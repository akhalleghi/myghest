<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\ZibalCallbackController;
use App\Http\Controllers\User\Auth\CustomerCaptchaController;
use App\Http\Controllers\User\Auth\CustomerLoginController;
use App\Http\Controllers\User\Auth\CustomerPasswordForgotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| روت‌های عمومی برنامه (خارج از بخش ادمین و کاربر ماژولار)
|--------------------------------------------------------------------------
*/

Route::middleware(['guest.customer'])->group(function (): void {
    Route::get('/', [CustomerLoginController::class, 'create'])->name('customer.login');
    Route::post('/', [CustomerLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('customer.login.attempt');

    Route::prefix('auth')->name('customer.auth.')->group(function (): void {
        Route::get('captcha/{purpose}', [CustomerCaptchaController::class, 'show'])
            ->whereIn('purpose', ['login', 'forgot'])
            ->middleware('throttle:120,1')
            ->name('captcha');

        Route::post('captcha/refresh', [CustomerCaptchaController::class, 'refresh'])
            ->middleware('throttle:60,1')
            ->name('captcha.refresh');

        Route::post('forgot/request-otp', [CustomerPasswordForgotController::class, 'requestOtp'])
            ->middleware('throttle:5,1')
            ->name('forgot.request-otp');

        Route::post('forgot/verify-otp', [CustomerPasswordForgotController::class, 'verifyOtp'])
            ->middleware('throttle:30,1')
            ->name('forgot.verify-otp');

        Route::post('forgot/reset-password', [CustomerPasswordForgotController::class, 'resetPassword'])
            ->middleware('throttle:10,1')
            ->name('forgot.reset-password');
    });
});

Route::get('/payment/zibal/callback', ZibalCallbackController::class)
    ->name('payment.zibal.callback');
