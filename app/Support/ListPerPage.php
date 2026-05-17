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
     * @return list<int>
     */
    public static function allowedOptions(): array
    {
        return self::ALLOWED;
    }
}
