<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminGuest
{
    /**
     * اگر ادمین قبلاً وارد شده، از مشاهده‌ی مجدد صفحه‌ی ورود جلوگیری می‌کنیم (کاهش سطح حمله).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
