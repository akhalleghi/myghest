<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SmsFreeSendCampaign extends Model
{
    protected $fillable = [
        'created_by_admin_id',
        'source',
        'status',
        'total_count',
        'sent_count',
        'failed_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsFreeSendCampaignRecipient::class, 'campaign_id');
    }
}
