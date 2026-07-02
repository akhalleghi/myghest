<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class LoanInstallmentRoundingSettings
{
    public const SETTING_KEY_REMAINDER_TARGET = 'loan_installment_remainder_target';

    public const REMAINDER_LAST = 'last';

    public const REMAINDER_FIRST = 'first';

    public const REMAINDER_DOWN_PAYMENT = 'down_payment';

    public const ROUNDING_STEP_TOMAN = 10_000;

    /**
     * @return list<string>
     */
    public static function remainderTargetOptions(): array
    {
        return [
            self::REMAINDER_LAST,
            self::REMAINDER_FIRST,
            self::REMAINDER_DOWN_PAYMENT,
        ];
    }

    public static function remainderTarget(): string
    {
        $value = AppSetting::query()->where('key', self::SETTING_KEY_REMAINDER_TARGET)->value('value');
        if (! is_string($value) || ! in_array($value, self::remainderTargetOptions(), true)) {
            return self::REMAINDER_LAST;
        }

        return $value;
    }

    public static function remainderTargetLabel(string $target): string
    {
        return match ($target) {
            self::REMAINDER_FIRST => 'قسط اول',
            self::REMAINDER_DOWN_PAYMENT => 'پیش‌پرداخت',
            default => 'قسط آخر',
        };
    }

    /**
     * @return array{step_toman: int, remainder_target: string, remainder_target_label: string}
     */
    public static function clientConfig(): array
    {
        $target = self::remainderTarget();

        return [
            'step_toman' => self::ROUNDING_STEP_TOMAN,
            'remainder_target' => $target,
            'remainder_target_label' => self::remainderTargetLabel($target),
        ];
    }
}
