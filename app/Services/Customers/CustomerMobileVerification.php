<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\AppSetting;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\JalaliInputParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerMobileVerification
{
    public function mobile(string $value): string
    {
        $value = trim(JalaliInputParser::toEnglishDigits($value));
        if (! preg_match('/^09[0-9]{9}$/D', $value)) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل معتبر با قالب 09 وارد کنید.']);
        }

        return $value;
    }

    public function preview(): string
    {
        $appName = trim((string) AppSetting::query()->where('key', 'app_display_name')->value('value'));

        return 'کد تأیید شماره موبایل شما در '.($appName !== '' ? $appName : config('app.name')).": {{code}}\nاین کد را فقط برای تأیید شماره موبایل هنگام ثبت مشتری ارائه کنید.";
    }

    public function send(int $adminId, string $mobile, string $text): array
    {
        if (! preg_match('/\{\{\s*code\s*\}\}/i', $text)) {
            throw ValidationException::withMessages(['sms_text' => 'عبارت {{code}} را در متن پیامک حفظ کنید.']);
        }
        $cooldown = 'customer_mobile_cooldown:'.hash('sha256', $mobile);
        if (! Cache::add($cooldown, true, 60)) {
            throw ValidationException::withMessages(['mobile' => 'برای ارسال مجدد یک دقیقه صبر کنید.']);
        }
        $code = (string) random_int(100000, 999999);
        $id = (string) Str::uuid();
        $key = 'customer_mobile_otp:'.$id;
        Cache::put($key, ['hash' => Hash::make($code), 'mobile' => $mobile, 'admin_id' => $adminId, 'attempts' => 0, 'expires' => now()->addMinutes(10)->timestamp], now()->addMinutes(10));
        try {
            $result = app(RawSmsDispatcher::class)->send($mobile, preg_replace('/\{\{\s*code\s*\}\}/i', $code, $text), 'customer-mobile-verification');
        } catch (\Throwable $error) {
            Cache::forget($key);
            Cache::forget($cooldown);
            throw $error;
        }
        if (! $result['ok']) {
            Cache::forget($key);
            Cache::forget($cooldown);
            throw ValidationException::withMessages(['mobile' => $result['message']]);
        }

        return ['otp_session' => $id, 'message' => 'کد تأیید ارسال شد؛ اعتبار کد ۱۰ دقیقه است.'];
    }

    public function verify(int $adminId, string $mobile, string $id, string $code): array
    {
        return Cache::lock('customer_mobile_verify:'.$id, 10)->block(3, function () use ($adminId, $mobile, $id, $code): array {
            $key = 'customer_mobile_otp:'.$id;
            $data = Cache::get($key);
            if (! is_array($data) || $data['admin_id'] !== $adminId || $data['mobile'] !== $mobile || $data['expires'] <= now()->timestamp) {
                throw ValidationException::withMessages(['code' => 'درخواست کد معتبر نیست؛ دوباره کد دریافت کنید.']);
            }
            if (! Hash::check(JalaliInputParser::toEnglishDigits(trim($code)), $data['hash'])) {
                $data['attempts']++;
                if ($data['attempts'] >= 5) {
                    Cache::forget($key);
                } else {
                    Cache::put($key, $data, now()->setTimestamp($data['expires']));
                }
                throw ValidationException::withMessages(['code' => 'کد صحیح نیست؛ پس از پنج تلاش باید کد جدید دریافت کنید.']);
            }
            $token = Str::random(48);
            Cache::put('customer_mobile_verified:'.$token, ['admin_id' => $adminId, 'mobile' => $mobile, 'verified_at' => now()->toDateTimeString()], now()->addMinutes(30));
            Cache::forget($key);

            return ['verification_token' => $token, 'message' => 'شماره موبایل تأیید شد.'];
        });
    }

    public function proof(int $adminId, string $mobile, string $token): string
    {
        $data = Cache::get('customer_mobile_verified:'.$token);
        if (! is_array($data) || $data['admin_id'] !== $adminId || $data['mobile'] !== $mobile) {
            throw ValidationException::withMessages(['mobile' => 'تأیید شماره موبایل معتبر نیست یا منقضی شده است؛ مجدداً تأیید کنید.']);
        }

        return $data['verified_at'];
    }

    public function consume(string $token): void
    {
        Cache::forget('customer_mobile_verified:'.$token);
    }
}
