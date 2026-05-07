<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerWallet extends Model
{
    protected $fillable = [
        'customer_id',
        'balance_toman',
        'is_locked',
        'locked_at',
        'locked_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_toman' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class, 'wallet_id')->latest('id');
    }
}
