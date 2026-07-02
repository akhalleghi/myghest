<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesPortalAdminRecipientSmsSettings;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AdminPermissionService;
use App\Models\AppSetting;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Services\Sms\AdminLoginNotifySmsService;
use App\Services\Sms\CustomerDepositDeclarationNotifyAdminSmsService;
use App\Services\Sms\CustomerFullSettlementNotifyAdminSmsService;
use App\Services\Sms\CustomerInstallmentPaymentNotifyAdminSmsService;
use App\Services\Sms\CustomerLoanRequestNotifyAdminSmsService;
use App\Services\Sms\CustomerLoginNotifyAdminSmsService;
use App\Services\Sms\CustomerSupportTicketNotifyAdminSmsService;
use App\Services\Sms\SmsPanelManager;
use App\Support\IranMobile;
use App\Support\ListPerPage;
use App\Services\Sms\SmsSettingsService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SmsManagementController extends Controller
{
    use UpdatesPortalAdminRecipientSmsSettings;

    public function __construct(
        private readonly SmsPanelManager $panelManager,
        private readonly SmsSettingsService $smsSettings,
        private readonly AdminPermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $sessionTab = $request->session()->pull('sms_active_tab');
        $sessionTab = is_string($sessionTab) && $sessionTab !== '' ? $sessionTab : null;
        $hub = $this->permissions->resolveHubPage(
            $admin,
            'sms',
            $this->resolveSmsPreferredTab($request),
            $sessionTab,
        );
        $smsAllowedTabs = $hub['tabs'];
        $smsUiFeatures = $hub['features'];
        $smsActiveTab = $hub['active_tab'];

        $filters = $this->resolveFilters($request);
        $query = $this->buildFilteredQuery($filters['from'], $filters['to'], $filters['status'], $filters['search']);
        $providerOptions = $this->panelManager->providerOptions();
        $activeSetting = SmsPanelSetting::query()->where('is_active', true)->first();
        $activeProvider = $activeSetting?->provider ?? $this->resolveActiveProvider($providerOptions);
        $panelSetting = $activeSetting;
        $connectionState = $this->connectionStateFromSetting($panelSetting);
        $templateCategories = $this->templateCategories();
        $templatePatterns = $this->templatePatterns();
        $smsTemplates = SmsTemplate::query()->latest('id')->get();
        $scenarioTemplateIds = $this->smsSettings->scenarioTemplateIds();
        $smsReminderSettings = $this->smsSettings->reminderSettings();
        $loginNotifyService = app(AdminLoginNotifySmsService::class);
        $smsAdminLoginNotify = $this->smsSettings->adminLoginNotifySettings();
        $smsAdminLoginSelfNotify = $this->smsSettings->adminLoginSelfNotifySettings();
        $customerLoginNotifyService = app(CustomerLoginNotifyAdminSmsService::class);
        $smsCustomerLoginNotifyAdmin = $this->smsSettings->customerLoginNotifyAdminSettings();
        $adminLoginNotifyDefaultTemplate = $loginNotifyService->defaultMessageTemplate();
        $adminLoginSelfNotifyDefaultTemplate = $loginNotifyService->defaultSelfMessageTemplate();
        $customerLoginNotifyAdminDefaultTemplate = $customerLoginNotifyService->defaultMessageTemplate();
        $installmentPaymentNotifyService = app(CustomerInstallmentPaymentNotifyAdminSmsService::class);
        $smsCustomerInstallmentPaymentNotifyAdmin = $this->smsSettings->customerInstallmentPaymentNotifyAdminSettings();
        $customerInstallmentPaymentNotifyAdminDefaultTemplate = $installmentPaymentNotifyService->defaultMessageTemplate();
        $fullSettlementNotifyService = app(CustomerFullSettlementNotifyAdminSmsService::class);
        $depositNotifyService = app(CustomerDepositDeclarationNotifyAdminSmsService::class);
        $ticketNotifyService = app(CustomerSupportTicketNotifyAdminSmsService::class);
        $loanRequestNotifyService = app(CustomerLoanRequestNotifyAdminSmsService::class);
        $smsCustomerFullSettlementNotifyAdmin = $this->smsSettings->customerFullSettlementNotifyAdminSettings();
        $smsCustomerDepositDeclarationNotifyAdmin = $this->smsSettings->customerDepositDeclarationNotifyAdminSettings();
        $smsCustomerSupportTicketNotifyAdmin = $this->smsSettings->customerSupportTicketNotifyAdminSettings();
        $smsCustomerLoanRequestNotifyAdmin = $this->smsSettings->customerLoanRequestNotifyAdminSettings();
        $customerFullSettlementNotifyAdminDefaultTemplate = $fullSettlementNotifyService->defaultMessageTemplate();
        $customerDepositDeclarationNotifyAdminDefaultTemplate = $depositNotifyService->defaultMessageTemplate();
        $customerSupportTicketNotifyAdminDefaultTemplate = $ticketNotifyService->defaultMessageTemplate();
        $customerLoanRequestNotifyAdminDefaultTemplate = $loanRequestNotifyService->defaultMessageTemplate();

        $smsAdminPickerAdmins = Admin::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'name', 'username', 'mobile'])
            ->map(static fn (Admin $admin): array => [
                'id' => (int) $admin->id,
                'full_name' => $admin->fullName(),
                'username' => (string) $admin->username,
                'mobile' => (string) ($admin->mobile ?? ''),
                'mobile_valid' => IranMobile::isValid((string) ($admin->mobile ?? '')),
            ])
            ->values()
            ->all();

        $canSmsReports = isset($smsAllowedTabs['reports']);
        $logs = $canSmsReports
            ? $query->latest('sent_at')->paginate(ListPerPage::resolve($request))->withQueryString()
            : new LengthAwarePaginator([], 0, ListPerPage::resolve($request));

        return view('admin.sms.index', [
            'smsAllowedTabs' => $smsAllowedTabs,
            'smsActiveTab' => $smsActiveTab,
            'smsUiFeatures' => $smsUiFeatures,
            'logs' => $logs,
            'status' => $filters['status'],
            'search' => $filters['search'],
            'isRangeMode' => $filters['isRangeMode'],
            'selectedDate' => $filters['selectedDate'],
            'selectedDateJalali' => Jalali::instance($filters['selectedDate'])->format('Y/m/d'),
            'fromJDate' => $filters['fromJDate'] !== '' ? $filters['fromJDate'] : Jalali::instance($filters['from'])->format('Y/m/d'),
            'toJDate' => $filters['toJDate'] !== '' ? $filters['toJDate'] : Jalali::instance($filters['to'])->format('Y/m/d'),
            'prevDate' => $filters['selectedDate']->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $filters['selectedDate']->copy()->addDay()->format('Y-m-d'),
            'smsPanelProviders' => $providerOptions,
            'smsPanelSelectedProvider' => $activeProvider,
            'smsPanelUsername' => (string) ($panelSetting?->username ?? ''),
            'smsPanelSenderNumber' => (string) ($panelSetting?->sender_number ?? '50003300'),
            'smsPanelConnectionState' => $connectionState,
            'smsPanelLastConnectedAt' => $panelSetting?->last_connected_at,
            'smsTemplateCategories' => $templateCategories,
            'smsTemplatePatterns' => $templatePatterns,
            'smsTemplates' => $smsTemplates,
            'smsScenarioTemplateIds' => $scenarioTemplateIds,
            'smsReminderSettings' => $smsReminderSettings,
            'smsAdminLoginNotify' => $smsAdminLoginNotify,
            'smsAdminLoginSelfNotify' => $smsAdminLoginSelfNotify,
            'smsCustomerLoginNotifyAdmin' => $smsCustomerLoginNotifyAdmin,
            'smsAdminPickerAdmins' => $smsAdminPickerAdmins,
            'adminLoginNotifyDefaultTemplate' => $adminLoginNotifyDefaultTemplate,
            'adminLoginSelfNotifyDefaultTemplate' => $adminLoginSelfNotifyDefaultTemplate,
            'customerLoginNotifyAdminDefaultTemplate' => $customerLoginNotifyAdminDefaultTemplate,
            'smsCustomerInstallmentPaymentNotifyAdmin' => $smsCustomerInstallmentPaymentNotifyAdmin,
            'customerInstallmentPaymentNotifyAdminDefaultTemplate' => $customerInstallmentPaymentNotifyAdminDefaultTemplate,
            'smsCustomerFullSettlementNotifyAdmin' => $smsCustomerFullSettlementNotifyAdmin,
            'smsCustomerDepositDeclarationNotifyAdmin' => $smsCustomerDepositDeclarationNotifyAdmin,
            'smsCustomerSupportTicketNotifyAdmin' => $smsCustomerSupportTicketNotifyAdmin,
            'smsCustomerLoanRequestNotifyAdmin' => $smsCustomerLoanRequestNotifyAdmin,
            'customerFullSettlementNotifyAdminDefaultTemplate' => $customerFullSettlementNotifyAdminDefaultTemplate,
            'customerDepositDeclarationNotifyAdminDefaultTemplate' => $customerDepositDeclarationNotifyAdminDefaultTemplate,
            'customerSupportTicketNotifyAdminDefaultTemplate' => $customerSupportTicketNotifyAdminDefaultTemplate,
            'customerLoanRequestNotifyAdminDefaultTemplate' => $customerLoanRequestNotifyAdminDefaultTemplate,
            'appDisplayName' => $this->appDisplayName(),
        ]);
    }

    public function updateAdminLoginNotifySettings(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('admin_login_notify_enabled');

        $validated = $request->validate([
            'admin_login_notify_enabled' => ['nullable', 'boolean'],
            'recipient_admin_ids' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'array',
                'min:1',
            ],
            'recipient_admin_ids.*' => ['integer', 'exists:admins,id'],
            'admin_login_notify_message' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'string',
                'max:500',
            ],
        ], [], [
            'admin_login_notify_enabled' => 'ارسال پیامک ورود ادمین',
            'recipient_admin_ids' => 'دریافت‌کنندگان',
            'recipient_admin_ids.*' => 'شناسه ادمین',
            'admin_login_notify_message' => 'متن پیامک',
        ]);

        $recipientIds = [];
        if ($enabled) {
            $rawIds = $validated['recipient_admin_ids'] ?? [];
            $activeAdmins = Admin::query()
                ->whereIn('id', $rawIds)
                ->where('is_active', true)
                ->get(['id', 'mobile']);

            $recipientIds = $activeAdmins
                ->map(static fn (Admin $admin): int => (int) $admin->id)
                ->values()
                ->all();

            $hasDeliverableMobile = $activeAdmins->contains(
                static fn (Admin $admin): bool => IranMobile::isValid((string) ($admin->mobile ?? ''))
            );

            if ($recipientIds === [] || ! $hasDeliverableMobile) {
                return back()
                    ->withInput()
                    ->withErrors(['recipient_admin_ids' => 'حداقل یک ادمین فعال با شماره موبایل معتبر انتخاب کنید.'])
                    ->with('sms_active_tab', 'settings');
            }
        }

        $message = strip_tags(trim((string) ($validated['admin_login_notify_message'] ?? '')));
        if ($enabled && $message === '') {
            return back()
                ->withInput()
                ->withErrors(['admin_login_notify_message' => 'متن پیامک را وارد کنید.'])
                ->with('sms_active_tab', 'settings');
        }

        if (! $enabled) {
            $existing = $this->smsSettings->adminLoginNotifySettings();
            $recipientIds = $existing['recipient_ids'];
            $message = $existing['message_template'] !== ''
                ? $existing['message_template']
                : app(AdminLoginNotifySmsService::class)->defaultMessageTemplate();
        }

        $this->smsSettings->saveAdminLoginNotifySettings([
            'enabled' => $enabled ? '1' : '0',
            'recipient_ids' => $recipientIds,
            'message_template' => $message,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'تنظیمات اعلان ورود به مدیران ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function updateAdminLoginSelfNotifySettings(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('admin_login_self_notify_enabled');

        $validated = $request->validate([
            'admin_login_self_notify_enabled' => ['nullable', 'boolean'],
            'admin_login_self_notify_message' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'string',
                'max:500',
            ],
        ], [], [
            'admin_login_self_notify_enabled' => 'پیامک ورود به خود ادمین',
            'admin_login_self_notify_message' => 'متن پیامک',
        ]);

        $message = strip_tags(trim((string) ($validated['admin_login_self_notify_message'] ?? '')));
        if ($enabled && $message === '') {
            return back()
                ->withInput()
                ->withErrors(['admin_login_self_notify_message' => 'متن پیامک را وارد کنید.'])
                ->with('sms_active_tab', 'settings');
        }

        if (! $enabled) {
            $existing = $this->smsSettings->adminLoginSelfNotifySettings();
            $message = $existing['message_template'] !== ''
                ? $existing['message_template']
                : app(AdminLoginNotifySmsService::class)->defaultSelfMessageTemplate();
        }

        $this->smsSettings->saveAdminLoginSelfNotifySettings([
            'enabled' => $enabled ? '1' : '0',
            'message_template' => $message,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'تنظیمات پیامک ورود به خود ادمین ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function updateCustomerLoginNotifyAdminSettings(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('customer_login_notify_admin_enabled');

        $validated = $request->validate([
            'customer_login_notify_admin_enabled' => ['nullable', 'boolean'],
            'customer_login_recipient_admin_ids' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'array',
                'min:1',
            ],
            'customer_login_recipient_admin_ids.*' => ['integer', 'exists:admins,id'],
            'customer_login_notify_admin_message' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'string',
                'max:500',
            ],
        ], [], [
            'customer_login_notify_admin_enabled' => 'ارسال پیامک ورود مشتری برای ادمین',
            'customer_login_recipient_admin_ids' => 'دریافت‌کنندگان',
            'customer_login_recipient_admin_ids.*' => 'شناسه ادمین',
            'customer_login_notify_admin_message' => 'متن پیامک',
        ]);

        $recipientIds = [];
        if ($enabled) {
            $rawIds = $validated['customer_login_recipient_admin_ids'] ?? [];
            $activeAdmins = Admin::query()
                ->whereIn('id', $rawIds)
                ->where('is_active', true)
                ->get(['id', 'mobile']);

            $recipientIds = $activeAdmins
                ->map(static fn (Admin $admin): int => (int) $admin->id)
                ->values()
                ->all();

            $hasDeliverableMobile = $activeAdmins->contains(
                static fn (Admin $admin): bool => IranMobile::isValid((string) ($admin->mobile ?? ''))
            );

            if ($recipientIds === [] || ! $hasDeliverableMobile) {
                return back()
                    ->withInput()
                    ->withErrors(['customer_login_recipient_admin_ids' => 'حداقل یک ادمین فعال با شماره موبایل معتبر انتخاب کنید.'])
                    ->with('sms_active_tab', 'settings');
            }
        }

        $message = strip_tags(trim((string) ($validated['customer_login_notify_admin_message'] ?? '')));
        if ($enabled && $message === '') {
            return back()
                ->withInput()
                ->withErrors(['customer_login_notify_admin_message' => 'متن پیامک را وارد کنید.'])
                ->with('sms_active_tab', 'settings');
        }

        if (! $enabled) {
            $existing = $this->smsSettings->customerLoginNotifyAdminSettings();
            $recipientIds = $existing['recipient_ids'];
            $message = $existing['message_template'] !== ''
                ? $existing['message_template']
                : app(CustomerLoginNotifyAdminSmsService::class)->defaultMessageTemplate();
        }

        $this->smsSettings->saveCustomerLoginNotifyAdminSettings([
            'enabled' => $enabled ? '1' : '0',
            'recipient_ids' => $recipientIds,
            'message_template' => $message,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'تنظیمات پیامک ورود مشتری برای ادمین ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function updateCustomerInstallmentPaymentNotifyAdminSettings(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('customer_installment_payment_notify_admin_enabled');

        $validated = $request->validate([
            'customer_installment_payment_notify_admin_enabled' => ['nullable', 'boolean'],
            'customer_installment_payment_recipient_admin_ids' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'array',
                'min:1',
            ],
            'customer_installment_payment_recipient_admin_ids.*' => ['integer', 'exists:admins,id'],
            'customer_installment_payment_notify_admin_message' => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'string',
                'max:500',
            ],
        ], [], [
            'customer_installment_payment_notify_admin_enabled' => 'ارسال پیامک واریزی قسط مشتری',
            'customer_installment_payment_recipient_admin_ids' => 'دریافت‌کنندگان',
            'customer_installment_payment_recipient_admin_ids.*' => 'شناسه ادمین',
            'customer_installment_payment_notify_admin_message' => 'متن پیامک',
        ]);

        $recipientIds = [];
        if ($enabled) {
            $rawIds = $validated['customer_installment_payment_recipient_admin_ids'] ?? [];
            $activeAdmins = Admin::query()
                ->whereIn('id', $rawIds)
                ->where('is_active', true)
                ->get(['id', 'mobile']);

            $recipientIds = $activeAdmins
                ->map(static fn (Admin $admin): int => (int) $admin->id)
                ->values()
                ->all();

            $hasDeliverableMobile = $activeAdmins->contains(
                static fn (Admin $admin): bool => IranMobile::isValid((string) ($admin->mobile ?? ''))
            );

            if ($recipientIds === [] || ! $hasDeliverableMobile) {
                return back()
                    ->withInput()
                    ->withErrors(['customer_installment_payment_recipient_admin_ids' => 'حداقل یک ادمین فعال با شماره موبایل معتبر انتخاب کنید.'])
                    ->with('sms_active_tab', 'settings');
            }
        }

        $message = strip_tags(trim((string) ($validated['customer_installment_payment_notify_admin_message'] ?? '')));
        if ($enabled && $message === '') {
            return back()
                ->withInput()
                ->withErrors(['customer_installment_payment_notify_admin_message' => 'متن پیامک را وارد کنید.'])
                ->with('sms_active_tab', 'settings');
        }

        if (! $enabled) {
            $existing = $this->smsSettings->customerInstallmentPaymentNotifyAdminSettings();
            $recipientIds = $existing['recipient_ids'];
            $message = $existing['message_template'] !== ''
                ? $existing['message_template']
                : app(CustomerInstallmentPaymentNotifyAdminSmsService::class)->defaultMessageTemplate();
        }

        $this->smsSettings->saveCustomerInstallmentPaymentNotifyAdminSettings([
            'enabled' => $enabled ? '1' : '0',
            'recipient_ids' => $recipientIds,
            'message_template' => $message,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'تنظیمات پیامک واریزی قسط مشتری ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function updateCustomerFullSettlementNotifyAdminSettings(Request $request): RedirectResponse
    {
        return $this->updatePortalAdminRecipientSmsSettings(
            $request,
            $this->smsSettings,
            'customer_full_settlement_notify_admin_enabled',
            'customer_full_settlement_recipient_admin_ids',
            'customer_full_settlement_notify_admin_message',
            'ارسال پیامک تسویه یکجای وام',
            fn (): array => $this->smsSettings->customerFullSettlementNotifyAdminSettings(),
            fn (array $values) => $this->smsSettings->saveCustomerFullSettlementNotifyAdminSettings($values),
            fn (): string => app(CustomerFullSettlementNotifyAdminSmsService::class)->defaultMessageTemplate(),
            'تنظیمات پیامک تسویه یکجای وام ذخیره شد.',
        );
    }

    public function updateCustomerDepositDeclarationNotifyAdminSettings(Request $request): RedirectResponse
    {
        return $this->updatePortalAdminRecipientSmsSettings(
            $request,
            $this->smsSettings,
            'customer_deposit_declaration_notify_admin_enabled',
            'customer_deposit_declaration_recipient_admin_ids',
            'customer_deposit_declaration_notify_admin_message',
            'ارسال پیامک اعلام واریزی مشتری',
            fn (): array => $this->smsSettings->customerDepositDeclarationNotifyAdminSettings(),
            fn (array $values) => $this->smsSettings->saveCustomerDepositDeclarationNotifyAdminSettings($values),
            fn (): string => app(CustomerDepositDeclarationNotifyAdminSmsService::class)->defaultMessageTemplate(),
            'تنظیمات پیامک اعلام واریزی مشتری ذخیره شد.',
        );
    }

    public function updateCustomerSupportTicketNotifyAdminSettings(Request $request): RedirectResponse
    {
        return $this->updatePortalAdminRecipientSmsSettings(
            $request,
            $this->smsSettings,
            'customer_support_ticket_notify_admin_enabled',
            'customer_support_ticket_recipient_admin_ids',
            'customer_support_ticket_notify_admin_message',
            'ارسال پیامک ثبت تیکت مشتری',
            fn (): array => $this->smsSettings->customerSupportTicketNotifyAdminSettings(),
            fn (array $values) => $this->smsSettings->saveCustomerSupportTicketNotifyAdminSettings($values),
            fn (): string => app(CustomerSupportTicketNotifyAdminSmsService::class)->defaultMessageTemplate(),
            'تنظیمات پیامک ثبت تیکت مشتری ذخیره شد.',
        );
    }

    public function updateCustomerLoanRequestNotifyAdminSettings(Request $request): RedirectResponse
    {
        return $this->updatePortalAdminRecipientSmsSettings(
            $request,
            $this->smsSettings,
            'customer_loan_request_notify_admin_enabled',
            'customer_loan_request_recipient_admin_ids',
            'customer_loan_request_notify_admin_message',
            'ارسال پیامک ثبت درخواست وام',
            fn (): array => $this->smsSettings->customerLoanRequestNotifyAdminSettings(),
            fn (array $values) => $this->smsSettings->saveCustomerLoanRequestNotifyAdminSettings($values),
            fn (): string => app(CustomerLoanRequestNotifyAdminSmsService::class)->defaultMessageTemplate(),
            'تنظیمات پیامک ثبت درخواست وام ذخیره شد.',
        );
    }

    private function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : 'سامانه';
    }

    public function updatePanelSettings(Request $request): RedirectResponse
    {
        $providerOptions = $this->panelManager->providerOptions();
        $providerKeys = array_keys($providerOptions);
        $activeProvider = $this->resolveActiveProvider($providerOptions);

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($providerKeys)],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'sender_number' => ['required', 'string', 'max:32', 'regex:/^[0-9]{5,20}$/'],
        ], [], [
            'provider' => 'پنل پیامک',
            'username' => 'نام کاربری',
            'password' => 'رمز عبور',
            'sender_number' => 'شماره فرستنده',
        ]);

        $provider = (string) $validated['provider'];
        $username = trim((string) $validated['username']);
        $passwordInput = trim((string) ($validated['password'] ?? ''));
        $senderNumber = trim((string) $validated['sender_number']);
        $setting = SmsPanelSetting::query()->firstOrNew(['provider' => $provider]);

        if ($passwordInput === '' && empty($setting->password)) {
            return back()
                ->withInput()
                ->withErrors(['password' => 'برای اولین اتصال، وارد کردن رمز عبور الزامی است.']);
        }

        $plainPassword = $passwordInput !== '' ? $passwordInput : $this->decryptPasswordOrEmpty((string) $setting->password);
        if ($plainPassword === '') {
            return back()
                ->withInput()
                ->withErrors(['password' => 'رمز عبور فعلی معتبر نیست. لطفاً مجدداً رمز عبور را وارد کنید.']);
        }

        $gateway = $this->panelManager->gateway($provider);
        $connection = $gateway->testConnection($username, $plainPassword, [
            'domain_name' => (string) ($setting->domain_name ?: 'sepahansms'),
        ]);

        SmsPanelSetting::query()->where('is_active', true)->update(['is_active' => false]);

        $setting->provider = $provider;
        $setting->username = $username;
        $setting->is_active = true;
        $setting->domain_name = (string) ($setting->domain_name ?: 'sepahansms');
        $setting->sender_number = $senderNumber;
        $setting->password = $passwordInput !== '' ? Crypt::encryptString($passwordInput) : $setting->password;
        $setting->last_connection_status = $connection->ok ? 'connected' : 'disconnected';
        $setting->last_connection_message = $connection->message;
        $setting->last_connected_at = now();
        $setting->save();

        if ($provider !== $activeProvider) {
            SmsPanelSetting::query()
                ->where('provider', '!=', $provider)
                ->update(['is_active' => false]);
        }

        return redirect()
            ->route('admin.sms.index')
            ->with($connection->ok ? 'flash_success' : 'flash_error', $connection->message)
            ->with('sms_active_tab', 'settings');
    }

    public function updateScenarioTemplates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tpl_installment_thanks_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'tpl_login_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'tpl_register_verify_code_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'tpl_register_welcome_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ], [], [
            'tpl_installment_thanks_id' => 'قالب پیامک ثبت قسط و تشکر',
            'tpl_login_id' => 'قالب پیامک ورود به سیستم',
            'tpl_register_verify_code_id' => 'قالب پیامک رمز تاییدیه ثبت نام',
            'tpl_register_welcome_id' => 'قالب پیامک خوش آمد ثبت نام',
        ]);

        $this->smsSettings->saveScenarioTemplateIds([
            'tpl_installment_thanks_id' => $validated['tpl_installment_thanks_id'] ?? null,
            'tpl_login_id' => $validated['tpl_login_id'] ?? null,
            'tpl_register_verify_code_id' => $validated['tpl_register_verify_code_id'] ?? null,
            'tpl_register_welcome_id' => $validated['tpl_register_welcome_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'الگوهای پیش‌فرض پیامک با موفقیت ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function updateReminderSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_send_time' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled')),
                'nullable',
                'date_format:H:i',
            ],
            'due_day_enabled' => ['nullable', 'boolean'],
            'due_day_template_id' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled') && $request->boolean('due_day_enabled')),
                'nullable',
                'integer',
                'exists:sms_templates,id',
            ],
            'before_due_enabled' => ['nullable', 'boolean'],
            'before_due_template_id' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled') && $request->boolean('before_due_enabled')),
                'nullable',
                'integer',
                'exists:sms_templates,id',
            ],
            'before_due_days' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled') && $request->boolean('before_due_enabled')),
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
            'overdue_days_after' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled')),
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            'overdue_daily_until_paid' => ['nullable', 'boolean'],
            'overdue_template_id' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled')),
                'nullable',
                'integer',
                'exists:sms_templates,id',
            ],
        ], [], [
            'reminder_enabled' => 'فعال‌سازی پیامک‌های یادآوری',
            'reminder_send_time' => 'ساعت ارسال پیامک',
            'due_day_enabled' => 'ارسال یادآوری روز سررسید',
            'due_day_template_id' => 'قالب پیامک روز سررسید',
            'before_due_enabled' => 'ارسال یادآوری پیش از موعد',
            'before_due_template_id' => 'قالب پیامک سررسید پیش از موعد',
            'before_due_days' => 'تعداد روز قبل از سررسید',
            'overdue_days_after' => 'تعداد روز پس از سررسید برای شروع معوق',
            'overdue_daily_until_paid' => 'ارسال روزانه تا زمان وصول',
            'overdue_template_id' => 'قالب پیامک اقساط معوق',
        ]);

        $this->smsSettings->saveReminderSettings([
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            'reminder_send_time' => trim((string) ($validated['reminder_send_time'] ?? '')),
            'due_day_enabled' => $request->boolean('due_day_enabled'),
            'due_day_template_id' => $validated['due_day_template_id'] ?? null,
            'before_due_enabled' => $request->boolean('before_due_enabled'),
            'before_due_template_id' => $validated['before_due_template_id'] ?? null,
            'before_due_days' => $validated['before_due_days'] ?? null,
            'overdue_days_after' => $validated['overdue_days_after'] ?? null,
            'overdue_daily_until_paid' => $request->boolean('overdue_daily_until_paid'),
            'overdue_template_id' => $validated['overdue_template_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'تنظیمات پیامک یادآوری با موفقیت ذخیره شد.')
            ->with('sms_active_tab', 'settings');
    }

    public function sendPanelTest(Request $request): RedirectResponse
    {
        $providerOptions = $this->panelManager->providerOptions();
        $setting = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($setting === null) {
            return redirect()
                ->route('admin.sms.index')
                ->with('flash_error', 'ابتدا تنظیمات پنل را ذخیره کنید.')
                ->with('sms_active_tab', 'settings');
        }
        $activeProvider = $setting->provider;

        $validated = $request->validate([
            'test_recipient' => ['required', 'regex:/^09\d{9}$/'],
            'test_message' => ['required', 'string', 'max:500'],
        ], [], [
            'test_recipient' => 'شماره تماس',
            'test_message' => 'متن پیام',
        ]);

        $password = $this->decryptPasswordOrEmpty((string) $setting->password);
        if ($password === '') {
            return redirect()
                ->route('admin.sms.index')
                ->with('flash_error', 'رمز عبور پنل معتبر نیست. لطفاً دوباره ذخیره کنید.')
                ->with('sms_active_tab', 'settings');
        }

        $gateway = $this->panelManager->gateway($activeProvider);
        $recipient = trim((string) $validated['test_recipient']);
        $message = trim((string) $validated['test_message']);
        $result = $gateway->sendTestMessage(
            (string) $setting->username,
            $password,
            $recipient,
            $message,
            [
                'domain_name' => (string) ($setting->domain_name ?: 'sepahansms'),
                'sender_number' => (string) ($setting->sender_number ?: '50003300'),
            ]
        );

        $setting->last_connection_status = $result->ok ? 'connected' : 'disconnected';
        $setting->last_connection_message = $result->message;
        $setting->last_connected_at = now();
        $setting->save();

        SmsLog::query()->create([
            'sms_panel' => (string) ($providerOptions[$activeProvider] ?? $activeProvider),
            'status' => $result->ok ? SmsLog::STATUS_DELIVERED : SmsLog::STATUS_UNDELIVERED,
            'sent_at' => now(),
            'message_text' => $message,
            'recipient' => $recipient,
            'type' => 'panel-test',
            'cost' => 0,
            'meta' => [
                'provider' => $activeProvider,
                'response_code' => $result->code,
                'result_message' => $result->message,
            ],
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with($result->ok ? 'flash_success' : 'flash_error', $result->message)
            ->with('sms_active_tab', 'settings');
    }

    public function destroyLog(SmsLog $smsLog): RedirectResponse
    {
        $smsLog->delete();

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'رکورد پیامک با موفقیت حذف شد.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validatedPayload = $this->validateTemplatePayload($request);
        if ($validatedPayload['has_errors']) {
            return back()->withErrors($validatedPayload['errors'])->withInput()->with('sms_active_tab', 'templates');
        }

        SmsTemplate::query()->create([
            'title' => $validatedPayload['title'],
            'category' => $validatedPayload['category'],
            'body' => $validatedPayload['body'],
            'placeholders' => $validatedPayload['tokens'],
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'قالب پیامک با موفقیت ثبت شد.')
            ->with('sms_active_tab', 'templates');
    }

    public function updateTemplate(Request $request, SmsTemplate $smsTemplate): RedirectResponse
    {
        $validatedPayload = $this->validateTemplatePayload($request);
        if ($validatedPayload['has_errors']) {
            return back()->withErrors($validatedPayload['errors'])->withInput()->with('sms_active_tab', 'templates');
        }

        $smsTemplate->update([
            'title' => $validatedPayload['title'],
            'category' => $validatedPayload['category'],
            'body' => $validatedPayload['body'],
            'placeholders' => $validatedPayload['tokens'],
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'قالب پیامک با موفقیت ویرایش شد.')
            ->with('sms_active_tab', 'templates');
    }

    public function destroyTemplate(SmsTemplate $smsTemplate): RedirectResponse
    {
        if ($smsTemplate->is_system) {
            return redirect()
                ->route('admin.sms.index')
                ->with('flash_error', 'قالب‌های پیش‌فرض سیستم قابل حذف نیستند.')
                ->with('sms_active_tab', 'templates');
        }

        $smsTemplate->delete();

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', 'قالب پیامک حذف شد.')
            ->with('sms_active_tab', 'templates');
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $rows = $this->buildFilteredQuery($filters['from'], $filters['to'], $filters['status'], $filters['search'])
            ->latest('sent_at')
            ->get();

        $filename = 'sms-logs-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            // UTF-16LE BOM for proper Persian display in Excel on Windows.
            fwrite($out, "\xFF\xFE");

            $this->writeExcelUnicodeRow($out, [
                'پنل پیامک',
                'وضعیت',
                'زمان ارسال',
                'متن پیام',
                'دریافت کننده',
                'نوع',
                'هزینه',
            ]);

            foreach ($rows as $log) {
                $sentAt = $log->sent_at ? jalali($log->sent_at)->format('Y/m/d H:i') : '';

                $this->writeExcelUnicodeRow($out, [
                    (string) ($log->sms_panel ?? ''),
                    $log->statusLabel(),
                    $sentAt,
                    (string) ($log->message_text ?? ''),
                    (string) ($log->recipient ?? ''),
                    (string) ($log->type ?? ''),
                    number_format((float) $log->cost, 0, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * @return array{
     *   status:string,
     *   search:string,
     *   isRangeMode:bool,
     *   fromJDate:string,
     *   toJDate:string,
     *   selectedDate:Carbon,
     *   from:Carbon,
     *   to:Carbon
     * }
     */
    private function resolveFilters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $mode = (string) $request->query('mode', 'day');

        $allowedStatuses = [
            SmsLog::STATUS_PENDING,
            SmsLog::STATUS_DELIVERED,
            SmsLog::STATUS_UNDELIVERED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $isRangeMode = false;
        $fromJDate = (string) $request->query('from_jdate', '');
        $toJDate = (string) $request->query('to_jdate', '');
        $selectedDate = Carbon::today();

        if ($mode === 'range') {
            $from = $this->parseJalaliDate($fromJDate);
            $to = $this->parseJalaliDate($toJDate);

            if ($from !== null && $to !== null) {
                $isRangeMode = true;
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        if (! $isRangeMode) {
            $rawDate = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
            $parsedDate = Carbon::createFromFormat('Y-m-d', $rawDate);
            $selectedDate = $parsedDate ?: Carbon::today();
            $from = $selectedDate->copy()->startOfDay();
            $to = $selectedDate->copy()->endOfDay();
        }

        return [
            'status' => $status,
            'search' => $search,
            'isRangeMode' => $isRangeMode,
            'fromJDate' => $fromJDate,
            'toJDate' => $toJDate,
            'selectedDate' => $selectedDate,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function buildFilteredQuery(Carbon $from, Carbon $to, string $status, string $search): Builder
    {
        return SmsLog::query()
            ->whereBetween('sent_at', [$from, $to])
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('sms_panel', 'like', "%$search%")
                        ->orWhere('message_text', 'like', "%$search%")
                        ->orWhere('recipient', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%");
                });
            });
    }

    /**
     * @param  resource  $out
     * @param  array<int, string>  $cells
     */
    private function writeExcelUnicodeRow($out, array $cells): void
    {
        $cleanCells = array_map(static function (string $value): string {
            return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);
        }, $cells);

        $line = implode("\t", $cleanCells)."\r\n";
        fwrite($out, mb_convert_encoding($line, 'UTF-16LE', 'UTF-8'));
    }

    private function parseJalaliDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        try {
            $j = Jalali::parseFormat('Y/m/d', $value);

            return Carbon::createFromTimestamp($j->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $providerOptions
     */
    private function resolveActiveProvider(array $providerOptions): string
    {
        $active = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($active !== null && isset($providerOptions[$active->provider])) {
            return $active->provider;
        }

        return (string) array_key_first($providerOptions);
    }

    /**
     * @return array{state:string,label:string,message:string}
     */
    private function connectionStateFromSetting(?SmsPanelSetting $setting): array
    {
        if ($setting === null || ! $setting->is_active || $setting->username === null || $setting->username === '') {
            return [
                'state' => 'not-configured',
                'label' => 'تنظیم نشده',
                'message' => 'پنل فعالی انتخاب نشده است. ابتدا تنظیمات پنل را ذخیره کنید.',
            ];
        }

        if ($setting->last_connection_status === 'connected') {
            return [
                'state' => 'connected',
                'label' => 'متصل',
                'message' => (string) ($setting->last_connection_message ?: 'اتصال برقرار است.'),
            ];
        }

        return [
            'state' => 'disconnected',
            'label' => 'ناموفق',
            'message' => (string) ($setting->last_connection_message ?: 'اتصال برقرار نیست.'),
        ];
    }

    private function decryptPasswordOrEmpty(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, string>
     */
    private function templateCategories(): array
    {
        return [
            'installment' => 'قسط',
            'status_change' => 'تغییر وضعیت',
            'loan_request_status' => 'درخواست وام (وضعیت)',
            'panel_login' => 'ورود به پنل',
            'registration' => 'ثبت نام',
            'registration_verification_code' => 'کد تاییدیه ثبت نام',
            'wallet_charge' => 'شارژ کیف پول',
            'new_order' => 'ثبت سفارش جدید',
            'file_registration' => 'ثبت پرونده',
            'wallet_withdrawal' => 'برداشت از کیف پول',
            'invoice_payment_link' => 'لینک واریز صورتحساب الکترونیکی',
            'contract_link' => 'لینک قرارداد',
            'contract_verification_code' => 'کد تایدییه قرارداد',
            'contract_registered_notice' => 'اطلاعیه ثبت موفق قرارداد',
            'guarantor_otp' => 'احراز هویت ضامن',
            'loan_creation_otp' => 'تایید ایجاد پرونده وام',
            'guarantee_return_otp' => 'تایید عودت ضمانت',
        ];
    }

    /**
     * @return array<string, array{label:string,sample:string}>
     */
    private function templatePatterns(): array
    {
        return [
            'store_name' => ['label' => 'نام فروشگاه', 'sample' => 'فروشگاه مهر'],
            'customer_name' => ['label' => 'نام مشتری', 'sample' => 'علی رضایی'],
            'installment_number' => ['label' => 'شماره قسط', 'sample' => '5'],
            'installment_amount' => ['label' => 'مبلغ قسط', 'sample' => '1,500,000 تومان'],
            'paid_amount' => ['label' => 'مبلغ واریز', 'sample' => '600,000 تومان'],
            'payment_date' => ['label' => 'تاریخ واریز', 'sample' => '1405/02/22'],
            'days_until_due' => ['label' => 'تعداد روز تا سررسید', 'sample' => '2'],
            'remaining_loan' => ['label' => 'مانده وام', 'sample' => '12,400,000 تومان'],
            'payment_link' => ['label' => 'لینک پرداخت', 'sample' => 'https://pay.example.com/i/452'],
            'payment_link_variable' => ['label' => 'بخش متغیر لینک پرداخت', 'sample' => 'i/452'],
            'transaction_tracking_code' => ['label' => 'شماره پیگیری تراکنش', 'sample' => 'TRX-904521'],
            'guarantor_name' => ['label' => 'نام و نام خانوادگی ضامن', 'sample' => 'محمد احمدی'],
            'borrower_name' => ['label' => 'نام مشتری وام‌گیرنده', 'sample' => 'زهرا کریمی'],
            'app_name' => ['label' => 'نام نمایشی سامانه', 'sample' => 'سامانه میهمان'],
            'loan_request_status_title' => ['label' => 'عنوان وضعیت درخواست وام', 'sample' => 'تکمیل مدارک'],
            'code' => ['label' => 'کد تأیید پیامکی', 'sample' => '847392'],
            'guarantee_type_label' => ['label' => 'نوع ضمانت', 'sample' => 'چک'],
        ];
    }

    /**
     * @return list<string>
     */
    private function extractTemplateTokens(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);
        $tokens = array_map(static fn (string $token): string => strtolower(trim($token)), $matches[1] ?? []);

        return array_values(array_unique($tokens));
    }

    /**
     * @return array{
     *   has_errors:bool,
     *   errors:array<string,string>,
     *   title:string,
     *   category:string,
     *   body:string,
     *   tokens:list<string>
     * }
     */
    private function validateTemplatePayload(Request $request): array
    {
        $categories = $this->templateCategories();
        $patterns = $this->templatePatterns();
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', Rule::in(array_keys($categories))],
            'body' => ['required', 'string', 'max:1000'],
        ], [], [
            'title' => 'عنوان قالب',
            'category' => 'دسته قالب',
            'body' => 'الگوی قالب',
        ]);

        if ($validator->fails()) {
            /** @var array<string, string> $messages */
            $messages = $validator->errors()->toArray();

            return [
                'has_errors' => true,
                'errors' => array_map(static fn (array $m): string => (string) ($m[0] ?? ''), $messages),
                'title' => '',
                'category' => '',
                'body' => '',
                'tokens' => [],
            ];
        }

        $validated = $validator->validated();
        $body = trim((string) $validated['body']);
        $tokens = $this->extractTemplateTokens($body);
        $allowedTokens = array_keys($patterns);
        $unknownTokens = array_values(array_diff($tokens, $allowedTokens));
        if ($unknownTokens !== []) {
            return [
                'has_errors' => true,
                'errors' => [
                    'body' => 'این متغیرها پشتیبانی نمی‌شوند: '.implode('، ', array_map(static fn (string $token): string => '{{'.$token.'}}', $unknownTokens)),
                ],
                'title' => '',
                'category' => '',
                'body' => '',
                'tokens' => [],
            ];
        }

        return [
            'has_errors' => false,
            'errors' => [],
            'title' => trim((string) $validated['title']),
            'category' => (string) $validated['category'],
            'body' => $body,
            'tokens' => $tokens,
        ];
    }

    private function resolveSmsPreferredTab(Request $request): ?string
    {
        if (
            $request->old('title') !== null
            || $request->old('category') !== null
            || $request->old('body') !== null
        ) {
            return 'templates';
        }

        $settingsKeys = [
            'provider', 'username', 'sender_number', 'password', 'test_recipient', 'test_message',
            'tpl_installment_thanks_id', 'tpl_login_id', 'reminder_enabled', 'admin_login_notify_enabled',
        ];
        foreach ($settingsKeys as $key) {
            if ($request->old($key) !== null) {
                return 'settings';
            }
        }

        return null;
    }
}
