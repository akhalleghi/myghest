<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class LoanCreationOtpSettings
{
    public const SETTING_KEY = 'loan_creation_customer_otp_enabled';

    public static function isEnabled(): bool
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_string($value) && $value === '1';
    }
}
