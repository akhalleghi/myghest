<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChequeCustomer extends Model
{
    protected $guarded = ['id', 'wallet_balance_toman', 'mobile_verified_at'];

    protected function casts(): array
    {
        return ['membership_at' => 'date', 'mobile_verified_at' => 'datetime', 'sms_enabled' => 'boolean', 'auto_reminders_enabled' => 'boolean', 'wallet_balance_toman' => 'integer'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ChequeSale::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChequeEvent::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ChequeCustomerMedia::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
