<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\LoanCreationOtpSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoanCreationOtpController extends Controller
{
    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function preview(Request $request, Customer $customer): JsonResponse
    {
        if (! LoanCreationOtpSettings::isEnabled()) {
            return response()->json(['message' => 'تایید پیامکی ایجاد وام در تنظیمات غیرفعال است.'], 422);
        }

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:200'],
        ]);
        $mobile = $this->normalizeMobile((string) $customer->mobile);
        if ($mobile === '') {
            return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است.'], 422);
        }

        return response()->json([
            'body' => $this->loanCreationOtpSmsText($this->customerLabel($customer, $validated), $this->appDisplayName(), '{{code}}'),
            'mobile_masked' => $this->maskMobile($mobile),
        ]);
    }

    public function send(Request $request, Customer $customer): JsonResponse
    {
        if (! LoanCreationOtpSettings::isEnabled()) {
            return response()->json(['message' => 'تایید پیامکی ایجاد وام در تنظیمات غیرفعال است.'], 422);
        }

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:200'],
            'sms_text' => ['sometimes', 'required', 'string', 'max:1000'],
        ]);

        $mobile = $this->normalizeMobile((string) $customer->mobile);
        if ($mobile === '') {
            return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است.'], 422);
        }

        $customText = isset($validated['sms_text']) ? trim((string) $validated['sms_text']) : null;
        if ($customText !== null && ! preg_match('/\{\{\s*code\s*\}\}/i', $customText)) {
            throw ValidationException::withMessages([
                'sms_text' => 'عبارت {{code}} باید در متن حفظ شود تا کد تایید در پیامک درج شود.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $sessionId = (string) Str::uuid();

        Cache::put('loan_creation_otp:'.$sessionId, [
            'code' => $code,
            'mobile' => $mobile,
            'customer_id' => (int) $customer->id,
        ], now()->addMinutes(10));

        $text = $customText !== null
            ? $this->renderTemplateBody($customText, ['code' => $code])
            : $this->loanCreationOtpSmsText($this->customerLabel($customer, $validated), $this->appDisplayName(), $code);
        $result = $this->rawSms->send($mobile, $text, 'loan-creation-otp');
        if (! $result['ok']) {
            Cache::forget('loan_creation_otp:'.$sessionId);

            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'کد تایید به موبایل مشتری ارسال شد.',
            'otp_session' => $sessionId,
            'mobile_masked' => $this->maskMobile($mobile),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function customerLabel(Customer $customer, array $validated): string
    {
        $customerLabel = $this->smsSafeLine((string) ($validated['customer_name'] ?? ''));
        if ($customerLabel === '') {
            $customerLabel = $this->smsSafeLine(trim((string) $customer->first_name.' '.(string) $customer->last_name));
        }
        if ($customerLabel === '') {
            $customerLabel = 'مشتری';
        }

        return $customerLabel;
    }

    public function verify(Request $request, Customer $customer): JsonResponse
    {
        if (! LoanCreationOtpSettings::isEnabled()) {
            return response()->json(['message' => 'تایید پیامکی ایجاد وام در تنظیمات غیرفعال است.'], 422);
        }

        $validated = $request->validate([
            'otp_session' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $mobile = $this->normalizeMobile((string) $customer->mobile);
        if ($mobile === '') {
            return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است.'], 422);
        }

        $codeIn = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['code']) ?? '');
        $sessionKey = 'loan_creation_otp:'.trim((string) $validated['otp_session']);
        $data = Cache::get($sessionKey);

        if (! is_array($data)
            || (int) ($data['customer_id'] ?? 0) !== (int) $customer->id
            || (string) ($data['mobile'] ?? '') !== $mobile) {
            return response()->json(['message' => 'جلسه ارسال کد معتبر نیست؛ دوباره درخواست کد دهید.'], 422);
        }

        if ((string) ($data['code'] ?? '') !== $codeIn) {
            return response()->json(['message' => 'کد وارد شده صحیح نیست.'], 422);
        }

        $token = Str::random(48);
        Cache::put('loan_creation_verified:'.$token, [
            'mobile' => $mobile,
            'customer_id' => (int) $customer->id,
        ], now()->addMinutes(30));
        Cache::forget($sessionKey);

        return response()->json([
            'message' => 'احراز مشتری با موفقیت انجام شد.',
            'verification_token' => $token,
        ]);
    }

    private function loanCreationOtpSmsText(string $customerLabel, string $appName, string $code): string
    {
        $tpl = SmsTemplate::query()->where('template_key', 'default_loan_creation_otp')->first();
        if ($tpl !== null && trim((string) $tpl->body) !== '') {
            $body = (string) $tpl->body;
            if (! preg_match('/\{\{\s*code\s*\}\}/i', $body)) {
                $body .= "\nکد تایید: {{code}}";
            }

            return $this->renderTemplateBody($body, [
                'customer_name' => $customerLabel,
                'app_name' => $appName,
                'code' => $code,
            ]);
        }

        return 'مشتری گرامی ('.$customerLabel.')'.chr(10)
            .'برای ثبت پرونده وام در سامانه ('.$appName.') کد تایید زیر را وارد کنید.'.chr(10)
            .'کد تایید: '.$code.chr(10)
            .'لطفا این کد را در اختیار شخص دیگر قرار ندهید.';
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function renderTemplateBody(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace_callback('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', fn (): string => (string) $v, $out) ?? $out;
        }

        return trim($out);
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $this->smsSafeLine($v) : $this->smsSafeLine((string) config('app.name'));
    }

    private function smsSafeLine(string $value): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($t === '') {
            return '';
        }
        $t = str_replace(["\r", "\n"], '', $t);

        return mb_substr($t, 0, 200);
    }

    private function normalizeMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $this->toEnglishDigits(trim($raw))) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }
        if (! preg_match('/^09\d{9}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    private function maskMobile(string $mobile): string
    {
        if (! preg_match('/^09\d{9}$/', $mobile)) {
            return '—';
        }

        return substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }
}
