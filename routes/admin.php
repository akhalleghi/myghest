<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Auth\AdminCaptchaController;
use App\Http\Controllers\Admin\Auth\AdminDashboardController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| روت‌های ادمین سامانه اقساط
|--------------------------------------------------------------------------
|
| پیشوند مسیر `/admin` و نام پیشوندی `admin.*` در bootstrap/app.php اعمال شده است.
| کل روت‌ها فقط وب و با کوکی نشست Laravel اجرا می‌شوند؛ از این لیست خارج نشوید تا امنیت یکدست باشد.
|
*/

// --- مهمان‌ها: ورود به پنل ادمین؛ در صورت داشتن نشست معتبر ادمین، کاربر را به داشبورد می‌فرستیم ---
Route::middleware(['guest.admin'])->group(function (): void {

    /*
    | نمایش فرم ورود؛ استفاده‌شده برای GET پس از خارج شدن از کشف مسیر عمومی کنترلر.
    */
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');

    /*
    | عملیات ارسال نام کاربری، رمز و کپچا؛ برای کاهش brute-force محدودیت نرخ دقیقه‌ای اعمال شده است.
    */
    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');

    /*
    | تصویر کپچا بدون کش با محدودیت نرخ جداگانه.
    */
    Route::get('/captcha.png', [AdminCaptchaController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('captcha');

    /*
    | تولید کپچای جدید (مثلاً با کلیک روی تصویر): پاسخ JSON با تصویر base64؛ CSRF الزامی.
    */
    Route::post('/captcha/refresh', [AdminCaptchaController::class, 'refresh'])
        ->middleware('throttle:60,1')
        ->name('captcha.refresh');
});

// --- نشست احرازشده با گارد `admin`: پس از بررسی دیتابیس و فیلد فعال بودن حساب در کنترلر ---
Route::middleware(['auth:admin'])->group(function (): void {

    /*
    | خروج امن؛ نوع متد POST و CSRF اجباری است تا دستکاری پیوند GET ممکن نباشد.
    */
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

    /*
    | داشبورد موقت؛ ساخت صفحه‌های واقعی سیستم اقساط در گام بعدی خواهد بود.
    */
    Route::get('/', AdminDashboardController::class)->name('dashboard');
});
