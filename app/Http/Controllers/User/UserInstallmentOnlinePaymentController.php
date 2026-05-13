<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Services\Payment\InstallmentOnlinePaymentResolver;
use App\Services\Payment\ZibalIpgClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UserInstallmentOnlinePaymentController extends Controller
{
    public function start(
        Request $request,
        InstallmentOnlinePaymentResolver $resolver,
        ZibalIpgClient $zibal,
    ): RedirectResponse {
        $validated = $request->validate([
            'customer_loan_installment_id' => ['required', 'integer', 'min:1'],
        ], [], [
            'customer_loan_installment_id' => 'قسط',
        ]);

        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
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
            return $this->backWithPayFlash(false, 'مبلغ این قسط برای پرداخت آنلاین از حداقل مجاز درگاه کمتر است.');
        }

        /** @var \App\Models\CustomerLoanInstallment $installment */
        $installment = $resolved['installment'];
        /** @var \App\Models\CustomerLoanFile $file */
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

        $req = $zibal->request($merchant, $amountRial, $callbackUrl, $description, $orderId);
        if (! $req['ok'] || $req['track_id'] === null) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $req['message'],
            ]);

            return $this->backWithPayFlash(false, 'شروع پرداخت در درگاه ممکن نشد: '.$req['message']);
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

        $startUrl = 'https://gateway.zibal.ir/start/'.$req['track_id'];

        return redirect()->away($startUrl);
    }

    private function backWithPayFlash(bool $success, string $message): RedirectResponse
    {
        return back()->with('portal_pay_result', [
            'success' => $success,
            'message' => $message,
            'track_id' => null,
            'bank_ref' => null,
        ]);
    }
}
