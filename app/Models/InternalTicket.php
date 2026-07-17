<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class InternalTicket extends Model
{
    public const MODE_SINGLE = 'single';

    public const MODE_MULTIPLE = 'multiple';

    public const MODE_ALL = 'all';

    protected $fillable = [
        'subject',
        'status',
        'recipient_mode',
        'created_by_admin_id',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InternalTicketMessage::class)->orderBy('id');
    }

    public function firstMessage(): HasOne
    {
        return $this->hasOne(InternalTicketMessage::class)->oldestOfMany();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(InternalTicketMessage::class)->latestOfMany();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(InternalTicketRecipient::class);
    }
}
