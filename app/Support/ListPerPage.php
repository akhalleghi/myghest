<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * تعداد ردیف مجاز در هر صفحهٔ لیست‌های سامانه.
 */
final class ListPerPage
{
    /** @var list<int> */
    public const ALLOWED = [10, 15, 20, 25, 50];

    public const DEFAULT = 15;

    public const MAX = 50;

    public static function resolve(?Request $request = null, ?int $default = null): int
    {
        $fallback = $default ?? self::DEFAULT;
        if ($request === null) {
            return $fallback;
        }

        $raw = $request->query('per_page', $fallback);
        $perPage = is_numeric($raw) ? (int) $raw : $fallback;

        return in_array($perPage, self::ALLOWED, true) ? $perPage : $fallback;
    }

    /**
     * تعداد در صفحه با سقف عددی امن (برای لیست‌هایی که عدد دلخواه می‌پذیرند).
     * رفتار resolve() پیش‌فرض را تغییر نمی‌دهد.
     */
    public static function resolveBounded(?Request $request = null, int $max = 100, ?int $default = null): int
    {
        $fallback = $default ?? self::DEFAULT;
        $max = max(1, $max);
        $fallback = min(max(1, $fallback), $max);

        if ($request === null) {
            return $fallback;
        }

        $raw = $request->query('per_page', $fallback);
        if (! is_numeric($raw)) {
            return $fallback;
        }

        $perPage = (int) $raw;
        if ($perPage < 1) {
            return $fallback;
        }

        return min($perPage, $max);
    }

    /**
     * محدود کردن عدد خام به بازهٔ مجاز (برای ترجیحات ذخیره‌شده).
     */
    public static function clamp(int $perPage, int $max = 100, ?int $default = null): int
    {
        $max = max(1, $max);
        $fallback = min(max(1, $default ?? self::DEFAULT), $max);
        if ($perPage < 1) {
            return $fallback;
        }

        return min($perPage, $max);
    }

    /**
     * @return list<int>
     */
    public static function allowedOptions(): array
    {
        return self::ALLOWED;
    }
}
