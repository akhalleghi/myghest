<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * نقطهٔ بازگشت کاربر از درگاه زیبال (IPG).
 * زیبال پارامترها را به صورت query string با GET ارسال می‌کند.
 */
final class ZibalCallbackController extends Controller
{
    public function __invoke(): View
    {
        return view('payment.zibal-callback');
    }
}
