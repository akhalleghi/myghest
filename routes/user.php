<?php

declare(strict_types=1);

use App\Http\Controllers\User\UserCustomerLoanRequestController;
use App\Http\Controllers\User\UserDepositDeclarationController;
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
    Route::get('/deposits', [UserDepositDeclarationController::class, 'page'])->name('deposits.index');
    Route::post('/deposits/review-notifications/ack', [UserDepositDeclarationController::class, 'acknowledgeReviewNotifications'])
        ->middleware('throttle:30,1')
        ->name('deposits.review-notifications.ack');
    Route::get('/deposits/list', [UserDepositDeclarationController::class, 'list'])
        ->middleware('throttle:60,1')
        ->name('deposits.list');
    Route::post('/deposits/items', [UserDepositDeclarationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('deposits.items.store');
    Route::post('/deposits/items/{deposit_declaration}/update', [UserDepositDeclarationController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('deposits.items.update');
    Route::delete('/deposits/items/{deposit_declaration}', [UserDepositDeclarationController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('deposits.items.destroy');
    Route::get('/deposits/attachments/{deposit_declaration}', [UserDepositDeclarationController::class, 'downloadAttachment'])
        ->middleware('throttle:60,1')
        ->name('deposits.attachment');
    Route::get('/loan-request', [UserPanelController::class, 'loanRequest'])->name('loan-request');
    Route::post('/loan-request', [UserCustomerLoanRequestController::class, 'store'])
        ->middleware('throttle:15,1')
        ->name('loan-request.store');

    Route::get('/loan-request/{customerLoanRequest}/wizard-context', [UserCustomerLoanRequestController::class, 'wizardContext'])
        ->middleware('throttle:60,1')
        ->name('loan-request.wizard-context');

    Route::post('/loan-request/{customerLoanRequest}/update', [UserCustomerLoanRequestController::class, 'update'])
        ->middleware('throttle:15,1')
        ->name('loan-request.update');

    Route::get('/loan-request/{customerLoanRequest}/documents/{customerLoanRequestDocument}/file', [UserCustomerLoanRequestController::class, 'documentFile'])
        ->middleware('throttle:120,1')
        ->name('loan-request.documents.file');

    Route::post('/logout', function (Request $request) {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    })->name('logout');
});
