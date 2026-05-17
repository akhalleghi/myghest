<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InstallmentSmsReminderDispatch extends Model
{
    public const KIND_PRE_DUE = 'pre_due';

    public const KIND_DUE_DAY = 'due_day';

    public const KIND_OVERDUE = 'overdue';

    protected $fillable = [
        'customer_loan_installment_id',
        'kind',
        'business_date',
        'sms_log_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_loan_installment_id' => 'integer',
            'business_date' => 'date',
            'sms_log_id' => 'integer',
        ];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanInstallment::class, 'customer_loan_installment_id');
    }

    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(SmsLog::class);
    }
}
