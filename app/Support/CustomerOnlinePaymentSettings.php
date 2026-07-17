<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * فعال/غیرفعال بودن پرداخت آنلاین (درگاه) برای پنل مشتری.
 * در صورت نبودن کلید در دیتابیس، پیش‌فرض فعال است تا نصب‌های قبلی مختل نشوند.
 */
final class CustomerOnlinePaymentSettings
{
    public const SETTING_KEY = 'customer_online_payment_enabled';

    public static function isEnabled(): bool
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');

        if (! is_string($value) || $value === '') {
            return true;
        }

        return $value === '1';
    }
}
