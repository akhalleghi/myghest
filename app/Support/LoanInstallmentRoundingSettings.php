<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class LoanInstallmentRoundingSettings
{
    public const SETTING_KEY_REMAINDER_TARGET = 'loan_installment_remainder_target';

    public const SETTING_KEY_ROUNDING_STEP = 'loan_installment_rounding_step_toman';

    public const REMAINDER_LAST = 'last';

    public const REMAINDER_FIRST = 'first';

    public const REMAINDER_DOWN_PAYMENT = 'down_payment';

    public const REMAINDER_DISTRIBUTE = 'distribute';

    /** پیش‌فرض سازگار با رفتار قبلی سامانه */
    public const ROUNDING_STEP_TOMAN = 10_000;

    public const ROUNDING_STEP_MIN_TOMAN = 1;

    public const ROUNDING_STEP_MAX_TOMAN = 10_000_000;

    /**
     * @return list<string>
     */
    public static function remainderTargetOptions(): array
    {
        return [
            self::REMAINDER_LAST,
            self::REMAINDER_FIRST,
            self::REMAINDER_DOWN_PAYMENT,
            self::REMAINDER_DISTRIBUTE,
        ];
    }

    /**
     * مقادیر پیشنهادی برای انتخاب سریع؛ ورود دستی هر عدد معتبر هم مجاز است.
     *
     * @return list<int>
     */
    public static function roundingStepPresets(): array
    {
        return [1, 1_000, 5_000, 10_000, 50_000, 100_000];
    }

    /**
     * @deprecated Use roundingStepPresets()
     *
     * @return list<int>
     */
    public static function roundingStepOptions(): array
    {
        return self::roundingStepPresets();
    }

    public static function remainderTarget(): string
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY_REMAINDER_TARGET)->value('value');
        if (! is_string($value) || ! in_array($value, self::remainderTargetOptions(), true)) {
            return self::REMAINDER_LAST;
        }

        return $value;
    }

    public static function roundingStepToman(): int
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY_ROUNDING_STEP)->value('value');
        if (! is_numeric($value)) {
            return self::ROUNDING_STEP_TOMAN;
        }

        return self::normalizeRoundingStep((int) $value);
    }

    /**
     * نرمال‌سازی حد رند (۱ تا سقف مجاز)؛ مقادیر نامعتبر به پیش‌فرض برمی‌گردند.
     */
    public static function normalizeRoundingStep(int $step): int
    {
        if ($step < self::ROUNDING_STEP_MIN_TOMAN || $step > self::ROUNDING_STEP_MAX_TOMAN) {
            return self::ROUNDING_STEP_TOMAN;
        }

        return $step;
    }

    public static function isValidRoundingStep(int $step): bool
    {
        return $step >= self::ROUNDING_STEP_MIN_TOMAN && $step <= self::ROUNDING_STEP_MAX_TOMAN;
    }

    public static function remainderTargetLabel(string $target): string
    {
        return match ($target) {
            self::REMAINDER_FIRST => 'قسط اول',
            self::REMAINDER_DOWN_PAYMENT => 'پیش‌پرداخت',
            self::REMAINDER_DISTRIBUTE => 'تقسیم بر اقساط',
            default => 'قسط آخر',
        };
    }

    public static function roundingStepLabel(int $step): string
    {
        if ($step <= 1) {
            return 'بدون رند (۱ تومان)';
        }

        return number_format($step, 0, '.', ',').' تومان';
    }

    /**
     * @return array{step_toman: int, remainder_target: string, remainder_target_label: string}
     */
    public static function clientConfig(): array
    {
        $target = self::remainderTarget();

        return [
            'step_toman' => self::roundingStepToman(),
            'remainder_target' => $target,
            'remainder_target_label' => self::remainderTargetLabel($target),
        ];
    }
}
