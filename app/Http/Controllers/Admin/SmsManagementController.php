<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesPortalAdminRecipientSmsSettings;
use App\Http\Controllers\Controller;
use App\Jobs\SendSmsFreeSendCampaignBatchJob;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\SmsFreeSendCampaign;
use App\Models\SmsFreeSendCampaignRecipient;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\AdminLoginNotifySmsService;
use App\Services\Sms\CustomerDepositDeclarationNotifyAdminSmsService;
use App\Services\Sms\CustomerFullSettlementNotifyAdminSmsService;
use App\Services\Sms\CustomerInstallmentPaymentNotifyAdminSmsService;
use App\Services\Sms\CustomerLoanRequestNotifyAdminSmsService;
use App\Services\Sms\CustomerLoginNotifyAdminSmsService;
use App\Services\Sms\CustomerSupportTicketNotifyAdminSmsService;
use App\Services\Sms\SmsFreeSendAudienceResolver;
use App\Services\Sms\SmsFreeSendExcelImporter;
use App\Services\Sms\SmsPanelCreditService;
use App\Services\Sms\SmsPanelManager;
use App\Services\Sms\SmsSettingsService;
use App\Services\Sms\SmsTemplateRenderer;
use App\Support\IranMobile;
use App\Support\ListPerPage;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SmsManagementController extends Controller
{
    use UpdatesPortalAdminRecipientSmsSettings;

    private const FREE_SEND_MAX_RECIPIENTS = 30;

    public function __construct(
        private readonly SmsPanelManager $panelManager,
        private readonly SmsSettingsService $smsSettings,
        private readonly AdminPermissionService $permissions,
        private readonly SmsPanelCreditService $creditService,
        private readonly RawSmsDispatcher $rawSms,
        private readonly SmsFreeSendAudienceResolver $freeSendAudience,
        private readonly SmsFreeSendExcelImporter $freeSendExcelImporter,
    ) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $sessionTab = $request->session()->pull('sms_active_tab');
        $sessionTab = is_string($sessionTab) && $sessionTab !== '' ? $sessionTab : null;
        $campaignId = (int) $request->session()->pull('sms_free_send_campaign_id', 0);
        $smsFreeSendCampaign = $campaignId > 0
            ? SmsFreeSendCampaign::query()
                ->whereKey($campaignId)
                ->where('created_by_admin_id', $admin->id)
                ->first()
            : null;
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
        $freeSmsTemplates = $smsTemplates->where('category', 'free_send')->values();
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
            'smsPanelApiTokenStatus' => $this->creditService->activePanelTokenStatus(),
            'smsTemplateCategories' => $templateCategories,
            'smsTemplatePatterns' => $templatePatterns,
            'smsTemplates' => $smsTemplates,
            'freeSmsTemplates' => $freeSmsTemplates,
            'smsFreeSendCampaign' => $smsFreeSendCampaign,
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

        $previousUsername = trim((string) ($setting->username ?? ''));
        $usernameChanged = $previousUsername !== $username;

        $setting->provider = $provider;
        $setting->username = $username;
        $setting->is_active = true;
        $setting->domain_name = (string) ($setting->domain_name ?: 'sepahansms');
        $setting->sender_number = $senderNumber;
        $setting->password = $passwordInput !== '' ? Crypt::encryptString($passwordInput) : $setting->password;
        if ($usernameChanged) {
            $setting->api_token = null;
        }
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

    public function panelCredit(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        if (! $this->permissions->canAccessUiFeature($admin, 'sms', 'credit.view')) {
            abort(403, 'شما به مشاهده اعتبار پنل پیامک دسترسی ندارید.');
        }

        $result = $this->creditService->fetchActivePanelCredit();
        $credit = $result->credit;
        $creditFa = $credit !== null
            ? Jalali::enToFaNumbers(number_format($credit, 0, '.', ','))
            : null;

        return response()->json([
            'ok' => $result->ok,
            'message' => $result->message,
            'credit' => $credit,
            'credit_fa' => $creditFa,
            'unit' => 'ریال',
            'checked_at' => now()->toIso8601String(),
            'checked_at_fa' => Jalali::enToFaNumbers(jalali(now())->format('Y/m/d H:i:s')),
            'token_status' => $this->creditService->activePanelTokenStatus(),
        ], $result->ok ? 200 : 422);
    }

    public function updatePanelApiToken(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        if (! $this->permissions->canAccessUiFeature($admin, 'sms', 'credit.view')) {
            abort(403, 'شما به تنظیم توکن پنل پیامک دسترسی ندارید.');
        }

        $validated = $request->validate([
            'api_token' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [], [
            'api_token' => 'توکن WebAPI',
        ]);

        $result = $this->creditService->saveActivePanelApiToken((string) $validated['api_token']);

        return redirect()
            ->route('admin.sms.index')
            ->with($result->ok ? 'flash_success' : 'flash_error', $result->message)
            ->with('sms_active_tab', 'credit')
            ->withInput($result->ok ? [] : $request->only('api_token'));
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
            'overdue_repeat_mode' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled')),
                'nullable',
                'string',
                Rule::in(['once', 'daily', 'weekly', 'interval']),
            ],
            'overdue_repeat_interval_days' => [
                Rule::requiredIf(fn (): bool => $request->boolean('reminder_enabled') && $request->input('overdue_repeat_mode') === 'interval'),
                'nullable',
                'integer',
                'min:2',
                'max:365',
            ],
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
            'overdue_repeat_mode' => 'بازه ارسال پیامک معوق',
            'overdue_repeat_interval_days' => 'تعداد روز بین هر ارسال معوق',
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
            'overdue_repeat_mode' => $validated['overdue_repeat_mode'] ?? 'once',
            'overdue_repeat_interval_days' => $validated['overdue_repeat_interval_days'] ?? null,
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

    public function sendFreeSms(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);

        $source = (string) $request->input('recipient_source', 'manual_numbers');
        $sources = [
            'manual_numbers',
            'selected_customers',
            'all_customers',
            'overdue_customers',
            'settled_customers',
            'no_debt_customers',
            'wallet_positive_customers',
            'excel',
        ];
        if (! in_array($source, $sources, true)) {
            throw ValidationException::withMessages(['recipient_source' => 'روش انتخاب گیرنده معتبر نیست.']);
        }

        $validated = $request->validate([
            'recipient_source' => ['required', Rule::in($sources)],
            'recipients' => ['required_if:recipient_source,manual_numbers', 'nullable', 'string', 'max:4000'],
            'customer_ids' => ['required_if:recipient_source,selected_customers', 'nullable', 'array', 'min:1', 'max:10000'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
            'message' => ['required_unless:recipient_source,excel', 'nullable', 'string', 'max:700'],
            'contacts_file' => ['required_if:recipient_source,excel', 'nullable', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [], [
            'recipient_source' => 'مخاطبان پیامک',
            'recipients' => 'شماره‌های گیرنده',
            'customer_ids' => 'مشتریان انتخاب‌شده',
            'message' => 'متن پیامک',
            'contacts_file' => 'فایل مخاطبان',
        ]);

        $commonMessage = $this->cleanFreeSmsMessage((string) ($validated['message'] ?? ''));
        $recipients = [];
        $invalidCount = 0;
        $duplicateCount = 0;

        if ($source === 'manual_numbers') {
            $parsed = $this->parseFreeSmsRecipients((string) $validated['recipients']);
            $invalidCount = $parsed['invalid_count'];
            $duplicateCount = $parsed['duplicate_count'];
            foreach ($parsed['mobiles'] as $mobile) {
                $recipients[] = ['customer_id' => null, 'mobile' => $mobile, 'message' => $commonMessage];
            }
        } elseif ($source === 'excel') {
            /** @var UploadedFile $file */
            $file = $validated['contacts_file'];
            $parsed = $this->freeSendExcelImporter->parse($file);
            $invalidCount = $parsed['invalid_count'];
            $duplicateCount = $parsed['duplicate_count'];
            foreach ($parsed['recipients'] as $row) {
                $rowMessage = $this->cleanFreeSmsMessage($row['message'] !== '' ? $row['message'] : $commonMessage);
                if ($rowMessage === '') {
                    $invalidCount++;

                    continue;
                }
                if (mb_strlen($rowMessage) > 700) {
                    throw ValidationException::withMessages([
                        'contacts_file' => 'متن پیام یکی از ردیف‌های فایل بیشتر از ۷۰۰ نویسه است.',
                    ]);
                }
                $recipients[] = ['customer_id' => null, 'mobile' => $row['mobile'], 'message' => $rowMessage];
            }
        } else {
            $customerIds = array_map('intval', (array) ($validated['customer_ids'] ?? []));
            $customers = $this->freeSendAudience->resolve($source, $customerIds);
            foreach ($customers as $customer) {
                $personalized = $this->renderFreeSmsMessage($commonMessage, $customer);
                $recipients[] = [
                    'customer_id' => $customer['customer_id'],
                    'mobile' => $customer['mobile'],
                    'message' => $personalized,
                ];
            }
        }

        if ($recipients === []) {
            $reason = $source === 'manual_numbers' || $source === 'excel'
                ? 'شماره معتبر با متن پیام قابل ارسال پیدا نشد.'
                : 'مشتری واجد شرایط با شماره معتبر و اجازه دریافت پیامک پیدا نشد.';

            return redirect()
                ->route('admin.sms.index')
                ->withInput()
                ->withErrors(['recipients' => $reason])
                ->with('sms_active_tab', 'free_send');
        }

        if ($source === 'manual_numbers' && count($recipients) > self::FREE_SEND_MAX_RECIPIENTS) {
            return redirect()
                ->route('admin.sms.index')
                ->withInput()
                ->withErrors(['recipients' => 'برای ارسال به بیش از ۳۰ شماره، فایل اکسل یا انتخاب مشتریان را استفاده کنید.'])
                ->with('sms_active_tab', 'free_send');
        }

        if (config('queue.default') === 'sync' && count($recipients) > self::FREE_SEND_MAX_RECIPIENTS) {
            return redirect()
                ->route('admin.sms.index')
                ->withInput()
                ->withErrors(['recipient_source' => 'برای ارسال گروهی، صف پردازش پیامک باید فعال باشد تا درخواست وب‌سایت طولانی نشود.'])
                ->with('sms_active_tab', 'free_send');
        }

        $campaign = DB::transaction(function () use ($admin, $source, $recipients): SmsFreeSendCampaign {
            $campaign = SmsFreeSendCampaign::query()->create([
                'created_by_admin_id' => $admin->id,
                'source' => $source,
                'status' => 'queued',
                'total_count' => count($recipients),
            ]);

            foreach (array_chunk($recipients, 500) as $chunk) {
                $now = now();
                SmsFreeSendCampaignRecipient::query()->insert(array_map(static fn (array $recipient): array => [
                    'campaign_id' => $campaign->id,
                    'customer_id' => $recipient['customer_id'],
                    'recipient' => $recipient['mobile'],
                    'message_text' => $recipient['message'],
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return $campaign;
        });

        try {
            SendSmsFreeSendCampaignBatchJob::dispatch((int) $campaign->id);
        } catch (\Throwable $e) {
            $campaign->update(['status' => 'failed', 'completed_at' => now()]);

            return redirect()
                ->route('admin.sms.index')
                ->with('flash_error', 'ذخیرهٔ کمپین انجام شد اما صف پیامک در دسترس نیست؛ هیچ پیامکی ارسال نشد.')
                ->with('sms_active_tab', 'free_send');
        }

        $campaign->refresh();
        $flashMessage = $campaign->status === 'completed'
            ? 'کمپین پیامک تکمیل شد: '.$campaign->sent_count.' موفق و '.$campaign->failed_count.' ناموفق.'
            : 'کمپین برای ارسال به '.$campaign->total_count.' مخاطب در صف قرار گرفت.';
        if ($invalidCount > 0 || $duplicateCount > 0) {
            $flashMessage .= ' '.$invalidCount.' ردیف نامعتبر و '.$duplicateCount.' شماره تکراری کنار گذاشته شد.';
        }

        return redirect()
            ->route('admin.sms.index')
            ->with($campaign->failed_count > 0 && $campaign->sent_count === 0 ? 'flash_error' : 'flash_success', $flashMessage)
            ->with('sms_free_send_campaign_id', $campaign->id)
            ->with('sms_active_tab', 'free_send');
    }

    public function searchFreeSmsCustomers(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['items' => []]);
        }

        $items = Customer::query()
            ->where(fn ($query) => $query->whereNull('sms_sending_enabled')->orWhere('sms_sending_enabled', true))
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('last_name', 'like', '%'.$term.'%')
                    ->orWhere('mobile', 'like', '%'.$term.'%')
                    ->orWhere('customer_code', 'like', '%'.$term.'%');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'mobile', 'customer_code'])
            ->map(static fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'name' => $customer->fullName(),
                'mobile' => (string) $customer->mobile,
                'code' => (string) $customer->customer_code,
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function previewFreeSmsAudience(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);
        $validated = $request->validate([
            'recipient_source' => ['required', Rule::in([
                'selected_customers', 'all_customers', 'overdue_customers', 'settled_customers', 'no_debt_customers', 'wallet_positive_customers',
            ])],
            'customer_ids' => ['required_if:recipient_source,selected_customers', 'nullable', 'array', 'min:1', 'max:10000'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
        ]);

        $items = $this->freeSendAudience->resolve(
            (string) $validated['recipient_source'],
            array_map('intval', (array) ($validated['customer_ids'] ?? [])),
        );

        return response()->json([
            'count' => count($items),
            'sample' => array_slice(array_map(static fn (array $item): array => [
                'name' => $item['name'],
                'mobile' => $item['mobile'],
                'wallet_balance_toman' => $item['wallet_balance_toman'],
            ], $items), 0, 5),
        ]);
    }

    public function previewFreeSmsExcel(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);
        $validated = $request->validate([
            'contacts_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);
        $parsed = $this->freeSendExcelImporter->parse($validated['contacts_file']);

        return response()->json([
            'count' => count($parsed['recipients']),
            'invalid_count' => $parsed['invalid_count'],
            'duplicate_count' => $parsed['duplicate_count'],
            'sample' => array_slice(array_map(static fn (array $row): array => [
                'mobile' => $row['mobile'],
                'message' => mb_substr($row['message'], 0, 160),
            ], $parsed['recipients']), 0, 5),
        ]);
    }

    public function freeSmsCampaignStatus(SmsFreeSendCampaign $smsFreeSendCampaign): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);
        if (! $admin->isSuperAdmin() && (int) $smsFreeSendCampaign->created_by_admin_id !== (int) $admin->id) {
            abort(404);
        }

        return response()->json([
            'id' => (int) $smsFreeSendCampaign->id,
            'status' => (string) $smsFreeSendCampaign->status,
            'total_count' => (int) $smsFreeSendCampaign->total_count,
            'sent_count' => (int) $smsFreeSendCampaign->sent_count,
            'failed_count' => (int) $smsFreeSendCampaign->failed_count,
        ]);
    }

    public function downloadFreeSmsExcelSample(): StreamedResponse
    {
        $admin = Auth::guard('admin')->user();
        $this->assertCanSendFreeSms($admin);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('مخاطبان پیامک');
        $sheet->fromArray(['شماره موبایل', 'متن پیامک'], null, 'A1');
        $sheet->fromArray([['', '']], null, 'A2');
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(60);

        return response()->streamDownload(static function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'sms-free-send-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function assertCanSendFreeSms(Admin $admin): void
    {
        if (! $this->permissions->canAccessUiFeature($admin, 'sms', 'free_send.send')) {
            abort(403, 'شما به ارسال آزاد پیامک دسترسی ندارید.');
        }
    }

    private function cleanFreeSmsMessage(string $message): string
    {
        $message = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u', '', $message) ?? '';

        return trim($message);
    }

    /**
     * @param  array{customer_id:int,mobile:string,name:string,wallet_balance_toman:int}  $customer
     */
    private function renderFreeSmsMessage(string $message, array $customer): string
    {
        return app(SmsTemplateRenderer::class)->render($message, [
            'customer_name' => $customer['name'],
            'wallet_balance' => number_format($customer['wallet_balance_toman'], 0, '.', ',').' تومان',
            'mobile' => $customer['mobile'],
        ]);
    }

    public function storeFreeSmsTemplate(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        if (! $this->permissions->canAccessUiFeature($admin, 'sms', 'free_send.send')) {
            abort(403, 'شما به ذخیره الگوی پیام آزاد دسترسی ندارید.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:1', 'max:700'],
        ], [], [
            'title' => 'عنوان الگو',
            'body' => 'متن الگو',
        ]);

        $title = trim((string) $validated['title']);
        $body = trim((string) preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u', '', (string) $validated['body']));
        if ($title === '') {
            return response()->json(['message' => 'عنوان الگو نمی‌تواند خالی باشد.'], 422);
        }
        if ($body === '') {
            return response()->json(['message' => 'متن الگو نمی‌تواند خالی باشد.'], 422);
        }

        $template = SmsTemplate::query()->create([
            'title' => $title,
            'category' => 'free_send',
            'body' => $body,
            'placeholders' => [],
        ]);

        return response()->json([
            'id' => (int) $template->id,
            'title' => (string) $template->title,
            'body' => (string) $template->body,
            'message' => 'الگوی پیام آزاد ذخیره شد.',
        ], 201);
    }

    /**
     * @return array{mobiles: list<string>, invalid_count: int, duplicate_count: int}
     */
    private function parseFreeSmsRecipients(string $raw): array
    {
        $chunks = preg_split('/[\s,;،]+/u', $raw) ?: [];
        $mobiles = [];
        $seen = [];
        $invalidCount = 0;
        $duplicateCount = 0;

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }

            $normalized = IranMobile::normalize($chunk);
            if ($normalized === null) {
                $invalidCount++;

                continue;
            }

            if (isset($seen[$normalized])) {
                $duplicateCount++;

                continue;
            }

            $seen[$normalized] = true;
            $mobiles[] = $normalized;
        }

        return [
            'mobiles' => $mobiles,
            'invalid_count' => $invalidCount,
            'duplicate_count' => $duplicateCount,
        ];
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
            'free_send' => 'پیام آزاد',
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
            'wallet_balance' => ['label' => 'موجودی کیف پول', 'sample' => '850,000 تومان'],
            'username' => ['label' => 'نام کاربری', 'sample' => '09121234567'],
            'password' => ['label' => 'رمز ورود', 'sample' => 'Ab12cd34'],
            'loan_amount' => ['label' => 'مبلغ کل وام', 'sample' => '50,000,000 تومان'],
            'installment_number' => ['label' => 'شماره قسط', 'sample' => '5'],
            'installment_amount' => ['label' => 'مبلغ قسط', 'sample' => '1,500,000 تومان'],
            'paid_amount' => ['label' => 'مبلغ واریز', 'sample' => '600,000 تومان'],
            'payment_date' => ['label' => 'تاریخ واریز', 'sample' => '1405/02/22'],
            'days_until_due' => ['label' => 'تعداد روز تا سررسید', 'sample' => '2'],
            'remaining_loan' => ['label' => 'مانده وام', 'sample' => '12,400,000 تومان'],
            'purchase_credit' => ['label' => 'اعتبار خرید باقیمانده', 'sample' => '8,500,000 تومان'],
            'purchase_credit_after_payment' => ['label' => 'اعتبار باقیمانده پس از پرداخت قسط', 'sample' => '9,000,000 تومان'],
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

        if ($request->old('api_token') !== null) {
            return 'credit';
        }

        if ($request->old('recipients') !== null) {
            return 'free_send';
        }

        return null;
    }
}
