<?php

use App\Http\Middleware\AdminFarsiLocale;
use App\Http\Middleware\EnsureAdminGuest;
use App\Http\Middleware\EnsureCustomerGuest;
use App\Services\Auth\LoginAccessBlockService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', AdminFarsiLocale::class])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->prefix('user')
                ->name('user.')
                ->group(base_path('routes/user.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        Authenticate::redirectUsing(function (Request $request): string {
            if ($request->routeIs('admin.*')) {
                return route('admin.login');
            }

            if ($request->routeIs('user.*')) {
                return route('customer.login');
            }

            return '/';
        });

        $middleware->alias([
            'guest.admin' => EnsureAdminGuest::class,
            'guest.customer' => EnsureCustomerGuest::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminHasPermission::class,
            'portal.session' => \App\Http\Middleware\EnforcePortalSessionLifetime::class,
            'log.admin.activity' => \App\Http\Middleware\LogAdminActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 429) {
                return null;
            }

            $isLoginAttempt = $request->routeIs('admin.login.attempt', 'customer.login.attempt');
            $blockedMessage = LoginAccessBlockService::BLOCKED_ACCOUNT_MESSAGE;

            if ($isLoginAttempt) {
                $wantsJson = $request->expectsJson()
                    || $request->header('X-Login-Mode') === 'ajax'
                    || $request->boolean('ajax');

                if ($wantsJson) {
                    return response()->json([
                        'message' => $blockedMessage,
                        'errors' => ['username' => [$blockedMessage]],
                    ], 429);
                }

                $loginRoute = $request->routeIs('admin.*')
                    ? route('admin.login')
                    : route('customer.login');

                return redirect()
                    ->to($loginRoute)
                    ->withInput($request->except('password', '_token'))
                    ->withErrors(['username' => $blockedMessage]);
            }

            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'تعداد درخواست‌ها بیش از حد مجاز است؛ یک دقیقه صبر کنید و دوباره تلاش کنید.',
            ], 429);
        });
    })->create();
