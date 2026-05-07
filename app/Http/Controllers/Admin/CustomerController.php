<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerBankAccount;
use App\Models\CustomerReferrer;
use App\Models\CustomerWallet;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Rules\IranNationalId;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly SmsPanelManager $panelManager,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($w) use ($q): void {
                    $w->where('customer_code', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('mobile', 'like', '%'.$q.'%')
                        ->orWhere('national_id', 'like', '%'.$q.'%');
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $q,
            'smsTemplates' => SmsTemplate::query()
                ->latest('id')
                ->get(['id', 'title', 'category', 'body'])
                ->map(static fn (SmsTemplate $tpl): array => [
                    'id' => $tpl->id,
                    'title' => $tpl->title,
                    'category' => $tpl->category,
                    'body' => $tpl->body,
                ])
                ->values(),
        ]);
    }

    public function sendQuickSms(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'sms_type' => ['required', 'in:wallet_link,welcome'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ]);

        $smsType = (string) $validated['sms_type'];
        $messageText = trim((string) ($validated['sms_text'] ?? ''));
        $templateId = $validated['sms_template_id'] ?? null;
        if ($messageText === '' && $templateId !== null) {
            $tpl = SmsTemplate::query()->find((int) $templateId);
            if ($tpl !== null) {
                $messageText = $this->renderTemplate($tpl->body, [
                    'store_name' => $this->appDisplayName(),
                    'customer_name' => $customer->fullName(),
                    'payment_link' => '—',
                    'payment_link_variable' => '—',
                ]);
            }
        }
        if ($messageText === '') {
            $messageText = $smsType === 'wallet_link'
                ? 'سلام '.$customer->fullName().'، لینک شارژ کیف پول شما: —'
                : 'سلام '.$customer->fullName().'، به سامانه '.$this->appDisplayName().' خوش آمدید.';
        }

        $result = $this->sendRawSms($customer->mobile, $messageText, $smsType === 'wallet_link' ? 'wallet-charge-link' : 'welcome-message');

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function store(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $request->merge([
            'national_id' => $this->toEnglishDigits(trim((string) $request->input('national_id', ''))),
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'postal_code' => $this->toEnglishDigits(trim((string) $request->input('postal_code', ''))),
        ]);

        $validated = $request->validate([
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['required', 'string', 'max:120'],
            'national_id' => ['required', 'digits:10', new IranNationalId, Rule::unique('customers', 'national_id')],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')],
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:2000'],
            'postal_code' => ['required', 'string', 'max:16', 'regex:/^[0-9]{10}$/'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.',
            ]);
        }

        $accounts = $this->validatedBankAccounts($request->input('accounts', []));
        $referrers = $this->validatedReferrers($request->input('referrers', []));

        $sendCredentials = $request->boolean('send_credentials');

        $customerCode = trim((string) ($validated['customer_code'] ?? ''));
        if ($customerCode === '') {
            $customerCode = $this->generateUniqueCustomerCode();
        }

        $plainPassword = (string) $validated['password'];

        $customer = DB::transaction(function () use (
            $validated,
            $customerCode,
            $username,
            $plainPassword,
            $membershipAt,
            $birthDate,
            $accounts,
            $referrers
        ): Customer {
            /** @var Customer $c */
            $c = Customer::query()->create([
                'customer_code' => $customerCode,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'],
                'national_id' => $validated['national_id'],
                'mobile' => $validated['mobile'],
                'phone_landline' => $validated['phone_landline'] !== null && $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'password' => $plainPassword,
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
            ]);

            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $c->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $c->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }

            CustomerWallet::query()->create([
                'customer_id' => $c->id,
                'balance_toman' => 0,
                'is_locked' => false,
            ]);

            return $c;
        });

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10)
                .'رمز عبور: '.$plainPassword;
            $smsResult = $this->sendRawSms($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'مشتری با موفقیت ثبت شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', $flash);
    }

    public function editData(Customer $customer): JsonResponse
    {
        $customer->load(['bankAccounts', 'referrers']);

        $membershipJ = '';
        if ($customer->membership_at !== null) {
            $membershipJ = Jalali::instance(Carbon::parse($customer->membership_at))->format('Y/m/d');
        }
        $birthJ = '';
        if ($customer->birth_date !== null) {
            $birthJ = Jalali::instance(Carbon::parse($customer->birth_date))->format('Y/m/d');
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'father_name' => $customer->father_name,
                'national_id' => $customer->national_id,
                'mobile' => $customer->mobile,
                'phone_landline' => (string) ($customer->phone_landline ?? ''),
                'membership_jdate' => $membershipJ,
                'birth_jdate' => $birthJ,
                'email' => (string) ($customer->email ?? ''),
                'city' => $customer->city,
                'address' => $customer->address,
                'postal_code' => $customer->postal_code,
            ],
            'bank_accounts' => $customer->bankAccounts->map(static function (CustomerBankAccount $b): array {
                return [
                    'account_identifier' => $b->account_identifier,
                    'bank_name' => (string) ($b->bank_name ?? ''),
                    'branch_name' => (string) ($b->branch_name ?? ''),
                ];
            })->values(),
            'referrers' => $customer->referrers->map(static function (CustomerReferrer $r): array {
                return [
                    'first_name' => $r->first_name,
                    'last_name' => $r->last_name,
                    'phone' => $r->phone,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $request->merge([
            'national_id' => $this->toEnglishDigits(trim((string) $request->input('national_id', ''))),
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'postal_code' => $this->toEnglishDigits(trim((string) $request->input('postal_code', ''))),
        ]);

        $validator = Validator::make($request->all(), [
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')->ignore($customer->id)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['required', 'string', 'max:120'],
            'national_id' => ['required', 'digits:10', new IranNationalId, Rule::unique('customers', 'national_id')->ignore($customer->id)],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')->ignore($customer->id)],
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:2000'],
            'postal_code' => ['required', 'string', 'max:16', 'regex:/^[0-9]{10}$/'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($validator)
                ->with('open_edit_customer_id', $customer->id);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->where('id', '!=', $customer->id)->exists()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors(['mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.'])
                ->with('open_edit_customer_id', $customer->id);
        }

        try {
            $accounts = $this->validatedBankAccounts($request->input('accounts', []));
            $referrers = $this->validatedReferrers($request->input('referrers', []));
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($e->errors())
                ->with('open_edit_customer_id', $customer->id);
        }

        $sendCredentials = $request->boolean('send_credentials');
        $plainPasswordInput = trim((string) ($validated['password'] ?? ''));

        DB::transaction(function () use ($validated, $customer, $username, $membershipAt, $birthDate, $accounts, $referrers, $plainPasswordInput): void {
            $data = [
                'customer_code' => trim((string) ($validated['customer_code'] ?? '')) !== ''
                    ? (string) $validated['customer_code']
                    : $customer->customer_code,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'],
                'national_id' => $validated['national_id'],
                'mobile' => $validated['mobile'],
                'phone_landline' => $validated['phone_landline'] !== null && (string) $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
            ];

            if ($plainPasswordInput !== '') {
                $data['password'] = $plainPasswordInput;
            }

            $customer->update($data);

            $customer->bankAccounts()->delete();
            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $customer->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            $customer->referrers()->delete();
            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $customer->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }
        });

        $customer->refresh();

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10);
            if ($plainPasswordInput !== '') {
                $msg .= 'رمز عبور: '.$plainPasswordInput;
            } else {
                $msg .= 'رمز عبور تغییر نکرده است.';
            }
            $smsResult = $this->sendRawSms($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'اطلاعات مشتری با موفقیت به‌روزرسانی شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index', $request->only('q'))
            ->with('flash_success', $flash);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', 'مشتری با موفقیت حذف شد.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{account_identifier: string, bank_name: string|null, branch_name: string|null}>
     */
    private function validatedBankAccounts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $acc = $this->toEnglishDigits(trim((string) ($row['account_identifier'] ?? '')));
            $bank = trim((string) ($row['bank_name'] ?? ''));
            $branch = trim((string) ($row['branch_name'] ?? ''));
            if ($acc === '' && $bank === '' && $branch === '') {
                continue;
            }
            if ($acc === '') {
                throw ValidationException::withMessages([
                    'accounts' => 'برای هر ردیف شماره حساب، شماره کارت یا شبا را کامل کنید.',
                ]);
            }
            $out[] = [
                'account_identifier' => $acc,
                'bank_name' => $bank !== '' ? $bank : null,
                'branch_name' => $branch !== '' ? $branch : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{first_name: string, last_name: string, phone: string}>
     */
    private function validatedReferrers(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fn = trim((string) ($row['first_name'] ?? ''));
            $ln = trim((string) ($row['last_name'] ?? ''));
            $ph = $this->toEnglishDigits(trim((string) ($row['phone'] ?? '')));
            if ($fn === '' && $ln === '' && $ph === '') {
                continue;
            }
            if ($fn === '' || $ln === '' || $ph === '') {
                throw ValidationException::withMessages([
                    'referrers' => 'برای هر معرف، نام، نام خانوادگی و شماره تماس الزامی است.',
                ]);
            }
            if (! preg_match('/^09\d{9}$/', $ph)) {
                throw ValidationException::withMessages([
                    'referrers' => 'شماره تماس معرف باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
                ]);
            }
            $out[] = ['first_name' => $fn, 'last_name' => $ln, 'phone' => $ph];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function sendRawSms(string $recipient, string $messageText, string $type = 'customer-credentials'): array
    {
        $active = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($active === null) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک فعال نیست).'];
        }

        $providerOptions = $this->panelManager->providerOptions();
        $providerKey = $active->provider;
        if (! isset($providerOptions[$providerKey])) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک پیکربندی نشده).'];
        }

        $password = $this->decryptPasswordOrEmpty((string) $active->password);
        if ($password === '') {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (رمز پنل ذخیره نشده).'];
        }

        $gateway = $this->panelManager->gateway($providerKey);
        $result = $gateway->sendTestMessage(
            (string) $active->username,
            $password,
            $recipient,
            $messageText,
            [
                'domain_name' => (string) ($active->domain_name ?: 'sepahansms'),
                'sender_number' => (string) ($active->sender_number ?: '50003300'),
            ]
        );

        SmsLog::query()->create([
            'sms_panel' => (string) ($providerOptions[$providerKey] ?? $providerKey),
            'status' => $result->ok ? SmsLog::STATUS_DELIVERED : SmsLog::STATUS_UNDELIVERED,
            'sent_at' => now(),
            'message_text' => $messageText,
            'recipient' => $recipient,
            'type' => $type,
            'cost' => 0,
            'meta' => [
                'provider' => $providerKey,
                'response_code' => $result->code,
                'result_message' => $result->message,
            ],
        ]);

        // Do not mutate panel connection health here.
        // Operational SMS failures must not flip panel status in settings.

        return [
            'ok' => $result->ok,
            'message' => $result->ok
                ? 'پیام برای مشتری ارسال شد.'
                : 'عملیات انجام شد اما ارسال پیامک ناموفق بود: '.$result->message,
        ];
    }

    private function renderTemplate(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
        }

        return trim($out);
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $v : (string) config('app.name');
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

    private function usernameFromMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    private function generateUniqueCustomerCode(): string
    {
        do {
            $code = 'CUS-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (Customer::query()->where('customer_code', $code)->exists());

        return $code;
    }

    private function parseJalaliDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = $this->toEnglishDigits($value);

        try {
            $j = Jalali::parseFormat('Y/m/d', $value);

            return Carbon::createFromTimestamp($j->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }
}
