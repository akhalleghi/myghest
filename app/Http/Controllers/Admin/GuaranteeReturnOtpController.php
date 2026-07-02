<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanGuarantee;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\GuaranteeReturnOtpSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class GuaranteeReturnOtpController extends Controller
{
    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function send(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if (! GuaranteeReturnOtpSettings::isEnabled()) {
            return response()->json(['message' => 'تایید پیامکی عودت ضمانت در تنظیمات غیرفعال است.'], 422);
        }

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:200'],
            'guarantee_id' => ['nullable', 'integer', 'min:1'],
            'guarantee_type_label' => ['nullable', 'string', 'max:120'],
        ]);

        $guaranteeId = isset($validated['guarantee_id']) ? (int) $validated['guarantee_id'] : null;
        if ($guaranteeId !== null) {
            $guarantee = CustomerLoanGuarantee::query()
                ->where('id', $guaranteeId)
                ->where('loan_file_id', $loanFile->id)
                ->where('customer_id', $customer->id)
                ->first();
            if ($guarantee === null) {
                return response()->json(['message' => 'ضمانت انتخاب‌شده معتبر نیست.'], 422);
            }
            if (! $this->guaranteeTypeSupportsReturn((string) $guarantee->type)) {
                return response()->json(['message' => 'این نوع ضمانت از عودت پشتیبانی نمی‌کند.'], 422);
            }
        }

        $mobile = $this->normalizeMobile((string) $customer->mobile);
        if ($mobile === '') {
            return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است.'], 422);
        }

        $code = (string) random_int(100000, 999999);
        $sessionId = (string) Str::uuid();

        Cache::put('guarantee_return_otp:'.$sessionId, [
            'code' => $code,
            'mobile' => $mobile,
            'customer_id' => (int) $customer->id,
            'loan_file_id' => (int) $loanFile->id,
            'guarantee_id' => $guaranteeId,
        ], now()->addMinutes(10));

        $customerLabel = $this->smsSafeLine((string) ($validated['customer_name'] ?? ''));
        if ($customerLabel === '') {
            $customerLabel = $this->smsSafeLine(trim((string) $customer->first_name.' '.(string) $customer->last_name));
        }
        if ($customerLabel === '') {
            $customerLabel = 'مشتری';
        }

        $typeLabel = $this->smsSafeLine((string) ($validated['guarantee_type_label'] ?? ''));
        if ($typeLabel === '') {
            $typeLabel = 'ضمانت';
        }

        $text = $this->guaranteeReturnOtpSmsText($customerLabel, $this->appDisplayName(), $typeLabel, $code);
        $result = $this->rawSms->send($mobile, $text, 'guarantee-return-otp');
        if (! $result['ok']) {
            Cache::forget('guarantee_return_otp:'.$sessionId);

            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'کد تایید عودت به موبایل مشتری ارسال شد.',
            'otp_session' => $sessionId,
            'mobile_masked' => $this->maskMobile($mobile),
        ]);
    }

    public function verify(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if (! GuaranteeReturnOtpSettings::isEnabled()) {
            return response()->json(['message' => 'تایید پیامکی عودت ضمانت در تنظیمات غیرفعال است.'], 422);
        }

        $validated = $request->validate([
            'otp_session' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
            'guarantee_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $mobile = $this->normalizeMobile((string) $customer->mobile);
        if ($mobile === '') {
            return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است.'], 422);
        }

        $guaranteeId = isset($validated['guarantee_id']) ? (int) $validated['guarantee_id'] : null;
        if ($guaranteeId !== null) {
            $guarantee = CustomerLoanGuarantee::query()
                ->where('id', $guaranteeId)
                ->where('loan_file_id', $loanFile->id)
                ->where('customer_id', $customer->id)
                ->first();
            if ($guarantee === null) {
                return response()->json(['message' => 'ضمانت انتخاب‌شده معتبر نیست.'], 422);
            }
        }

        $codeIn = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['code']) ?? '');
        $sessionKey = 'guarantee_return_otp:'.trim((string) $validated['otp_session']);
        $data = Cache::get($sessionKey);

        if (! is_array($data)
            || (int) ($data['customer_id'] ?? 0) !== (int) $customer->id
            || (int) ($data['loan_file_id'] ?? 0) !== (int) $loanFile->id
            || (string) ($data['mobile'] ?? '') !== $mobile) {
            return response()->json(['message' => 'جلسه ارسال کد معتبر نیست؛ دوباره درخواست کد دهید.'], 422);
        }

        $sessionGuaranteeId = isset($data['guarantee_id']) ? (int) $data['guarantee_id'] : null;
        if ($sessionGuaranteeId !== $guaranteeId) {
            return response()->json(['message' => 'جلسه تایید با ضمانت جاری سازگار نیست؛ دوباره کد بگیرید.'], 422);
        }

        if ((string) ($data['code'] ?? '') !== $codeIn) {
            return response()->json(['message' => 'کد وارد شده صحیح نیست.'], 422);
        }

        $token = Str::random(48);
        Cache::put('guarantee_return_verified:'.$token, [
            'mobile' => $mobile,
            'customer_id' => (int) $customer->id,
            'loan_file_id' => (int) $loanFile->id,
            'guarantee_id' => $guaranteeId,
        ], now()->addMinutes(30));
        Cache::forget($sessionKey);

        return response()->json([
            'message' => 'احراز مشتری برای عودت ضمانت انجام شد.',
            'verification_token' => $token,
        ]);
    }

    private function guaranteeTypeSupportsReturn(string $type): bool
    {
        return in_array($type, [
            CustomerLoanGuarantee::TYPE_CHEQUE,
            CustomerLoanGuarantee::TYPE_GOLD,
            CustomerLoanGuarantee::TYPE_OTHER,
        ], true);
    }

    private function guaranteeReturnOtpSmsText(string $customerLabel, string $appName, string $typeLabel, string $code): string
    {
        $tpl = SmsTemplate::query()->where('template_key', 'default_guarantee_return_otp')->first();
        if ($tpl !== null && trim((string) $tpl->body) !== '') {
            return $this->renderTemplateBody((string) $tpl->body, [
                'customer_name' => $customerLabel,
                'app_name' => $appName,
                'guarantee_type_label' => $typeLabel,
                'code' => $code,
            ]);
        }

        return 'مشتری گرامی ('.$customerLabel.')'.chr(10)
            .'عودت ضمانت ('.$typeLabel.') پرونده وام شما در سامانه ('.$appName.') ثبت می‌شود.'.chr(10)
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
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
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
