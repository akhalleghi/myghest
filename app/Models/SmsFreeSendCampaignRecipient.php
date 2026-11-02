<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SmsFreeSendCampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'customer_id',
        'recipient',
        'message_text',
        'status',
        'sms_log_id',
        'result_message',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SmsFreeSendCampaign::class, 'campaign_id');
    }
}
