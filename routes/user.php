<?php

declare(strict_types=1);

use App\Http\Controllers\User\UserPanelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| روت‌های کاربر سامانه اقساط
|--------------------------------------------------------------------------
|
| پیشوند مسیر `/user` و نام پیشوندی `user.*` در bootstrap/app.php اعمال شده است.
|
*/

Route::middleware(['auth:customer'])->group(function (): void {
    Route::get('/dashboard', [UserPanelController::class, 'dashboard'])->name('dashboard');
    Route::get('/loans', [UserPanelController::class, 'loans'])->name('loans.index');
    Route::get('/deposits', [UserPanelController::class, 'deposits'])->name('deposits.index');
    Route::get('/loan-request', [UserPanelController::class, 'loanRequest'])->name('loan-request');

    Route::post('/logout', function (Request $request) {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    })->name('logout');
});
