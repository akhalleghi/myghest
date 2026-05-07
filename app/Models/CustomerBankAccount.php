<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerBankAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'account_identifier',
        'bank_name',
        'branch_name',
        'sort_order',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
