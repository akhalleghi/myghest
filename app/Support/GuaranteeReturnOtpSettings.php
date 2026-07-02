<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class GuaranteeReturnOtpSettings
{
    public const SETTING_KEY = 'guarantee_return_customer_otp_enabled';

    public static function isEnabled(): bool
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_string($value) && $value === '1';
    }
}
