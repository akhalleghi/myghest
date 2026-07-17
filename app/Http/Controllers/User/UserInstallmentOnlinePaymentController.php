<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Services\Payment\CustomerTransactionLedgerService;
use App\Services\Payment\InstallmentOnlinePaymentResolver;
use App\Services\Payment\ZibalIpgClient;
use App\Support\CustomerOnlinePaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class UserInstallmentOnlinePaymentController extends Controller
{
    private const RETURN_ROUTE_NAMES = ['user.loans.index', 'user.dashboard'];

    public function start(
        Request $request,
        InstallmentOnlinePaymentResolver $resolver,
        ZibalIpgClient $zibal,
        CustomerTransactionLedgerService $ledger,
    ): RedirectResponse {
        $validated = $request->validate([
            'customer_loan_installment_id' => ['required', 'integer', 'min:1'],
            'return_route' => ['nullable', 'string', Rule::in(self::RETURN_ROUTE_NAMES)],
        ], [], [
            'customer_loan_installment_id' => 'قسط',
            'return_route' => 'صفحه بازگشت',
        ]);

        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        if (! CustomerOnlinePaymentSettings::isEnabled()) {
            return $this->backWithPayFlash(false, 'پرداخت آنلاین توسط مدیریت غیرفعال شده است.');
        }

        $gateway = AppSetting::query()->where('key', 'payment_gateway')->value('value');
        $gatewayKey = is_string($gateway) && $gateway !== '' ? $gateway : 'zibal';
        if ($gatewayKey !== 'zibal') {
            return $this->backWithPayFlash(false, 'درگاه پرداخت فعال برای این سامانه پشتیبانی نمی‌شود.');
        }

        $merchantRaw = AppSetting::query()->where('key', 'zibal_merchant')->value('value');
        $merchant = is_string($merchantRaw) ? trim($merchantRaw) : '';
        if ($merchant === '') {
            return $this->backWithPayFlash(false, 'درگاه پرداخت هنوز توسط مدیریت تکمیل نشده است.');
        }

        $resolved = $resolver->resolveForCustomer($customer, (int) $validated['customer_loan_installment_id']);
        if (! ($resolved['ok'] ?? false)) {
            return $this->backWithPayFlash(false, (string) ($resolved['message'] ?? 'امکان پرداخت وجود ندارد.'));
        }

        $amountToman = (int) $resolved['amount_toman'];
        $amountRial = $amountToman * 10;
        if ($amountRial < 1000) {
            return $this->backWithPayFlash(false, 'مبلغ این قسط برای پرداخت آنلاین از حداقل مجاز درگاه کمتر است.', $amountToman);
        }

        /** @var CustomerLoanInstallment $installment */
        $installment = $resolved['installment'];
        /** @var CustomerLoanFile $file */
        $file = $resolved['file'];

        $description = 'پرداخت قسط '.$installment->sequence.' — پرونده '.$file->loan_code;
        $callbackUrl = route('payment.zibal.callback', absolute: true);
        $orderId = 'ins-'.$installment->id.'-'.time();

        $intent = CustomerLoanInstallmentOnlinePaymentIntent::query()->create([
            'customer_id' => (int) $customer->id,
            'customer_loan_installment_id' => (int) $installment->id,
            'expected_amount_toman' => $amountToman,
            'expected_amount_rial' => $amountRial,
            'track_id' => null,
            'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_CREATED,
            'gateway_key' => 'zibal',
            'zibal_ref_number' => null,
            'failure_reason' => null,
        ]);
        $ledger->syncFromInstallmentIntent($intent);

        $req = $zibal->request($merchant, $amountRial, $callbackUrl, $description, $orderId);
        if (! $req['ok'] || $req['track_id'] === null) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $req['message'],
            ]);
            $ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->backWithPayFlash(false, 'شروع پرداخت در درگاه ممکن نشد: '.$req['message'], $amountToman);
        }

        DB::transaction(function () use ($intent, $req): void {
            $fresh = CustomerLoanInstallmentOnlinePaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->first();
            if ($fresh === null) {
                return;
            }
            if ($fresh->status !== CustomerLoanInstallmentOnlinePaymentIntent::STATUS_CREATED) {
                return;
            }
            $fresh->update([
                'track_id' => $req['track_id'],
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_REDIRECTED,
            ]);
        });

        $ledger->syncFromInstallmentIntent($intent->fresh());

        $returnRoute = isset($validated['return_route']) && is_string($validated['return_route'])
            && in_array($validated['return_route'], self::RETURN_ROUTE_NAMES, true)
            ? $validated['return_route']
            : 'user.loans.index';
        $request->session()->put('portal_pay_return_route', $returnRoute);
        $request->session()->save();

        $startUrl = 'https://gateway.zibal.ir/start/'.$req['track_id'];

        return redirect()->away($startUrl);
    }

    private function backWithPayFlash(bool $success, string $message, ?int $amountToman = null): RedirectResponse
    {
        $payload = [
            'success' => $success,
            'message' => $message,
            'track_id' => null,
            'bank_ref' => null,
        ];
        if ($amountToman !== null && $amountToman > 0) {
            $payload['amount_toman'] = $amountToman;
        }

        return back()->with('portal_pay_result', $payload);
    }
}
