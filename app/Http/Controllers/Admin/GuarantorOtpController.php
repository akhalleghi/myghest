<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class GuarantorOtpController extends Controller
{
    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'guarantor_name' => ['nullable', 'string', 'max:200'],
            'borrower_name' => ['nullable', 'string', 'max:200'],
        ]);

        $mobile = $this->toEnglishDigits(trim((string) $validated['mobile']));

        $code = (string) random_int(100000, 999999);
        $sessionId = (string) Str::uuid();

        Cache::put('guarantor_otp:'.$sessionId, [
            'code' => $code,
            'mobile' => $mobile,
        ], now()->addMinutes(10));

        $appName = $this->appDisplayName();
        $guarantorLabel = $this->smsSafeLine((string) ($validated['guarantor_name'] ?? ''));
        $borrowerLabel = $this->smsSafeLine((string) ($validated['borrower_name'] ?? ''));
        if ($guarantorLabel === '') {
            $guarantorLabel = 'ضامن';
        }
        if ($borrowerLabel === '') {
            $borrowerLabel = 'مشتری';
        }

        $text = $this->guarantorOtpSmsText($guarantorLabel, $borrowerLabel, $appName, $code);

        $result = $this->rawSms->send($mobile, $text, 'guarantor-otp');
        if (! $result['ok']) {
            Cache::forget('guarantor_otp:'.$sessionId);

            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'کد به شماره ضامن ارسال شد.',
            'otp_session' => $sessionId,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_session' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ]);

        $mobile = $this->toEnglishDigits(trim((string) $validated['mobile']));
        $codeIn = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['code']) ?? '');

        $sessionKey = 'guarantor_otp:'.trim((string) $validated['otp_session']);
        $data = Cache::get($sessionKey);

        if (! is_array($data) || (($data['mobile'] ?? '') !== $mobile)) {
            return response()->json(['message' => 'جلسه ارسال کد معتبر نیست؛ دوباره درخواست کد دهید.'], 422);
        }

        if ((string) ($data['code'] ?? '') !== $codeIn) {
            return response()->json(['message' => 'کد وارد شده صحیح نیست.'], 422);
        }

        $token = Str::random(48);
        Cache::put('guarantor_verified:'.$token, ['mobile' => $mobile], now()->addHours(2));
        Cache::forget($sessionKey);

        return response()->json([
            'message' => 'احراز با موفقیت انجام شد.',
            'verification_token' => $token,
        ]);
    }

    private function guarantorOtpSmsText(string $guarantorLabel, string $borrowerLabel, string $appName, string $code): string
    {
        $tpl = SmsTemplate::query()->where('template_key', 'default_guarantor_otp')->first();
        if ($tpl !== null && trim((string) $tpl->body) !== '') {
            return $this->renderTemplateBody((string) $tpl->body, [
                'guarantor_name' => $guarantorLabel,
                'borrower_name' => $borrowerLabel,
                'app_name' => $appName,
                'code' => $code,
            ]);
        }

        return 'آقای/خانم ('.$guarantorLabel.')'.chr(10)
            .'شما در حال ضمانت آقای/خانم ('.$borrowerLabel.') در سامانه ('.$appName.') هستید.'.chr(10)
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

    /** یک خط متن برای پیامک: بدون شکست خط و کنترل‌کاراکتر */
    private function smsSafeLine(string $value): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($t === '') {
            return '';
        }
        $t = str_replace(["\r", "\n"], '', $t);

        return mb_substr($t, 0, 200);
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }
}
