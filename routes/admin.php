<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminCustomerLoanRequestController;
use App\Http\Controllers\Admin\AdminCustomerLoginReportController;
use App\Http\Controllers\Admin\AdminCustomerTransactionController;
use App\Http\Controllers\Admin\AdminDepositDeclarationController;
use App\Http\Controllers\Admin\AdminLoanRequestStatusDefinitionController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminCustomerSupportTicketController;
use App\Http\Controllers\Admin\AdminSupportTicketController;
use App\Http\Controllers\Admin\AdminInternalTicketController;
use App\Http\Controllers\Admin\AdminDatabaseBackupController;
use App\Http\Controllers\Admin\AppSettingsController;
use App\Http\Controllers\Admin\LoginBackgroundSettingsController;
use App\Http\Controllers\Admin\Auth\AdminCaptchaController;
use App\Http\Controllers\Admin\Auth\AdminDashboardController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\Auth\AdminLoginTwoFactorController;
use App\Http\Controllers\Admin\AdminCustomerNoteController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\GuaranteeReturnOtpController;
use App\Http\Controllers\Admin\GuarantorOtpController;
use App\Http\Controllers\Admin\LoanCreationOtpController;
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
        ->name('login.attempt');

    Route::post('/login/verify-otp', [AdminLoginTwoFactorController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('login.verify-otp');

    Route::post('/login/resend-otp', [AdminLoginTwoFactorController::class, 'resend'])
        ->middleware('throttle:20,1')
        ->name('login.resend-otp');

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
Route::middleware(['auth:admin', 'portal.session:admin'])->group(function (): void {

    /*
    | خروج امن؛ نوع متد POST و CSRF اجباری است تا دستکاری پیوند GET ممکن نباشد.
    */
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth:admin', 'portal.session:admin', 'admin.permission', 'log.admin.activity'])->group(function (): void {

    /*
    | داشبورد موقت؛ ساخت صفحه‌های واقعی سیستم اقساط در گام بعدی خواهد بود.
    */
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/reports', [AdminReportsController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('reports.index');

    Route::get('/reports/member-loans-by-date/data', [AdminReportsController::class, 'memberLoansByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.member-loans-by-date.data');

    Route::get('/reports/member-loans-by-date/export-excel', [AdminReportsController::class, 'exportMemberLoansByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.member-loans-by-date.export-excel');

    Route::get('/reports/installment-due-by-date/data', [AdminReportsController::class, 'installmentDueByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.installment-due-by-date.data');

    Route::get('/reports/installment-due-by-date/export-excel', [AdminReportsController::class, 'exportInstallmentDueByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.installment-due-by-date.export-excel');

    Route::get('/reports/deposits-by-date/data', [AdminReportsController::class, 'depositsByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.deposits-by-date.data');

    Route::get('/reports/deposits-by-date/export-excel', [AdminReportsController::class, 'exportDepositsByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.deposits-by-date.export-excel');

    Route::get('/reports/settled-members/data', [AdminReportsController::class, 'settledMembersData'])
        ->middleware('throttle:90,1')
        ->name('reports.settled-members.data');

    Route::get('/reports/settled-members/export-excel', [AdminReportsController::class, 'exportSettledMembersExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.settled-members.export-excel');

    Route::get('/reports/wallet-transactions-by-date/data', [AdminReportsController::class, 'walletTransactionsByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.wallet-transactions-by-date.data');

    Route::get('/reports/wallet-transactions-by-date/export-excel', [AdminReportsController::class, 'exportWalletTransactionsByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.wallet-transactions-by-date.export-excel');

    Route::get('/reports/loan-guarantees/data', [AdminReportsController::class, 'loanGuaranteesData'])
        ->middleware('throttle:90,1')
        ->name('reports.loan-guarantees.data');

    Route::get('/reports/loan-guarantees/export-excel', [AdminReportsController::class, 'exportLoanGuaranteesExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.loan-guarantees.export-excel');

    Route::get('/reports/loan-interest-fees/data', [AdminReportsController::class, 'loanInterestFeesData'])
        ->middleware('throttle:90,1')
        ->name('reports.loan-interest-fees.data');

    Route::get('/reports/loan-interest-fees/customers-search', [AdminReportsController::class, 'loanInterestFeesCustomersSearch'])
        ->middleware('throttle:90,1')
        ->name('reports.loan-interest-fees.customers-search');

    Route::get('/reports/loan-interest-fees/export-excel', [AdminReportsController::class, 'exportLoanInterestFeesExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.loan-interest-fees.export-excel');

    Route::get('/reports/admin-activity/data', [AdminReportsController::class, 'adminActivityData'])
        ->middleware('throttle:90,1')
        ->name('reports.admin-activity.data');

    Route::get('/reports/admin-activity/admins-search', [AdminReportsController::class, 'adminActivityAdminsSearch'])
        ->middleware('throttle:90,1')
        ->name('reports.admin-activity.admins-search');

    Route::get('/reports/admin-activity/export-excel', [AdminReportsController::class, 'exportAdminActivityExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.admin-activity.export-excel');

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

    Route::get('/tickets', [AdminSupportTicketController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('tickets.index');

    Route::get('/tickets/list', [AdminSupportTicketController::class, 'list'])
        ->middleware('throttle:90,1')
        ->name('tickets.list');

    Route::get('/tickets/customers-search', [AdminSupportTicketController::class, 'customersSearch'])
        ->middleware('throttle:90,1')
        ->name('tickets.customers-search');

    Route::post('/tickets', [AdminSupportTicketController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('tickets.store');

    Route::post('/tickets/{ticket}/reply', [AdminSupportTicketController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('tickets.reply');

    Route::patch('/tickets/{ticket}/status', [AdminSupportTicketController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('tickets.status');

    Route::get('/tickets/attachments/{attachment}', [AdminSupportTicketController::class, 'attachment'])
        ->middleware('throttle:120,1')
        ->name('tickets.attachment');

    Route::get('/internal-tickets', [AdminInternalTicketController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('internal-tickets.index');

    Route::get('/internal-tickets/list', [AdminInternalTicketController::class, 'list'])
        ->middleware('throttle:90,1')
        ->name('internal-tickets.list');

    Route::get('/internal-tickets/admins-search', [AdminInternalTicketController::class, 'adminsSearch'])
        ->middleware('throttle:90,1')
        ->name('internal-tickets.admins-search');

    Route::get('/internal-tickets/attachments/{attachment}', [AdminInternalTicketController::class, 'attachment'])
        ->middleware('throttle:120,1')
        ->name('internal-tickets.attachment');

    Route::get('/internal-tickets/{ticket}', [AdminInternalTicketController::class, 'show'])
        ->middleware('throttle:90,1')
        ->name('internal-tickets.show');

    Route::post('/internal-tickets', [AdminInternalTicketController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('internal-tickets.store');

    Route::post('/internal-tickets/{ticket}/reply', [AdminInternalTicketController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('internal-tickets.reply');

    Route::patch('/internal-tickets/{ticket}/status', [AdminInternalTicketController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('internal-tickets.status');

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

    Route::get('/reports', [AdminReportsController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('reports.index');

    Route::get('/reports/member-loans-by-date/data', [AdminReportsController::class, 'memberLoansByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.member-loans-by-date.data');

    Route::get('/reports/member-loans-by-date/export-excel', [AdminReportsController::class, 'exportMemberLoansByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.member-loans-by-date.export-excel');

    Route::get('/reports/installment-due-by-date/data', [AdminReportsController::class, 'installmentDueByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.installment-due-by-date.data');

    Route::get('/reports/installment-due-by-date/export-excel', [AdminReportsController::class, 'exportInstallmentDueByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.installment-due-by-date.export-excel');

    Route::get('/reports/deposits-by-date/data', [AdminReportsController::class, 'depositsByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.deposits-by-date.data');

    Route::get('/reports/deposits-by-date/export-excel', [AdminReportsController::class, 'exportDepositsByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.deposits-by-date.export-excel');

    Route::get('/reports/settled-members/data', [AdminReportsController::class, 'settledMembersData'])
        ->middleware('throttle:90,1')
        ->name('reports.settled-members.data');

    Route::get('/reports/settled-members/export-excel', [AdminReportsController::class, 'exportSettledMembersExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.settled-members.export-excel');

    Route::get('/reports/wallet-transactions-by-date/data', [AdminReportsController::class, 'walletTransactionsByDateData'])
        ->middleware('throttle:90,1')
        ->name('reports.wallet-transactions-by-date.data');

    Route::get('/reports/wallet-transactions-by-date/export-excel', [AdminReportsController::class, 'exportWalletTransactionsByDateExcel'])
        ->middleware('throttle:20,1')
        ->name('reports.wallet-transactions-by-date.export-excel');

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

    Route::get('/customers/{customer}/notes', [AdminCustomerNoteController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('customers.notes.index');

    Route::post('/customers/{customer}/notes', [AdminCustomerNoteController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('customers.notes.store');

    Route::put('/customers/{customer}/notes/{note}', [AdminCustomerNoteController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('customers.notes.update');

    Route::delete('/customers/{customer}/notes/{note}', [AdminCustomerNoteController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('customers.notes.destroy');

    Route::get('/customers/{customer}/loan-manage-modal-context', [CustomerController::class, 'loanManageModalContext'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-manage-modal-context');

    Route::get('/customers/{customer}/loan-requests-panel', [AdminCustomerLoanRequestController::class, 'customerEmbedPanel'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-requests.embed');

    Route::get('/customers/{customer}/customer-transactions-panel', [AdminCustomerTransactionController::class, 'customerEmbedPanel'])
        ->middleware('throttle:60,1')
        ->name('customers.customer-transactions.embed');

    Route::get('/customers/{customer}/tickets-panel', [AdminCustomerSupportTicketController::class, 'customerEmbedPanel'])
        ->middleware('throttle:60,1')
        ->name('customers.tickets.embed');

    Route::get('/customers/{customer}/tickets/list', [AdminCustomerSupportTicketController::class, 'list'])
        ->middleware('throttle:60,1')
        ->name('customers.tickets.list');

    Route::post('/customers/{customer}/tickets', [AdminCustomerSupportTicketController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('customers.tickets.store');

    Route::post('/customers/{customer}/tickets/{ticket}/reply', [AdminCustomerSupportTicketController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('customers.tickets.reply');

    Route::patch('/customers/{customer}/tickets/{ticket}/status', [AdminCustomerSupportTicketController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('customers.tickets.status');

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

    Route::post('/customers/{customer}/loan-creation-otp/send', [LoanCreationOtpController::class, 'send'])
        ->middleware('throttle:30,1')
        ->name('customers.loan-creation-otp.send');

    Route::post('/customers/{customer}/loan-creation-otp/verify', [LoanCreationOtpController::class, 'verify'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-creation-otp.verify');

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

    Route::get('/customers/{customer}/instant-settlement-all-preview', [CustomerController::class, 'loanInstantSettlementAllPreview'])
        ->middleware('throttle:60,1')
        ->name('customers.instant-settlement-all-preview');

    Route::post('/customers/{customer}/settle-all-loans', [CustomerController::class, 'settleAllLoans'])
        ->middleware('throttle:30,1')
        ->name('customers.settle-all-loans');

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

    Route::post('/customers/{customer}/loan-files/{loanFile}/guarantee-return-otp/send', [GuaranteeReturnOtpController::class, 'send'])
        ->middleware('throttle:30,1')
        ->name('customers.loan-files.guarantee-return-otp.send');

    Route::post('/customers/{customer}/loan-files/{loanFile}/guarantee-return-otp/verify', [GuaranteeReturnOtpController::class, 'verify'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.guarantee-return-otp.verify');

    Route::get('/customers/{customer}/loan-files/{loanFile}/guarantees/{guarantee}/return-document', [CustomerController::class, 'loanGuaranteeReturnDocument'])
        ->middleware('throttle:60,1')
        ->name('customers.loan-files.guarantees.return-document');

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

    Route::post('/sms-management/free-send', [SmsManagementController::class, 'sendFreeSms'])
        ->middleware('throttle:10,1')
        ->name('sms.free-send');

    Route::get('/sms-management/panel-credit', [SmsManagementController::class, 'panelCredit'])
        ->middleware('throttle:12,1')
        ->name('sms.panel-credit');

    Route::post('/sms-management/panel-api-token', [SmsManagementController::class, 'updatePanelApiToken'])
        ->middleware('throttle:20,1')
        ->name('sms.panel-api-token.update');

    Route::post('/sms-management/scenario-templates', [SmsManagementController::class, 'updateScenarioTemplates'])
        ->middleware('throttle:20,1')
        ->name('sms.scenario-templates.update');

    Route::post('/sms-management/reminder-settings', [SmsManagementController::class, 'updateReminderSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.reminder-settings.update');

    Route::post('/sms-management/admin-login-notify', [SmsManagementController::class, 'updateAdminLoginNotifySettings'])
        ->middleware('throttle:20,1')
        ->name('sms.admin-login-notify.update');

    Route::post('/sms-management/admin-login-self-notify', [SmsManagementController::class, 'updateAdminLoginSelfNotifySettings'])
        ->middleware('throttle:20,1')
        ->name('sms.admin-login-self-notify.update');

    Route::post('/sms-management/customer-login-notify-admin', [SmsManagementController::class, 'updateCustomerLoginNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-login-notify-admin.update');

    Route::post('/sms-management/customer-installment-payment-notify-admin', [SmsManagementController::class, 'updateCustomerInstallmentPaymentNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-installment-payment-notify-admin.update');

    Route::post('/sms-management/customer-full-settlement-notify-admin', [SmsManagementController::class, 'updateCustomerFullSettlementNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-full-settlement-notify-admin.update');

    Route::post('/sms-management/customer-deposit-declaration-notify-admin', [SmsManagementController::class, 'updateCustomerDepositDeclarationNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-deposit-declaration-notify-admin.update');

    Route::post('/sms-management/customer-support-ticket-notify-admin', [SmsManagementController::class, 'updateCustomerSupportTicketNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-support-ticket-notify-admin.update');

    Route::post('/sms-management/customer-loan-request-notify-admin', [SmsManagementController::class, 'updateCustomerLoanRequestNotifyAdminSettings'])
        ->middleware('throttle:20,1')
        ->name('sms.customer-loan-request-notify-admin.update');

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

    Route::post('/app-settings/login-backgrounds/{context}/preference', [LoginBackgroundSettingsController::class, 'updatePreference'])
        ->whereIn('context', ['admin', 'customer'])
        ->middleware('throttle:30,1')
        ->name('app-settings.login-background.preference.update');

    Route::post('/app-settings/login-backgrounds/{context}/upload', [LoginBackgroundSettingsController::class, 'upload'])
        ->whereIn('context', ['admin', 'customer'])
        ->middleware('throttle:20,1')
        ->name('app-settings.login-background.upload');

    Route::delete('/app-settings/login-backgrounds/{context}', [LoginBackgroundSettingsController::class, 'destroy'])
        ->whereIn('context', ['admin', 'customer'])
        ->middleware('throttle:30,1')
        ->name('app-settings.login-background.destroy');

    Route::post('/app-settings/financial', [AppSettingsController::class, 'updateFinancial'])
        ->middleware('throttle:20,1')
        ->name('app-settings.financial.update');

    Route::post('/app-settings/loans', [AppSettingsController::class, 'updateLoans'])
        ->middleware('throttle:20,1')
        ->name('app-settings.loans.update');

    Route::post('/app-settings/reports', [AppSettingsController::class, 'updateReports'])
        ->middleware('throttle:20,1')
        ->name('app-settings.reports.update');

    Route::post('/app-settings/print', [AppSettingsController::class, 'updatePrint'])
        ->middleware('throttle:20,1')
        ->name('app-settings.print.update');

    Route::post('/app-settings/security', [AppSettingsController::class, 'updateSecurity'])
        ->middleware('throttle:20,1')
        ->name('app-settings.security.update');

    Route::get('/app-settings/login-blocks', [AppSettingsController::class, 'loginBlocks'])
        ->middleware('throttle:60,1')
        ->name('app-settings.login-blocks.index');

    Route::post('/app-settings/login-blocks/{block}/unblock', [AppSettingsController::class, 'unblockLoginBlock'])
        ->middleware('throttle:30,1')
        ->name('app-settings.login-blocks.unblock');

    Route::get('/backups', [AdminDatabaseBackupController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('backups.index');

    Route::post('/backups/restore', [AdminDatabaseBackupController::class, 'restore'])
        ->middleware('throttle:admin-backup-restore')
        ->name('backups.restore');

    Route::post('/backups', [AdminDatabaseBackupController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('backups.store');

    Route::get('/backups/{backup}/download', [AdminDatabaseBackupController::class, 'download'])
        ->where('backup', 'backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.(sql|sqlite)')
        ->middleware('throttle:30,1')
        ->name('backups.download');

    Route::delete('/backups/{backup}', [AdminDatabaseBackupController::class, 'destroy'])
        ->where('backup', 'backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.(sql|sqlite)')
        ->middleware('throttle:30,1')
        ->name('backups.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('users.index');

    Route::post('/users', [AdminUserController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('users.store');

    Route::put('/users/{admin}', [AdminUserController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('users.update');

    Route::delete('/users/{admin}', [AdminUserController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('users.destroy');
});
