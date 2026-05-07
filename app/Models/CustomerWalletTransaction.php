<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerWalletTransaction extends Model
{
    public const DIRECTION_DEPOSIT = 'deposit';

    public const DIRECTION_WITHDRAW = 'withdraw';

    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'customer_id',
        'direction',
        'amount_toman',
        'balance_after_toman',
        'description',
        'actor_admin_id',
        'ip_address',
        'user_agent',
        'meta',
        'request_uuid',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_toman' => 'integer',
            'balance_after_toman' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }
}
