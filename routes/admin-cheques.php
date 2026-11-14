<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ChequeSalesController as C;
use Illuminate\Support\Facades\Route;

Route::prefix('cheque-sales')->name('cheques.')->group(function (): void {
    Route::get('/customers/{chequeCustomer}/media', [\App\Http\Controllers\Admin\ChequeCustomerMediaController::class, 'index'])->name('media.index');
    Route::get('/customers/{chequeCustomer}/media/{media}', [\App\Http\Controllers\Admin\ChequeCustomerMediaController::class, 'show'])->name('media.show');
    Route::get('/', [C::class, 'index'])->name('index');
    Route::get('/reports', [C::class, 'index'])->name('reports');
    Route::get('/export', [C::class, 'export'])->name('export');
    Route::post('/customers', [C::class, 'saveCustomer'])->name('customers.store');
    Route::put('/customers/{chequeCustomer}', [C::class, 'saveCustomer'])->name('customers.update');
    Route::post('/customers/{chequeCustomer}/wallet', [C::class, 'wallet'])->name('wallet');
    Route::get('/customers/{chequeCustomer}/notes', [C::class, 'customerNotes'])->name('customers.notes');
    Route::post('/customers/{chequeCustomer}/notes', [C::class, 'customerNoteStore'])->name('customers.notes.store');
    Route::post('/customers/{chequeCustomer}/sales', [C::class, 'storeSale'])->name('sales.store');
    Route::post('/customers/{chequeCustomer}/sms-preview', [C::class, 'customerSmsPreview'])->middleware('throttle:30,1')->name('customers.sms.preview');
    Route::post('/customers/{chequeCustomer}/sms-send', [C::class, 'customerSmsSend'])->middleware('throttle:20,1')->name('customers.sms.send');
    Route::post('/quote', [C::class, 'quote'])->name('quote');
    Route::get('/cheques/{cheque}', [C::class, 'details'])->name('details');
    Route::post('/cheques/{cheque}/notes', [C::class, 'note'])->name('notes');
    Route::post('/cheques/{cheque}/actions', [C::class, 'action'])->name('actions');
    Route::post('/cheques/{cheque}/receipts', [C::class, 'action'])->name('receipts');
    Route::post('/cheques/{cheque}/sms-preview', [C::class, 'smsPreview'])->middleware('throttle:30,1')->name('sms.preview');
    Route::post('/cheques/{cheque}/sms-send', [C::class, 'smsSend'])->middleware('throttle:20,1')->name('sms.send');
    Route::post('/verification/preview', [C::class, 'verificationPreview'])->middleware('throttle:20,1')->name('verification.preview');
    Route::post('/verification/send', [C::class, 'verificationSend'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('/verification/confirm', [C::class, 'verificationConfirm'])->middleware('throttle:15,1')->name('verification.confirm');
});
