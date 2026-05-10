<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerLoanInstallment extends Model
{
    protected $fillable = [
        'customer_loan_file_id',
        'sequence',
        'amount_toman',
        'due_date',
        'paid_amount_toman',
        'paid_at',
        'recorded_by_admin_id',
        'recorded_by_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_loan_file_id' => 'integer',
            'sequence' => 'integer',
            'amount_toman' => 'integer',
            'due_date' => 'date',
            'paid_amount_toman' => 'integer',
            'paid_at' => 'date',
            'recorded_by_admin_id' => 'integer',
        ];
    }

    public function loanFile(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanFile::class, 'customer_loan_file_id');
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    /**
     * @return HasMany<CustomerLoanInstallmentPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerLoanInstallmentPayment::class, 'customer_loan_installment_id')->orderBy('id');
    }
}
