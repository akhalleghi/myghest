<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerMedia extends Model
{
    public const KINDS = [
        'portrait' => 'عکس مشتری',
        'national_card_front' => 'روی کارت ملی',
        'national_card_back' => 'پشت کارت ملی',
        'video' => 'ویدئوی مشتری',
    ];

    protected $table = 'customer_media';

    protected $fillable = ['customer_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
