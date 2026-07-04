<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * تنظیمات نمایش جداول گزارش‌های پنل ادمین.
 */
final class AdminReportsDisplaySettings
{
    public const SETTING_KEY = 'admin_reports_display';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'font_scale' => 'normal',
            'text_align' => 'right',
            'numeric_align' => 'center',
            'header_mode' => 'match',
            'stack_align' => 'center',
            'cell_density' => 'normal',
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
            $merged[$key] = self::normalizeChoice($key, (string) $value, $defaults[$key]);
        }

        return $merged;
    }

    /**
     * @param  array<string, string>|null  $settings
     */
    public static function inlineStyle(?array $settings = null): string
    {
        $vars = self::cssVariableMap($settings);

        return implode('; ', array_map(
            static fn (string $key, string $value): string => $key.': '.$value,
            array_keys($vars),
            array_values($vars),
        ));
    }

    /**
     * @param  array<string, string>|null  $settings
     * @return array<string, string>
     */
    public static function cssVariableMap(?array $settings = null): array
    {
        $s = $settings ?? self::resolved();

        $fontBase = match ($s['font_scale']) {
            'small' => 0.62,
            'large' => 0.74,
            'xlarge' => 0.8,
            default => 0.67,
        };

        [$cellPy, $cellPx] = match ($s['cell_density']) {
            'compact' => [0.22, 0.22],
            'comfortable' => [0.42, 0.38],
            default => [0.32, 0.28],
        };

        $textAlign = $s['text_align'] === 'center' ? 'center' : 'right';
        $numAlign = $s['numeric_align'] === 'right' ? 'right' : 'center';
        $stackItems = $s['stack_align'] === 'start' ? 'flex-start' : 'center';
        $headerAlign = match ($s['header_mode']) {
            'right' => 'right',
            'center' => 'center',
            default => 'center',
        };

        return [
            '--rpt-font-base' => $fontBase.'rem',
            '--rpt-font-th' => round($fontBase * 0.96, 3).'rem',
            '--rpt-font-num' => round($fontBase * 0.96, 3).'rem',
            '--rpt-font-stack' => round($fontBase * 0.99, 3).'rem',
            '--rpt-font-link' => round($fontBase * 0.99, 3).'rem',
            '--rpt-cell-py' => $cellPy.'rem',
            '--rpt-cell-px' => $cellPx.'rem',
            '--rpt-td-align' => $textAlign,
            '--rpt-num-align' => $numAlign,
            '--rpt-th-align' => $headerAlign,
            '--rpt-stack-items' => $stackItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(array $input): void
    {
        $defaults = self::defaults();
        $out = [];

        foreach ($defaults as $key => $default) {
            $out[$key] = self::normalizeChoice($key, (string) ($input[$key] ?? ''), $default);
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($out, JSON_UNESCAPED_UNICODE)],
        );
    }

    private static function normalizeChoice(string $key, string $value, string $fallback): string
    {
        $allowed = match ($key) {
            'font_scale' => ['small', 'normal', 'large', 'xlarge'],
            'text_align', 'numeric_align' => ['right', 'center'],
            'header_mode' => ['match', 'center', 'right'],
            'stack_align' => ['start', 'center'],
            'cell_density' => ['compact', 'normal', 'comfortable'],
            default => [$fallback],
        };

        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
