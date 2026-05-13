<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerLoanFullSettlementOnlinePaymentIntent extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_REDIRECTED = 'redirected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'customer_loan_full_settlement_online_payment_intents';

    protected $fillable = [
        'customer_id',
        'customer_loan_file_id',
        'expected_amount_toman',
        'expected_amount_rial',
        'principal_component_toman',
        'late_fee_component_toman',
        'track_id',
        'status',
        'gateway_key',
        'zibal_ref_number',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'customer_loan_file_id' => 'integer',
            'expected_amount_toman' => 'integer',
            'expected_amount_rial' => 'integer',
            'principal_component_toman' => 'integer',
            'late_fee_component_toman' => 'integer',
            'track_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function loanFile(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanFile::class, 'customer_loan_file_id');
    }
}
