<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerWalletOnlinePaymentIntent extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_REDIRECTED = 'redirected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'customer_wallet_online_payment_intents';

    protected $fillable = [
        'customer_id',
        'expected_amount_toman',
        'expected_amount_rial',
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
            'expected_amount_toman' => 'integer',
            'expected_amount_rial' => 'integer',
            'track_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
