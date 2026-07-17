<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Admin\CaptchaService;
use App\Services\Admin\RawSmsDispatcher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class CustomerPasswordForgotController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const RESET_TTL_MINUTES = 20;

    private const MAX_OTP_ATTEMPTS = 6;

    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $this->mergeNormalizedDigits($request, ['mobile', 'captcha']);

        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^(09|9)\d{9}$/'],
            'captcha' => ['required', 'string', 'regex:/^\d{5}$/'],
        ], [
            'mobile.regex' => 'شماره موبایل معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹).',
            'captcha.regex' => 'کپچا باید ۵ رقم باشد.',
        ]);

        $this->ensureForgotOtpSendNotLocked($request);

        if (! CaptchaService::validate($validated['captcha'], CaptchaService::PURPOSE_USER_FORGOT)) {
            $this->hitForgotOtpSendThrottle($request);

            return response()->json([
                'message' => 'کد تأیید تصویر نادرست است؛ تصویر را تازه کنید و دوباره تلاش کنید.',
            ], 422);
        }

        $mobile = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['mobile']) ?? '');
        if (strlen($mobile) === 10 && str_starts_with($mobile, '9')) {
            $mobile = '0'.$mobile;
        }
        if (! preg_match('/^09\d{9}$/', $mobile)) {
            return response()->json(['message' => 'شماره موبایل معتبر نیست.'], 422);
        }

        $customer = Customer::query()->where('mobile', $mobile)->first();

        if ($customer === null) {
            $this->hitForgotOtpSendThrottle($request);

            return response()->json([
                'message' => 'در صورت ثبت بودن این شماره در سامانه، پیامک حاوی کد برای شما ارسال می‌شود.',
                'otp_session' => null,
            ]);
        }

        $plain = (string) random_int(100000, 999999);
        $codeHash = hash_hmac('sha256', $plain, (string) config('app.key'));
        $sessionId = (string) Str::uuid();

        Cache::put($this->otpCacheKey($sessionId), [
            'hash' => $codeHash,
            'mobile' => $mobile,
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        $text = $this->buildOtpSmsBody($plain);
        $result = $this->rawSms->send($mobile, $text, 'customer-password-reset');

        if (! $result['ok']) {
            Cache::forget($this->otpCacheKey($sessionId));

            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        $this->hitForgotOtpSendThrottle($request);

        return response()->json([
            'message' => 'کد تأیید به شماره شما ارسال شد.',
            'otp_session' => $sessionId,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $this->mergeNormalizedDigits($request, ['mobile', 'code']);

        $validated = $request->validate([
            'otp_session' => ['required', 'string', 'uuid'],
            'mobile' => ['required', 'string', 'regex:/^(09|9)\d{9}$/'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $mobile = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['mobile']) ?? '');
        if (strlen($mobile) === 10 && str_starts_with($mobile, '9')) {
            $mobile = '0'.$mobile;
        }

        $codeIn = $this->toEnglishDigits(preg_replace('/\D/', '', (string) $validated['code']) ?? '');

        $sessionKey = $this->otpCacheKey((string) $validated['otp_session']);
        $data = Cache::get($sessionKey);

        if (! is_array($data) || (($data['mobile'] ?? '') !== $mobile)) {
            return response()->json(['message' => 'جلسه تأیید معتبر نیست؛ از ابتدا درخواست کد دهید.'], 422);
        }

        $attempts = (int) ($data['attempts'] ?? 0);
        if ($attempts >= self::MAX_OTP_ATTEMPTS) {
            Cache::forget($sessionKey);

            return response()->json(['message' => 'تعداد تلاش بیش از حد مجاز است؛ دوباره از مرحلهٔ اول اقدام کنید.'], 422);
        }

        $expectedHash = (string) ($data['hash'] ?? '');
        $actualHash = hash_hmac('sha256', $codeIn, (string) config('app.key'));

        if (! hash_equals($expectedHash, $actualHash)) {
            $data['attempts'] = $attempts + 1;
            Cache::put($sessionKey, $data, now()->addMinutes(self::OTP_TTL_MINUTES));

            return response()->json(['message' => 'کد وارد شده صحیح نیست.'], 422);
        }

        Cache::forget($sessionKey);

        $customer = Customer::query()->where('mobile', $mobile)->first();
        if ($customer === null) {
            return response()->json(['message' => 'جلسه تأیید معتبر نیست.'], 422);
        }

        $resetToken = Str::random(64);
        Cache::put($this->resetCacheKey($resetToken), [
            'customer_id' => $customer->id,
            'mobile' => $mobile,
        ], now()->addMinutes(self::RESET_TTL_MINUTES));

        return response()->json([
            'message' => 'کد تأیید شد؛ رمز جدید را وارد کنید.',
            'reset_token' => $resetToken,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(8)->max(128)],
        ]);

        $cacheKey = $this->resetCacheKey((string) $validated['reset_token']);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['customer_id'])) {
            return response()->json(['message' => 'لینک بازیابی منقضی یا نامعتبر است؛ دوباره تلاش کنید.'], 422);
        }

        $customer = Customer::query()->whereKey((int) $payload['customer_id'])->first();
        if ($customer === null) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'حساب کاربری یافت نشد.'], 422);
        }

        $customer->forceFill([
            'password' => $validated['password'],
        ])->save();

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'رمز عبور با موفقیت به‌روزرسانی شد؛ اکنون می‌توانید وارد شوید.',
        ]);
    }

    private function otpCacheKey(string $sessionId): string
    {
        return 'customer_pwd_otp:'.trim($sessionId);
    }

    private function resetCacheKey(string $token): string
    {
        return 'customer_pwd_reset:'.hash('sha256', $token);
    }

    private function buildOtpSmsBody(string $plainCode): string
    {
        $appName = $this->appDisplayName();

        return 'کد بازیابی رمز عبور '.$appName.chr(10)
            .$plainCode.chr(10)
            .'اعتبار '.$this->otpTtlMinutesFa().' دقیقه؛ این کد را در اختیار دیگران نگذارید.';
    }

    private function otpTtlMinutesFa(): string
    {
        return $this->toPersianDigits((string) self::OTP_TTL_MINUTES);
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && trim($v) !== '' ? $this->smsSafeLine($v) : $this->smsSafeLine((string) config('app.name'));
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

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }

    private function toPersianDigits(string $value): string
    {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return str_replace($en, $fa, $value);
    }

    private function forgotOtpSendThrottleKey(Request $request): string
    {
        return 'customer-forgot-otp-send|'.sha1((string) $request->ip());
    }

    private function ensureForgotOtpSendNotLocked(Request $request): void
    {
        $key = $this->forgotOtpSendThrottleKey($request);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $seconds = RateLimiter::availableIn($key);
            throw new HttpResponseException(response()->json([
                'message' => 'درخواست‌های مکرر؛ لطفاً '.$seconds.' ثانیه بعد دوباره تلاش کنید.',
            ], 429));
        }
    }

    private function hitForgotOtpSendThrottle(Request $request): void
    {
        RateLimiter::hit($this->forgotOtpSendThrottleKey($request), 60 * 15);
    }

    /**
     * @param  list<string>  $keys
     */
    private function mergeNormalizedDigits(Request $request, array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $ascii = $this->toEnglishDigits(trim((string) $request->input($key)));
            $merge[$key] = $key === 'captcha'
                ? (preg_replace('/\D+/', '', $ascii) ?? '')
                : $ascii;
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
