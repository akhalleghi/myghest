<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Payment\PortalLoanWalletPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UserInstallmentWalletPaymentController extends Controller
{
    public function pay(Request $request, PortalLoanWalletPaymentService $walletPay): RedirectResponse
    {
        $validated = $request->validate([
            'customer_loan_installment_id' => ['required', 'integer', 'min:1'],
            'payment_idempotency_key' => ['required', 'string', 'uuid'],
            'amount_toman' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
        ], [], [
            'customer_loan_installment_id' => 'قسط',
            'payment_idempotency_key' => 'شناسهٔ یکتای پرداخت',
            'amount_toman' => 'مبلغ پرداخت',
        ]);

        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        $amountToman = isset($validated['amount_toman']) ? (int) $validated['amount_toman'] : null;

        $result = $walletPay->payInstallmentFromWallet(
            $customer,
            (int) $validated['customer_loan_installment_id'],
            (string) $validated['payment_idempotency_key'],
            $amountToman,
        );

        return $this->portalPayRedirect($request, $result);
    }

    /**
     * @param  array{ok: bool, message: string, amount_toman?: int, shortfall_toman?: int, needs_topup?: bool, replay?: bool}  $result
     */
    private function portalPayRedirect(Request $request, array $result): RedirectResponse
    {
        $payload = [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'track_id' => null,
            'bank_ref' => null,
        ];
        if (! empty($result['amount_toman'])) {
            $payload['amount_toman'] = (int) $result['amount_toman'];
        }
        if (! empty($result['track_id'])) {
            $payload['track_id'] = (string) $result['track_id'];
        }
        if (! empty($result['bank_ref'])) {
            $payload['bank_ref'] = (string) $result['bank_ref'];
        }

        $redirect = back()->with('portal_pay_result', $payload);

        if (! empty($result['needs_topup']) && ! empty($result['shortfall_toman'])) {
            $redirect->with('portal_wallet_topup_prefill_toman', (int) $result['shortfall_toman']);
        }

        return $redirect;
    }
}
