<?php

use App\Http\Middleware\AdminFarsiLocale;
use App\Http\Middleware\EnsureAdminGuest;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Support\Facades\Route;

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

            return '/';
        });

        $middleware->alias([
            'guest.admin' => EnsureAdminGuest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 429) {
                return null;
            }
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'تعداد درخواست‌ها بیش از حد مجاز است؛ یک دقیقه صبر کنید و دوباره تلاش کنید.',
            ], 429);
        });
    })->create();
