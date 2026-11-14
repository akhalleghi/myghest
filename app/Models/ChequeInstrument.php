<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChequeInstrument extends Model
{
    public const FOLDERS = ['active' => 'چک‌های جاری', 'bank' => 'واگذار به بانک', 'transferred' => 'انتقال به دیگران', 'bounced' => 'چک‌های برگشتی', 'returned' => 'چک‌های عودت‌شده', 'archive' => 'بایگانی وصول‌شده'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'bounced_at' => 'date', 'cleared_at' => 'date', 'destination' => 'array', 'amount_toman' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ChequeSale::class, 'cheque_sale_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ChequeReceipt::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChequeEvent::class);
    }
}
