<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Payment\PortalLoanWalletPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UserLoanFullSettlementWalletPaymentController extends Controller
{
    private const RETURN_ROUTE_NAMES = ['user.loans.index', 'user.dashboard'];

    public function pay(Request $request, PortalLoanWalletPaymentService $walletPay): RedirectResponse
    {
        $validated = $request->validate([
            'customer_loan_file_id' => ['required', 'integer', 'min:1'],
            'payment_idempotency_key' => ['required', 'string', 'uuid'],
            'return_route' => ['nullable', 'string', 'in:'.implode(',', self::RETURN_ROUTE_NAMES)],
        ], [], [
            'customer_loan_file_id' => 'پرونده وام',
            'payment_idempotency_key' => 'شناسهٔ یکتای پرداخت',
            'return_route' => 'صفحه بازگشت',
        ]);

        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        $result = $walletPay->payFullSettlementFromWallet(
            $customer,
            (int) $validated['customer_loan_file_id'],
            (string) $validated['payment_idempotency_key'],
        );

        $payload = [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'track_id' => null,
            'bank_ref' => null,
        ];
        if (! empty($result['amount_toman'])) {
            $payload['amount_toman'] = (int) $result['amount_toman'];
        }

        $returnRoute = isset($validated['return_route']) && is_string($validated['return_route'])
            && in_array($validated['return_route'], self::RETURN_ROUTE_NAMES, true)
            ? $validated['return_route']
            : 'user.loans.index';

        $redirect = redirect()->route($returnRoute)->with('portal_pay_result', $payload);

        if (! empty($result['needs_topup']) && ! empty($result['shortfall_toman'])) {
            $redirect->with('portal_wallet_topup_prefill_toman', (int) $result['shortfall_toman']);
        }

        return $redirect;
    }
}
