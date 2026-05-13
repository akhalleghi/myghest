<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminCustomerLoanRequestController;
use App\Http\Controllers\Admin\AdminCustomerLoginReportController;
use App\Http\Controllers\Admin\AdminCustomerTransactionController;
use App\Http\Controllers\Admin\AdminDepositDeclarationController;
use App\Http\Controllers\Admin\AdminLoanRequestStatusDefinitionController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AppSettingsController;
use App\Http\Controllers\Admin\Auth\AdminCaptchaController;
use App\Http\Controllers\Admin\Auth\AdminDashboardController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\GuarantorOtpController;
use App\Http\Controllers\Admin\LoanTypeController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SmsManagementController;
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

    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('organizations.index');

    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('organizations.store');

    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('organizations.update');

    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('organizations.destroy');

    Route::post('/guarantor-otp/send', [GuarantorOtpController::class, 'send'])
        ->middleware('throttle:60,1')
        ->name('guarantor-otp.send');

    Route::post('/guarantor-otp/verify', [GuarantorOtpController::class, 'verify'])
        ->middleware('throttle:120,1')
        ->name('guarantor-otp.verify');

    Route::get('/customer-transactions', [AdminCustomerTransactionController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('customer-transactions.index');

    Route::get('/customer-transactions/export', [AdminCustomerTransactionController::class, 'export'])
        ->middleware('throttle:15,1')
        ->name('customer-transactions.export');

    Route::get('/deposit-declarations', [AdminDepositDeclarationController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('deposit-declarations.index');

    Route::get('/deposit-declarations/{deposit_declaration}/attachment', [AdminDepositDeclarationController::class, 'attachment'])
        ->middleware('throttle:120,1')
        ->name('deposit-declarations.attachment');

    Route::post('/deposit-declarations/{deposit_declaration}/review', [AdminDepositDeclarationController::class, 'review'])
        ->middleware('throttle:60,1')
        ->name('deposit-declarations.review');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('customers.index');

    Route::get('/customer-login-logs', [AdminCustomerLoginReportController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('customer-login-logs.index');

    Route::get('/loan-requests', [AdminCustomerLoanRequestController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.index');

    Route::get('/loan-requests/export', [AdminCustomerLoanRequestController::class, 'export'])
        ->middleware('throttle:20,1')
        ->name('loan-requests.export');

    Route::get('/loan-requests/print', [AdminCustomerLoanRequestController::class, 'printView'])
        ->middleware('throttle:30,1')
        ->name('loan-requests.print');

    Route::get('/loan-requests/{customerLoanRequest}/edit-context', [AdminCustomerLoanRequestController::class, 'editContext'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.edit-context');

    Route::put('/loan-requests/{customerLoanRequest}', [AdminCustomerLoanRequestController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('loan-requests.update');

    Route::delete('/loan-requests/{customerLoanRequest}', [AdminCustomerLoanRequestController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('loan-requests.destroy');

    Route::get('/loan-requests/{customerLoanRequest}/convert-preview', [AdminCustomerLoanRequestController::class, 'convertPreview'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.convert-preview');

    Route::post('/loan-requests/{customerLoanRequest}/convert', [AdminCustomerLoanRequestController::class, 'convert'])
        ->middleware('throttle:20,1')
        ->name('loan-requests.convert');

    Route::get('/loan-requests/{customerLoanRequest}/status-logs', [AdminCustomerLoanRequestController::class, 'statusLogs'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.status-logs');

    Route::get('/loan-requests/{customerLoanRequest}/status-logs/export', [AdminCustomerLoanRequestController::class, 'exportStatusLogs'])
        ->middleware('throttle:20,1')
        ->name('loan-requests.status-logs.export');

    Route::get('/loan-requests/{customerLoanRequest}/status-sms-logs', [AdminCustomerLoanRequestController::class, 'statusSmsLogs'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.status-sms-logs');

    Route::post('/loan-requests/{customerLoanRequest}/status-sms-logs/{smsLog}/resend', [AdminCustomerLoanRequestController::class, 'resendStatusSms'])
        ->middleware('throttle:30,1')
        ->name('loan-requests.status-sms-logs.resend');

    Route::get('/loan-requests/{customerLoanRequest}/documents/{customerLoanRequestDocument}/file', [AdminCustomerLoanRequestController::class, 'documentFile'])
        ->middleware('throttle:120,1')
        ->name('loan-requests.documents.file');

    Route::patch('/loan-requests/{customerLoanRequest}/documents/{customerLoanRequestDocument}', [AdminCustomerLoanRequestController::class, 'documentUpdate'])
        ->middleware('throttle:60,1')
        ->name('loan-requests.documents.update');

    Route::delete('/loan-requests/{customerLoanRequest}/documents/{customerLoanRequestDocument}', [AdminCustomerLoanRequestController::class, 'documentDestroy'])
        ->middleware('throttle:30,1')
        ->name('loan-requests.documents.destroy');

    Route::get('/loan-request-status-definitions', [AdminLoanRequestStatusDefinitionController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('loan-request-status-definitions.index');

    Route::post('/loan-request-status-definitions', [AdminLoanRequestStatusDefinitionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('loan-request-status-definitions.store');

    Route::put('/loan-request-status-definitions/{loanRequestStatusDefinition}', [AdminLoanRequestStatusDefinitionController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('loan-request-status-definitions.update');

    Route::delete('/loan-request-status-definitions/{loanRequestStatusDefinition}', [AdminLoanRequestStatusDefinitionController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('loan-request-status-definitions.destroy');

    Route::get('/customers/export-excel', [CustomerController::class, 'exportCustomersListExcel'])
        ->middleware('throttle:20,1')
        ->name('customers.export-excel');

    Route::get('/customers/import/sample-excel', [CustomerController::class, 'downloadCustomersImportSampleExcel'])
        ->middleware('throttle:60,1')
        ->name('customers.import.sample-excel');

    Route::post('/customers/import-excel', [CustomerController::class, 'importCustomersFromExcel'])
        ->middleware('throttle:12,1')
        ->name('customers.import-excel');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('customers.store');

    Route::get('/customers/{customer}/edit-data', [CustomerController::class, 'editData'])
        ->middleware('throttle:60,1')
        ->name('customers.edit-data');

    Route::get('/customers/{customer}/loan-manage-modal-context', [CustomerController::class, 'loanManageModalContext'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-manage-modal-context');

    Route::get('/customers/{customer}/loan-requests-panel', [AdminCustomerLoanRequestController::class, 'customerEmbedPanel'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-requests.embed');

    Route::get('/customers/{customer}/customer-transactions-panel', [AdminCustomerTransactionController::class, 'customerEmbedPanel'])
        ->middleware('throttle:60,1')
        ->name('customers.customer-transactions.embed');

    Route::get('/customers/{customer}/loan-board-summary', [CustomerController::class, 'loanBoardSummary'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-board-summary');

    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('customers.update');

    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('customers.destroy');

    Route::post('/customers/{customer}/quick-sms', [CustomerController::class, 'sendQuickSms'])
        ->middleware('throttle:20,1')
        ->name('customers.quick-sms');

    Route::get('/customers/{customer}/sms-modal-preview', [CustomerController::class, 'quickSmsModalPreview'])
        ->middleware('throttle:60,1')
        ->name('customers.sms-modal-preview');

    Route::post('/customers/{customer}/loan-files', [CustomerController::class, 'storeLoan'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.store');

    Route::put('/customers/{customer}/loan-files/{loanFile}', [CustomerController::class, 'updateLoan'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.update');

    Route::delete('/customers/{customer}/loan-files/{loanFile}', [CustomerController::class, 'destroyLoan'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.destroy');

    Route::post('/customers/{customer}/loan-files/{loanFile}/revoke-contract', [CustomerController::class, 'revokeLoanContract'])
        ->middleware('throttle:10,1')
        ->name('customers.loan-files.revoke-contract');

    Route::post('/customers/{customer}/loan-files/{loanFile}/send-sms', [CustomerController::class, 'sendLoanFileSms'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.send-sms');

    Route::get('/customers/{customer}/loan-files/{loanFile}/installments', [CustomerController::class, 'loanInstallments'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.installments.index');

    Route::get('/customers/{customer}/loan-files/{loanFile}/installment-booklet', [CustomerController::class, 'loanInstallmentBookletPrint'])
        ->middleware('throttle:30,1')
        ->name('customers.loan-files.installment-booklet');

    Route::put('/customers/{customer}/loan-files/{loanFile}/installments/{installment}', [CustomerController::class, 'updateLoanInstallment'])
        ->middleware('throttle:30,1')
        ->name('customers.loan-files.installments.update');

    Route::get('/customers/{customer}/loan-files/{loanFile}/installments/{installment}/payments', [CustomerController::class, 'loanInstallmentPayments'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.installments.payments.index');

    Route::post('/customers/{customer}/loan-files/{loanFile}/installments/{installment}/payments', [CustomerController::class, 'storeLoanInstallmentPayment'])
        ->middleware('throttle:40,1')
        ->name('customers.loan-files.installments.payments.store');

    Route::delete('/customers/{customer}/loan-files/{loanFile}/installments/{installment}/payments', [CustomerController::class, 'destroyAllLoanInstallmentPayments'])
        ->middleware('throttle:40,1')
        ->name('customers.loan-files.installments.payments.destroy-all');

    Route::put('/customers/{customer}/loan-files/{loanFile}/installments/{installment}/payments/{payment}', [CustomerController::class, 'updateLoanInstallmentPayment'])
        ->middleware('throttle:40,1')
        ->name('customers.loan-files.installments.payments.update');

    Route::delete('/customers/{customer}/loan-files/{loanFile}/installments/{installment}/payments/{payment}', [CustomerController::class, 'destroyLoanInstallmentPayment'])
        ->middleware('throttle:40,1')
        ->name('customers.loan-files.installments.payments.destroy');

    Route::get('/customers/{customer}/loan-files/{loanFile}/instant-settlement-preview', [CustomerController::class, 'loanInstantSettlementPreview'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.instant-settlement-preview');

    Route::get('/customers/{customer}/loan-files/{loanFile}/discount-preview', [CustomerController::class, 'loanDiscountPreview'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.discount-preview');

    Route::post('/customers/{customer}/loan-files/{loanFile}/discount', [CustomerController::class, 'storeLoanDiscount'])
        ->middleware('throttle:30,1')
        ->name('customers.loan-files.discount.store');

    Route::get('/customers/{customer}/guarantees-report', [CustomerController::class, 'loanGuaranteesReport'])
        ->middleware('throttle:60,1')
        ->name('customers.guarantees-report');

    Route::get('/customers/{customer}/guarantees-report/export-excel', [CustomerController::class, 'exportLoanGuaranteesReportExcel'])
        ->middleware('throttle:30,1')
        ->name('customers.guarantees-report.export-excel');

    Route::get('/customers/{customer}/sms-logs', [CustomerController::class, 'customerSmsLogs'])
        ->middleware('throttle:60,1')
        ->name('customers.sms-logs');

    Route::get('/customers/{customer}/sms-logs/export-excel', [CustomerController::class, 'exportCustomerSmsLogsExcel'])
        ->middleware('throttle:30,1')
        ->name('customers.sms-logs.export-excel');

    Route::get('/customers/{customer}/loan-files/{loanFile}/guarantees', [CustomerController::class, 'loanGuarantees'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.guarantees.index');

    Route::post('/customers/{customer}/loan-files/{loanFile}/guarantees', [CustomerController::class, 'storeLoanGuarantee'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.guarantees.store');

    Route::put('/customers/{customer}/loan-files/{loanFile}/guarantees/{guarantee}', [CustomerController::class, 'updateLoanGuarantee'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.guarantees.update');

    Route::delete('/customers/{customer}/loan-files/{loanFile}/guarantees/{guarantee}', [CustomerController::class, 'destroyLoanGuarantee'])
        ->middleware('throttle:20,1')
        ->name('customers.loan-files.guarantees.destroy');

    Route::get('/customers/{customer}/loan-files/{loanFile}/guarantees/{guarantee}/attachment', [CustomerController::class, 'loanGuaranteeAttachment'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.guarantees.attachment');

    Route::get('/customers/{customer}/wallet', [CustomerWalletController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('customers.wallet.show');

    Route::post('/customers/{customer}/wallet/lock', [CustomerWalletController::class, 'setLock'])
        ->middleware('throttle:30,1')
        ->name('customers.wallet.lock');

    Route::post('/customers/{customer}/wallet/adjust', [CustomerWalletController::class, 'adjust'])
        ->middleware('throttle:30,1')
        ->name('customers.wallet.adjust');

    Route::get('/customers/{customer}/wallet/transactions', [CustomerWalletController::class, 'transactions'])
        ->middleware('throttle:60,1')
        ->name('customers.wallet.transactions');

    Route::get('/customers/{customer}/wallet/transactions/export-excel', [CustomerWalletController::class, 'exportTransactionsExcel'])
        ->middleware('throttle:20,1')
        ->name('customers.wallet.transactions.export-excel');

    /*
    | تعریف انواع وام — فهرست و جدول (افزودن/ویرایش در گام بعدی).
    */
    Route::get('/loan-types', [LoanTypeController::class, 'index'])->name('loan-types.index');
    Route::get('/loan-types/export-excel', [LoanTypeController::class, 'exportExcel'])
        ->middleware('throttle:20,1')
        ->name('loan-types.export-excel');

    Route::post('/loan-types', [LoanTypeController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('loan-types.store');

    Route::put('/loan-types/{loanType}', [LoanTypeController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('loan-types.update');

    Route::get('/loan-types/{loanType}/plan-image', [LoanTypeController::class, 'planImage'])
        ->middleware('throttle:120,1')
        ->name('loan-types.plan-image');

    Route::delete('/loan-types/{loanType}', [LoanTypeController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('loan-types.destroy');

    Route::get('/sms-management', [SmsManagementController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('sms.index');

    Route::get('/sms-management/export-excel', [SmsManagementController::class, 'exportExcel'])
        ->middleware('throttle:30,1')
        ->name('sms.export-excel');

    Route::post('/sms-management/panel-settings', [SmsManagementController::class, 'updatePanelSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.panel-settings.update');

    Route::post('/sms-management/panel-test', [SmsManagementController::class, 'sendPanelTest'])
        ->middleware('throttle:20,1')
        ->name('sms.panel-test.send');

    Route::post('/sms-management/scenario-templates', [SmsManagementController::class, 'updateScenarioTemplates'])
        ->middleware('throttle:20,1')
        ->name('sms.scenario-templates.update');

    Route::post('/sms-management/reminder-settings', [SmsManagementController::class, 'updateReminderSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.reminder-settings.update');

    Route::post('/sms-management/templates', [SmsManagementController::class, 'storeTemplate'])
        ->middleware('throttle:30,1')
        ->name('sms.templates.store');

    Route::put('/sms-management/templates/{smsTemplate}', [SmsManagementController::class, 'updateTemplate'])
        ->middleware('throttle:30,1')
        ->name('sms.templates.update');

    Route::delete('/sms-management/templates/{smsTemplate}', [SmsManagementController::class, 'destroyTemplate'])
        ->middleware('throttle:30,1')
        ->name('sms.templates.destroy');

    Route::delete('/sms-management/{smsLog}', [SmsManagementController::class, 'destroyLog'])
        ->middleware('throttle:30,1')
        ->name('sms.destroy');

    Route::get('/notifications/{notification}/follow', [AdminNotificationController::class, 'follow'])
        ->middleware('throttle:120,1')
        ->name('notifications.follow');

    Route::post('/notifications/mark-all-read', [AdminNotificationController::class, 'markAllRead'])
        ->middleware('throttle:30,1')
        ->name('notifications.mark-all-read');

    Route::post('/app-settings/base', [AppSettingsController::class, 'updateBase'])
        ->middleware('throttle:20,1')
        ->name('app-settings.base.update');

    Route::post('/app-settings/ui', [AppSettingsController::class, 'updateUi'])
        ->middleware('throttle:20,1')
        ->name('app-settings.ui.update');

    Route::post('/app-settings/financial', [AppSettingsController::class, 'updateFinancial'])
        ->middleware('throttle:20,1')
        ->name('app-settings.financial.update');
});
