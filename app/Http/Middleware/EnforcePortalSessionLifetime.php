<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\LoginAccessBlock;
use App\Services\Admin\AdminActivityLogService;
use App\Support\PortalLoginSecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePortalSessionLifetime
{
    public function handle(Request $request, Closure $next, string $guard = LoginAccessBlock::GUARD_ADMIN): Response
    {
        $sessionKey = $this->sessionActivityKey($guard);
        $authGuard = $guard === LoginAccessBlock::GUARD_ADMIN ? 'admin' : 'customer';
        $maxIdleMinutes = PortalLoginSecuritySettings::sessionLifetimeMinutes($guard);

        if (Auth::guard($authGuard)->check()) {
            $lastActivity = $request->session()->get($sessionKey);
            if (is_numeric($lastActivity)) {
                $idleSeconds = now()->timestamp - (int) $lastActivity;
                if ($idleSeconds > ($maxIdleMinutes * 60)) {
                    if ($guard === LoginAccessBlock::GUARD_ADMIN) {
                        /** @var Admin|null $expiredAdmin */
                        $expiredAdmin = Auth::guard($authGuard)->user();
                        if ($expiredAdmin instanceof Admin) {
                            app(AdminActivityLogService::class)->recordSessionExpired($expiredAdmin, $request);
                        }
                    }

                    Auth::guard($authGuard)->logout();
                    $request->session()->forget($sessionKey);
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $loginRoute = $guard === LoginAccessBlock::GUARD_ADMIN
                        ? 'admin.login'
                        : 'customer.login';

                    return redirect()
                        ->route($loginRoute)
                        ->with('flash_info', 'به‌دلیل عدم فعالیت، نشست شما پایان یافت. لطفاً دوباره وارد شوید.');
                }
            }

            $request->session()->put($sessionKey, now()->timestamp);
        }

        return $next($request);
    }

    public static function touchSession(Request $request, string $guard): void
    {
        $request->session()->put(
            (new self)->sessionActivityKey($guard),
            now()->timestamp,
        );
    }

    private function sessionActivityKey(string $guard): string
    {
        return 'portal_session_activity:'.$guard;
    }
}
