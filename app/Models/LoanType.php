<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $profit_calculation_method
 * @property string $interest_rate
 * @property string $daily_late_coefficient
 * @property string $daily_early_coefficient
 * @property int|null $max_loan_amount
 * @property int|null $max_installment_gap
 * @property string $installment_gap_unit
 * @property array{type: string, max_months?: int|null, allowed_rows?: list<array{months: int, cap: float|int}>} $repayment_periods
 * @property bool $sms_reminder_enabled
 * @property bool $registration_suspended
 * @property string|null $registration_suspended_message
 * @property bool $plan_list_enabled
 * @property string|null $plan_image_path
 * @property string|null $plan_title
 * @property string|null $plan_summary
 * @property string|null $plan_body
 */
final class LoanType extends Model
{
    public const PROFIT_MONTHLY = 'monthly';

    public const PROFIT_BANK = 'bank';

    public const GAP_MONTHLY = 'monthly';

    public const GAP_WEEKLY = 'weekly';

    public const REPAY_UNLIMITED = 'unlimited';

    public const REPAY_MAX_UNTIL = 'max_until';

    public const REPAY_ALLOWED_MONTHS = 'allowed_months';

    protected $fillable = [
        'title',
        'profit_calculation_method',
        'interest_rate',
        'daily_late_coefficient',
        'daily_early_coefficient',
        'max_loan_amount',
        'max_installment_gap',
        'installment_gap_unit',
        'repayment_periods',
        'sms_reminder_enabled',
        'registration_suspended',
        'registration_suspended_message',
        'plan_list_enabled',
        'plan_image_path',
        'plan_title',
        'plan_summary',
        'plan_body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'daily_late_coefficient' => 'decimal:6',
            'daily_early_coefficient' => 'decimal:6',
            'max_loan_amount' => 'integer',
            'max_installment_gap' => 'integer',
            'repayment_periods' => 'array',
            'sms_reminder_enabled' => 'boolean',
            'registration_suspended' => 'boolean',
            'plan_list_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::deleting(function (LoanType $loanType): void {
            if ($loanType->plan_image_path) {
                Storage::disk('public')->delete($loanType->plan_image_path);
            }
        });
    }

    public function planImagePublicUrl(): ?string
    {
        if (! $this->plan_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->plan_image_path);
    }

    public function profitCalculationLabel(): string
    {
        return match ($this->profit_calculation_method) {
            self::PROFIT_MONTHLY => 'سود ماهانه',
            self::PROFIT_BANK => 'سود بانکی',
            default => $this->profit_calculation_method,
        };
    }

    public function installmentGapLabel(): string
    {
        return match ($this->installment_gap_unit) {
            self::GAP_MONTHLY => 'ماهانه',
            self::GAP_WEEKLY => 'هفتگی',
            default => $this->installment_gap_unit,
        };
    }
}
