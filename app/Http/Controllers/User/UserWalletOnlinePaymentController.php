<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerWalletOnlinePaymentIntent;
use App\Services\Payment\CustomerTransactionLedgerService;
use App\Services\Payment\ZibalIpgClient;
use App\Services\Wallet\CustomerWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UserWalletOnlinePaymentController extends Controller
{
    private const MIN_TOPUP_TOMAN = 10_000;

    private const MAX_TOPUP_TOMAN = 500_000_000;

    public function start(
        Request $request,
        ZibalIpgClient $zibal,
        CustomerTransactionLedgerService $ledger,
        CustomerWalletService $walletService,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount_toman' => ['required', 'integer', 'min:'.self::MIN_TOPUP_TOMAN, 'max:'.self::MAX_TOPUP_TOMAN],
        ], [], [
            'amount_toman' => 'مبلغ شارژ',
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

        $wallet = $walletService->ensureWallet($customer);
        if ($wallet->is_locked) {
            return $this->backWithPayFlash(false, 'کیف پول شما قفل است و امکان شارژ آنلاین وجود ندارد.');
        }

        $amountToman = (int) $validated['amount_toman'];
        $amountRial = $amountToman * 10;
        if ($amountRial < 1000) {
            return $this->backWithPayFlash(false, 'مبلغ برای پرداخت آنلاین از حداقل مجاز درگاه کمتر است.', $amountToman);
        }

        $description = 'شارژ کیف پول — '.number_format($amountToman, 0, '.', ',').' تومان';
        $callbackUrl = route('payment.zibal.callback', absolute: true);
        $orderId = 'wlt-'.$customer->id.'-'.time();

        $intent = CustomerWalletOnlinePaymentIntent::query()->create([
            'customer_id' => (int) $customer->id,
            'expected_amount_toman' => $amountToman,
            'expected_amount_rial' => $amountRial,
            'track_id' => null,
            'status' => CustomerWalletOnlinePaymentIntent::STATUS_CREATED,
            'gateway_key' => 'zibal',
            'zibal_ref_number' => null,
            'failure_reason' => null,
        ]);
        $ledger->syncFromWalletTopupIntent($intent);

        $req = $zibal->request($merchant, $amountRial, $callbackUrl, $description, $orderId);
        if (! $req['ok'] || $req['track_id'] === null) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $req['message'],
            ]);
            $ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->backWithPayFlash(false, 'شروع پرداخت در درگاه ممکن نشد: '.$req['message'], $amountToman);
        }

        DB::transaction(function () use ($intent, $req): void {
            $fresh = CustomerWalletOnlinePaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->first();
            if ($fresh === null) {
                return;
            }
            if ($fresh->status !== CustomerWalletOnlinePaymentIntent::STATUS_CREATED) {
                return;
            }
            $fresh->update([
                'track_id' => $req['track_id'],
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_REDIRECTED,
            ]);
        });

        $ledger->syncFromWalletTopupIntent($intent->fresh());

        $request->session()->put('portal_pay_return_route', 'user.dashboard');
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
