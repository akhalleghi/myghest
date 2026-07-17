<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InternalTicketAttachment extends Model
{
    protected $fillable = [
        'internal_ticket_message_id',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(InternalTicketMessage::class, 'internal_ticket_message_id');
    }
}
