<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نرمال‌سازی شماره موبایل ایران برای ارسال پیامک.
 */
final class IranMobile
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $raw)) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) === 1 ? $digits : null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
