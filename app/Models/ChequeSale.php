<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChequeSale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'disbursement_due_date' => 'date', 'owner_verified_at' => 'datetime', 'owner_details' => 'array', 'guarantee_by_self' => 'boolean', 'daily_late_coefficient' => 'decimal:8', 'daily_early_coefficient' => 'decimal:8'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ChequeCustomer::class, 'cheque_customer_id');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(ChequeInstrument::class);
    }
}
