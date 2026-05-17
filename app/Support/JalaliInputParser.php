<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

final class JalaliInputParser
{
    public static function toCarbonDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = self::toEnglishDigits($value);

        try {
            $timezone = (string) config('app.timezone', 'Asia/Tehran');
            $j = Jalali::parseFormat('Y/m/d', $value, $timezone);
            [$gregorianYear, $gregorianMonth, $gregorianDay] = Jalali::jalaliToGregorian(
                (int) $j->year,
                (int) $j->month,
                (int) $j->day,
            );

            return Carbon::create(
                $gregorianYear,
                $gregorianMonth,
                $gregorianDay,
                0,
                0,
                0,
                $timezone,
            )->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }
}
