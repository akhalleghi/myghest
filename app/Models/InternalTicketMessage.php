<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InternalTicketMessage extends Model
{
    protected $fillable = [
        'internal_ticket_id',
        'body_html',
        'body_excerpt',
        'sender_admin_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(InternalTicket::class, 'internal_ticket_id');
    }

    public function senderAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_admin_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InternalTicketAttachment::class);
    }
}
