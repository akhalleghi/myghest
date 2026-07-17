<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class CustomerLoginSecuritySettings
{
    public const SETTING_KEY = 'customer_login_two_factor_enabled';

    public const SMS_OTP_LOGIN_SETTING_KEY = 'customer_login_sms_otp_enabled';

    public static function isTwoFactorEnabled(): bool
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_string($value) && $value === '1';
    }

    public static function isSmsOtpLoginEnabled(): bool
    {
        $value = AppSetting::query()->where('key', self::SMS_OTP_LOGIN_SETTING_KEY)->value('value');

        return is_string($value) && $value === '1';
    }
}
