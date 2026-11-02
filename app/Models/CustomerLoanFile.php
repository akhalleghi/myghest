<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerLoanFile extends Model
{
    protected static function booted(): void
    {
        self::creating(function (self $file): void {
            // Contract rates are snapshots, including when the administrator uses the plan defaults.
            if ($file->daily_late_coefficient !== null && $file->daily_early_coefficient !== null) {
                return;
            }
            $type = LoanType::query()->find($file->loan_type_id, ['id', 'daily_late_coefficient', 'daily_early_coefficient']);
            $file->daily_late_coefficient ??= $type?->daily_late_coefficient ?? 0;
            $file->daily_early_coefficient ??= $type?->daily_early_coefficient ?? 0;
        });
    }

    protected $fillable = [
        'customer_id',
        'loan_type_id',
        'loan_code',
        'loan_start_date',
        'disbursement_due_date',
        'amount_toman',
        'installments_count',
        'installment_interval_count',
        'installment_interval_unit',
        'installment_amount_toman',
        'down_payment_toman',
        'profit_calculation_method',
        'sub_file_number',
        'description',
        'is_settled',
        'settled_at',
        'revoked_at',
        'revoked_by_admin_id',
        'discount_amount_toman',
        'discount_updated_at',
        'discount_updated_by_admin_id',
        'base_interest_rate',
        'has_custom_interest_rate',
        'custom_interest_rate',
        'effective_interest_rate',
        'daily_late_coefficient',
        'daily_early_coefficient',
        'created_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loan_start_date' => 'date',
            'disbursement_due_date' => 'date',
            'amount_toman' => 'integer',
            'installments_count' => 'integer',
            'installment_interval_count' => 'integer',
            'installment_amount_toman' => 'integer',
            'down_payment_toman' => 'integer',
            'profit_calculation_method' => 'string',
            'is_settled' => 'boolean',
            'settled_at' => 'date',
            'revoked_at' => 'datetime',
            'revoked_by_admin_id' => 'integer',
            'discount_amount_toman' => 'integer',
            'discount_updated_at' => 'datetime',
            'discount_updated_by_admin_id' => 'integer',
            'base_interest_rate' => 'decimal:2',
            'has_custom_interest_rate' => 'boolean',
            'custom_interest_rate' => 'decimal:2',
            'effective_interest_rate' => 'decimal:2',
            'daily_late_coefficient' => 'decimal:6',
            'daily_early_coefficient' => 'decimal:6',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function dailyLateCoefficient(): float
    {
        $coefficient = $this->getAttribute('daily_late_coefficient');
        if ($coefficient !== null) {
            return (float) $coefficient;
        }

        $loanType = $this->loanType;
        $coefficient = $loanType?->getAttribute('daily_late_coefficient');
        if ($loanType !== null && $coefficient === null) {
            $coefficient = $this->loanType()->value('daily_late_coefficient');
        }

        return (float) ($coefficient ?? 0);
    }

    public function dailyEarlyCoefficient(): float
    {
        $coefficient = $this->getAttribute('daily_early_coefficient');
        if ($coefficient !== null) {
            return (float) $coefficient;
        }

        $loanType = $this->loanType;
        $coefficient = $loanType?->getAttribute('daily_early_coefficient');
        if ($loanType !== null && $coefficient === null) {
            $coefficient = $this->loanType()->value('daily_early_coefficient');
        }

        return (float) ($coefficient ?? 0);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function guarantees(): HasMany
    {
        return $this->hasMany(CustomerLoanGuarantee::class, 'loan_file_id')->latest('id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CustomerLoanInstallment::class, 'customer_loan_file_id')->orderBy('sequence');
    }
}
