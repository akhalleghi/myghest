<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\IranMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class CustomerSmsOtpLoginService
{
    private const PENDING_TTL_MINUTES = 10;

    private const OTP_TTL_MINUTES = 10;

    private const MAX_OTP_ATTEMPTS = 6;

    private const MAX_RESENDS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function assertSendAllowed(Request $request): void
    {
        $key = $this->sendThrottleKey($request);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw new \RuntimeException('rate_limited:'.RateLimiter::availableIn($key));
        }
    }

    public function markSendAttempt(Request $request): void
    {
        RateLimiter::hit($this->sendThrottleKey($request), 60 * 15);
    }

    /**
     * @return array{login_session: string, message: string, resend_available_in: int, masked_mobile: ?string}
     */
    public function requestOtp(string $rawMobile, Request $request, bool $remember): array
    {
        $this->assertSendAllowed($request);

        $genericMessage = 'در صورت ثبت بودن این شماره در سامانه، پیامک حاوی کد برای شما ارسال می‌شود.';
        $mobile = IranMobile::normalize($rawMobile);

        if ($mobile === null || ! IranMobile::isValid($mobile)) {
            $this->markSendAttempt($request);

            return $this->issueOpaqueChallenge($request, $remember, $genericMessage, null);
        }

        $customer = Customer::query()->where('mobile', $mobile)->first();
        if ($customer === null) {
            $this->markSendAttempt($request);

            return $this->issueOpaqueChallenge($request, $remember, $genericMessage, $mobile);
        }

        $sessionId = (string) Str::uuid();
        $plain = (string) random_int(100000, 999999);
        $now = now()->getTimestamp();

        $payload = [
            'customer_id' => (int) $customer->id,
            'username' => (string) $customer->username,
            'remember' => $remember,
            'ip' => (string) $request->ip(),
            'ua_hash' => $this->userAgentHash($request),
            'otp_hash' => $this->hashOtp($plain),
            'otp_attempts' => 0,
            'otp_sent_at' => $now,
            'resend_count' => 0,
            'mobile' => $mobile,
            'bogus' => false,
        ];

        $this->putPending($sessionId, $payload);

        $result = $this->rawSms->send($mobile, $this->buildSmsBody($plain), 'customer-login-otp');
        if (! $result['ok']) {
            Cache::forget($this->pendingKey($sessionId));
            throw new \RuntimeException((string) ($result['message'] ?? 'sms_failed'));
        }

        $this->markSendAttempt($request);

        return [
            'login_session' => $sessionId,
            'message' => $genericMessage,
            'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
            'masked_mobile' => $this->maskMobile($mobile),
        ];
    }

    /**
     * @return array{message: string, resend_available_in: int}
     */
    public function resend(string $loginSession, Request $request): array
    {
        $sessionId = trim($loginSession);
        if ($sessionId === '' || ! Str::isUuid($sessionId)) {
            throw new \InvalidArgumentException('invalid_session');
        }

        $this->ensureResendNotLocked($request, $sessionId);

        $key = $this->pendingKey($sessionId);
        $data = Cache::get($key);
        if (! is_array($data) || ! $this->sessionMatchesRequest($data, $request)) {
            throw new \InvalidArgumentException('invalid_session');
        }

        if (! empty($data['bogus'])) {
            $this->hitResendThrottle($request, $sessionId);

            return [
                'message' => 'کد جدید ارسال شد.',
                'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
            ];
        }

        $resendCount = (int) ($data['resend_count'] ?? 0);
        if ($resendCount >= self::MAX_RESENDS) {
            Cache::forget($key);
            throw new \InvalidArgumentException('resend_limit');
        }

        $sentAt = (int) ($data['otp_sent_at'] ?? 0);
        $elapsed = now()->getTimestamp() - $sentAt;
        if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
            return [
                'message' => 'لطفاً تا پایان شمارش معکوس صبر کنید.',
                'resend_available_in' => self::RESEND_COOLDOWN_SECONDS - $elapsed,
            ];
        }

        $mobile = (string) ($data['mobile'] ?? '');
        if ($mobile === '') {
            throw new \InvalidArgumentException('invalid_session');
        }

        $plain = (string) random_int(100000, 999999);
        $data['otp_hash'] = $this->hashOtp($plain);
        $data['otp_attempts'] = 0;
        $data['otp_sent_at'] = now()->getTimestamp();
        $data['resend_count'] = $resendCount + 1;
        $this->putPending($sessionId, $data);

        $result = $this->rawSms->send($mobile, $this->buildSmsBody($plain), 'customer-login-otp');
        if (! $result['ok']) {
            throw new \RuntimeException((string) ($result['message'] ?? 'sms_failed'));
        }

        $this->hitResendThrottle($request, $sessionId);

        return [
            'message' => 'کد جدید ارسال شد.',
            'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
        ];
    }

    /**
     * @return array{customer: Customer, remember: bool, username: string}
     */
    public function verify(string $loginSession, string $code, Request $request): array
    {
        $sessionId = trim($loginSession);
        if ($sessionId === '' || ! Str::isUuid($sessionId)) {
            throw new \InvalidArgumentException('invalid_session');
        }

        $this->ensureVerifyNotLocked($request, $sessionId);

        $key = $this->pendingKey($sessionId);
        $data = Cache::get($key);
        if (! is_array($data) || ! $this->sessionMatchesRequest($data, $request)) {
            throw new \InvalidArgumentException('invalid_session');
        }

        $attempts = (int) ($data['otp_attempts'] ?? 0);
        if ($attempts >= self::MAX_OTP_ATTEMPTS) {
            Cache::forget($key);
            throw new \InvalidArgumentException('attempts_exceeded');
        }

        if (! empty($data['bogus'])) {
            $this->bumpAttempts($sessionId, $data);
            $this->hitVerifyThrottle($request, $sessionId);
            throw new \InvalidArgumentException('invalid_code');
        }

        $codeDigits = preg_replace('/\D/', '', $this->toEnglishDigits(trim($code))) ?? '';
        if (strlen($codeDigits) !== 6) {
            $this->bumpAttempts($sessionId, $data);
            $this->hitVerifyThrottle($request, $sessionId);
            throw new \InvalidArgumentException('invalid_code');
        }

        $expected = (string) ($data['otp_hash'] ?? '');
        $actual = $this->hashOtp($codeDigits);
        if ($expected === '' || ! hash_equals($expected, $actual)) {
            $this->bumpAttempts($sessionId, $data);
            $this->hitVerifyThrottle($request, $sessionId);
            throw new \InvalidArgumentException('invalid_code');
        }

        Cache::forget($key);

        $customer = Customer::query()->whereKey((int) ($data['customer_id'] ?? 0))->first();
        if ($customer === null) {
            throw new \InvalidArgumentException('invalid_session');
        }

        return [
            'customer' => $customer,
            'remember' => (bool) ($data['remember'] ?? false),
            'username' => (string) ($data['username'] ?? $customer->username),
        ];
    }

    /**
     * پاسخ یک‌شکل برای شماره‌های نامعتبر/ناشناخته تا امکان شمارش کاربران کاهش یابد.
     *
     * @return array{login_session: string, message: string, resend_available_in: int, masked_mobile: ?string}
     */
    private function issueOpaqueChallenge(Request $request, bool $remember, string $message, ?string $mobile): array
    {
        $sessionId = (string) Str::uuid();
        $this->putPending($sessionId, [
            'customer_id' => 0,
            'username' => '',
            'remember' => $remember,
            'ip' => (string) $request->ip(),
            'ua_hash' => $this->userAgentHash($request),
            'otp_hash' => $this->hashOtp((string) random_int(100000, 999999)),
            'otp_attempts' => 0,
            'otp_sent_at' => now()->getTimestamp(),
            'resend_count' => 0,
            'mobile' => $mobile ?? '',
            'bogus' => true,
        ]);

        return [
            'login_session' => $sessionId,
            'message' => $message,
            'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
            'masked_mobile' => $mobile !== null ? $this->maskMobile($mobile) : null,
        ];
    }

    private function pendingKey(string $sessionId): string
    {
        return 'customer_login_otp:'.hash('sha256', $sessionId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putPending(string $sessionId, array $payload): void
    {
        Cache::put($this->pendingKey($sessionId), $payload, now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bumpAttempts(string $sessionId, array $data): void
    {
        $data['otp_attempts'] = (int) ($data['otp_attempts'] ?? 0) + 1;
        $this->putPending($sessionId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sessionMatchesRequest(array $data, Request $request): bool
    {
        if ((string) ($data['ip'] ?? '') !== (string) $request->ip()) {
            return false;
        }

        return (string) ($data['ua_hash'] ?? '') === $this->userAgentHash($request);
    }

    private function userAgentHash(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent());
    }

    private function hashOtp(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('app.key'));
    }

    private function buildSmsBody(string $plainCode): string
    {
        $appName = $this->appDisplayName();

        return 'کد ورود به '.$appName.chr(10)
            .$plainCode.chr(10)
            .'اعتبار '.$this->toPersianDigits((string) self::OTP_TTL_MINUTES).' دقیقه؛ در اختیار دیگران نگذارید.';
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

        return mb_substr(str_replace(["\r", "\n"], '', $t), 0, 200);
    }

    private function maskMobile(string $mobile): string
    {
        if (strlen($mobile) !== 11) {
            return '۰۹** *** **۰۰';
        }

        return $this->toPersianDigits(substr($mobile, 0, 4).'***'.substr($mobile, -4));
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

    private function sendThrottleKey(Request $request): string
    {
        return 'customer-login-otp-send|'.sha1((string) $request->ip());
    }

    private function resendThrottleKey(Request $request, string $sessionId): string
    {
        return 'customer-login-otp-resend|'.sha1((string) $request->ip().'|'.$sessionId);
    }

    private function verifyThrottleKey(Request $request, string $sessionId): string
    {
        return 'customer-login-otp-verify|'.sha1((string) $request->ip().'|'.$sessionId);
    }

    private function ensureResendNotLocked(Request $request, string $sessionId): void
    {
        $key = $this->resendThrottleKey($request, $sessionId);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw new \RuntimeException('rate_limited:'.RateLimiter::availableIn($key));
        }
    }

    private function hitResendThrottle(Request $request, string $sessionId): void
    {
        RateLimiter::hit($this->resendThrottleKey($request, $sessionId), 60 * 15);
    }

    private function ensureVerifyNotLocked(Request $request, string $sessionId): void
    {
        $key = $this->verifyThrottleKey($request, $sessionId);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw new \RuntimeException('rate_limited:'.RateLimiter::availableIn($key));
        }
    }

    private function hitVerifyThrottle(Request $request, string $sessionId): void
    {
        RateLimiter::hit($this->verifyThrottleKey($request, $sessionId), 60 * 10);
    }
}
