<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerReferrer extends Model
{
    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'phone',
        'sort_order',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
