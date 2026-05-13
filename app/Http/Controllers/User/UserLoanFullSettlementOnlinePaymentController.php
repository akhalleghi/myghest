<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Payment\CustomerTransactionLedgerService;
use App\Services\Payment\ZibalIpgClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class UserLoanFullSettlementOnlinePaymentController extends Controller
{
    private const RETURN_ROUTE_NAMES = ['user.loans.index', 'user.dashboard'];

    public function start(
        Request $request,
        CustomerLoanPortalPresenter $portalPresenter,
        ZibalIpgClient $zibal,
        CustomerTransactionLedgerService $ledger,
    ): RedirectResponse {
        $validated = $request->validate([
            'customer_loan_file_id' => ['required', 'integer', 'min:1'],
            'return_route' => ['nullable', 'string', Rule::in(self::RETURN_ROUTE_NAMES)],
        ], [], [
            'customer_loan_file_id' => 'پرونده وام',
            'return_route' => 'صفحه بازگشت',
        ]);

        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        $returnRoute = isset($validated['return_route']) && is_string($validated['return_route'])
            && in_array($validated['return_route'], self::RETURN_ROUTE_NAMES, true)
            ? $validated['return_route']
            : 'user.loans.index';

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

        $file = CustomerLoanFile::query()
            ->where('customer_id', (int) $customer->id)
            ->whereKey((int) $validated['customer_loan_file_id'])
            ->first();

        if ($file === null) {
            return $this->backWithPayFlash(false, 'پروندهٔ وام یافت نشد.');
        }

        $quote = $portalPresenter->fullSettlementOnlinePaymentQuote($file);
        if ($quote === null) {
            return $this->backWithPayFlash(false, 'تسویهٔ آنلاین برای این پرونده در دسترس نیست.');
        }

        $amountToman = (int) $quote['amount_toman'];
        $principalToman = (int) $quote['principal_toman'];
        $lateFeeToman = (int) $quote['late_fee_toman'];
        $amountRial = $amountToman * 10;
        if ($amountRial < 1000) {
            return $this->backWithPayFlash(false, 'مبلغ تسویه برای پرداخت آنلاین از حداقل مجاز درگاه کمتر است.', $amountToman);
        }

        $loanCode = (string) $file->loan_code;
        $description = 'تسویه کلی بدهی — پرونده '.$loanCode;
        $callbackUrl = route('payment.zibal.callback', absolute: true);
        $orderId = 'fs-'.$file->id.'-'.time();

        $intent = CustomerLoanFullSettlementOnlinePaymentIntent::query()->create([
            'customer_id' => (int) $customer->id,
            'customer_loan_file_id' => (int) $file->id,
            'expected_amount_toman' => $amountToman,
            'expected_amount_rial' => $amountRial,
            'principal_component_toman' => $principalToman,
            'late_fee_component_toman' => $lateFeeToman,
            'track_id' => null,
            'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_CREATED,
            'gateway_key' => 'zibal',
            'zibal_ref_number' => null,
            'failure_reason' => null,
        ]);
        $ledger->syncFromFullSettlementIntent($intent);

        $req = $zibal->request($merchant, $amountRial, $callbackUrl, $description, $orderId);
        if (! $req['ok'] || $req['track_id'] === null) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $req['message'],
            ]);
            $ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->backWithPayFlash(false, 'شروع پرداخت در درگاه ممکن نشد: '.$req['message'], $amountToman);
        }

        DB::transaction(function () use ($intent, $req): void {
            $fresh = CustomerLoanFullSettlementOnlinePaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->first();
            if ($fresh === null) {
                return;
            }
            if ($fresh->status !== CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_CREATED) {
                return;
            }
            $fresh->update([
                'track_id' => $req['track_id'],
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_REDIRECTED,
            ]);
        });

        $ledger->syncFromFullSettlementIntent($intent->fresh());

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
