<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures portal Blade (including @section content) sees payment flags before any view renders.
 */
final class ShareCustomerPortalOnlinePaymentFlags
{
    public function handle(Request $request, Closure $next): Response
    {
        $zibalMerchant = AppSetting::query()
            ->where('key', 'zibal_merchant')
            ->value('value');
        $paymentGateway = AppSetting::query()
            ->where('key', 'payment_gateway')
            ->value('value');

        $merchantTrim = is_string($zibalMerchant) ? trim($zibalMerchant) : '';
        $resolvedGateway = is_string($paymentGateway) && $paymentGateway !== '' ? $paymentGateway : 'zibal';
        $gatewayNormalized = in_array($resolvedGateway, ['zibal'], true) ? $resolvedGateway : 'zibal';
        $ready = $gatewayNormalized === 'zibal' && $merchantTrim !== '';

        View::share('userOnlinePaymentReady', $ready);
        View::share('userLoanFullSettlementOnlinePayUrl', route('user.loans.full-settlement.online-pay.start'));

        return $next($request);
    }
}
