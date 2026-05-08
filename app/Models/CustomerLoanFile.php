<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerLoanFile extends Model
{
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
        'base_interest_rate',
        'has_custom_interest_rate',
        'custom_interest_rate',
        'effective_interest_rate',
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
            'base_interest_rate' => 'decimal:2',
            'has_custom_interest_rate' => 'boolean',
            'custom_interest_rate' => 'decimal:2',
            'effective_interest_rate' => 'decimal:2',
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

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
