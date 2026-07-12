<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * تنظیمات چاپ دفترچه اقساط پرونده وام.
 */
final class InstallmentBookletPrintSettings
{
    public const SETTING_KEY = 'installment_booklet_print';

    /**
     * @return array<string, string>
     */
    public static function scalarDefaults(): array
    {
        return [
            'title_main' => 'دفترچه اقساط',
            'subtitle' => 'فروش اقساطی',
            'loan_amount_label' => 'مبلغ وام خرید پوشاک',
            'show_logo' => '1',
            'use_app_logo' => '1',
            'logo_path' => '',
            'show_loan_amount' => '1',
            'show_summary_table' => '1',
            'show_detail_table' => '1',
            'show_portal_block' => '1',
            'show_username' => '1',
            'show_password' => '1',
            'password_unavailable_text' => '—',
            'portal_intro_text' => 'آدرس سایت جهت اطلاع از وضعیت اقساط و پرداخت آنلاین:',
            'username_label' => 'نام کاربری:',
            'password_label' => 'رمز عبور:',
            'show_signatures' => '1',
            'seller_signature_label' => 'امضا و اثر انگشت فروشنده',
            'buyer_signature_label' => 'امضا و اثر انگشت خریدار',
            'font_scale' => 'normal',
        ];
    }

    /**
     * @return array<string, array{show: string, label: string}>
     */
    public static function columnDefaults(): array
    {
        return [
            'sequence' => ['show' => '1', 'label' => 'شماره قسط'],
            'due_date' => ['show' => '1', 'label' => 'تاریخ سررسید'],
            'amount_due' => ['show' => '1', 'label' => 'مبلغ قسط'],
            'pay_dates' => ['show' => '1', 'label' => 'تاریخ پرداخت'],
            'amounts_paid' => ['show' => '1', 'label' => 'مبالغ پرداختی'],
            'early' => ['show' => '1', 'label' => 'زود کرد'],
            'late' => ['show' => '1', 'label' => 'دیرکرد'],
            'penalty' => ['show' => '1', 'label' => 'جریمه'],
            'online' => ['show' => '1', 'label' => 'آنلاین'],
            'gateway' => ['show' => '1', 'label' => 'درگاه پرداخت'],
            'cash' => ['show' => '1', 'label' => 'نقدی'],
            'transfer' => ['show' => '1', 'label' => 'واریزی'],
            'terminal' => ['show' => '1', 'label' => 'کارتخوان'],
            'notes' => ['show' => '1', 'label' => 'توضیحات'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolved(): array
    {
        $raw = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($stored)) {
            $stored = [];
        }

        $scalars = self::scalarDefaults();
        foreach ($scalars as $key => $default) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }
            $scalars[$key] = self::normalizeScalar($key, (string) $stored[$key], $default);
        }

        $columns = self::columnDefaults();
        $storedColumns = is_array($stored['columns'] ?? null) ? $stored['columns'] : [];
        foreach ($columns as $columnKey => $defaults) {
            $incoming = is_array($storedColumns[$columnKey] ?? null) ? $storedColumns[$columnKey] : [];
            $columns[$columnKey] = [
                'show' => self::normalizeBoolString((string) ($incoming['show'] ?? $defaults['show']), $defaults['show']),
                'label' => self::normalizeLabel((string) ($incoming['label'] ?? $defaults['label']), $defaults['label']),
            ];
        }

        return array_merge($scalars, ['columns' => $columns]);
    }

    /**
     * @return list<string>
     */
    public static function orderedColumnKeys(): array
    {
        return array_keys(self::columnDefaults());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(array $input): void
    {
        $scalars = self::scalarDefaults();
        $out = [];

        foreach ($scalars as $key => $default) {
            if ($key === 'logo_path') {
                continue;
            }
            $out[$key] = self::normalizeScalar($key, (string) ($input[$key] ?? ''), $default);
        }

        $out['logo_path'] = trim((string) ($input['logo_path'] ?? $scalars['logo_path']));

        $columns = self::columnDefaults();
        $incomingColumns = is_array($input['columns'] ?? null) ? $input['columns'] : [];
        $out['columns'] = [];
        foreach ($columns as $columnKey => $defaults) {
            $columnInput = is_array($incomingColumns[$columnKey] ?? null) ? $incomingColumns[$columnKey] : [];
            $out['columns'][$columnKey] = [
                'show' => self::normalizeBoolString((string) ($columnInput['show'] ?? ''), $defaults['show']),
                'label' => self::normalizeLabel((string) ($columnInput['label'] ?? ''), $defaults['label']),
            ];
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($out, JSON_UNESCAPED_UNICODE)],
        );
    }

    public static function logoUrl(array $settings, ?string $appLogoPath = null): ?string
    {
        if (($settings['show_logo'] ?? '0') !== '1') {
            return null;
        }

        $bookletLogo = trim((string) ($settings['logo_path'] ?? ''));
        if ($bookletLogo !== '') {
            return asset($bookletLogo);
        }

        if (($settings['use_app_logo'] ?? '0') === '1' && is_string($appLogoPath) && trim($appLogoPath) !== '') {
            return asset(trim($appLogoPath));
        }

        return null;
    }

    public static function bodyFontSize(array $settings): string
    {
        return match ($settings['font_scale'] ?? 'normal') {
            'small' => '9pt',
            'large' => '11pt',
            default => '10pt',
        };
    }

    private static function normalizeScalar(string $key, string $value, string $fallback): string
    {
        if (in_array($key, ['show_logo', 'use_app_logo', 'show_loan_amount', 'show_summary_table', 'show_detail_table', 'show_portal_block', 'show_username', 'show_password', 'show_signatures'], true)) {
            return self::normalizeBoolString($value, $fallback);
        }

        if ($key === 'font_scale') {
            return in_array($value, ['small', 'normal', 'large'], true) ? $value : $fallback;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    private static function normalizeBoolString(string $value, string $fallback): string
    {
        return in_array($value, ['0', '1'], true) ? $value : $fallback;
    }

    private static function normalizeLabel(string $value, string $fallback): string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? mb_substr($trimmed, 0, 80) : $fallback;
    }
}
