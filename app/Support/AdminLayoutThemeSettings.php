<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * رنگ‌های چیدمان پنل ادمین (منوی کناری و باکس‌های محتوا) — منبع واحد برای تمام صفحات.
 */
final class AdminLayoutThemeSettings
{
    public const SETTING_KEY = 'admin_layout_theme';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'sidebar_from' => '#1e293b',
            'sidebar_to' => '#0f172a',
            'sidebar_text' => '#f8fafc',
            'sidebar_nav' => '#cbd5e1',
            'sidebar_accent' => '#60a5fa',
            'content_from' => '#ffffff',
            'content_to' => '#eef4ff',
            'content_border' => '#2563eb',
            'sidebar_from_dark' => '#111827',
            'sidebar_to_dark' => '#0f172a',
            'sidebar_text_dark' => '#f1f5f9',
            'sidebar_nav_dark' => '#cbd5e1',
            'sidebar_accent_dark' => '#60a5fa',
            'content_from_dark' => '#182236',
            'content_to_dark' => '#141c2c',
            'content_border_dark' => '#3b82f6',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function resolved(): array
    {
        $raw = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($stored)) {
            $stored = [];
        }

        $defaults = self::defaults();
        $merged = array_merge($defaults, array_intersect_key($stored, $defaults));

        foreach ($merged as $key => $value) {
            $merged[$key] = self::normalizeHex((string) $value, $defaults[$key]);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(array $input): void
    {
        $defaults = self::defaults();
        $out = [];

        foreach ($defaults as $key => $default) {
            $out[$key] = self::normalizeHex((string) ($input[$key] ?? ''), $default);
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($out, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function normalizeHex(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
            $chars = str_split($m[1]);

            return '#'.strtolower($chars[0].$chars[0].$chars[1].$chars[1].$chars[2].$chars[2]);
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }

        return strtolower($fallback);
    }
}
