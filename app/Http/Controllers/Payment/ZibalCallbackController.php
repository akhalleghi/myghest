<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\InstallmentOnlinePaymentCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * نقطهٔ بازگشت کاربر از درگاه زیبال (IPG).
 * زیبال پارامترها را به صورت query string با GET ارسال می‌کند.
 */
final class ZibalCallbackController extends Controller
{
    public function __invoke(Request $request, InstallmentOnlinePaymentCompletionService $completion): RedirectResponse
    {
        $trackId = (int) $request->query('trackId', 0);
        $success = $request->query('success');
        $status = (string) $request->query('status', '');
        $successOk = $success === '1' || $success === 1 || $success === true || $status === '2';

        return $completion->completeZibalReturn($trackId, $successOk);
    }
}
