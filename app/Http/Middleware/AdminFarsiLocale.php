<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * پنل ادمین فارسی‌زبان است؛ با APP_LOCALE=en هم پیام‌های اعتبارسنجی و ترجمه‌ها فارسی می‌مانند.
 */
final class AdminFarsiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('fa');

        return $next($request);
    }
}
