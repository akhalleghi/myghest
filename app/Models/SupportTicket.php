<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SupportTicket extends Model
{
    /** @deprecated use SupportTicketStatus::PENDING_REVIEW */
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const MODE_SINGLE = 'single';

    public const MODE_MULTIPLE = 'multiple';

    public const MODE_ALL = 'all';

    protected $fillable = [
        'subject',
        'status',
        'recipient_mode',
        'created_by_admin_id',
        'created_by_customer_id',
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

    public function createdByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'created_by_customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    public function firstMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->oldestOfMany();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->latestOfMany();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SupportTicketRecipient::class);
    }

    public function isAdminOriginated(): bool
    {
        return $this->created_by_admin_id !== null;
    }

    public function isCustomerOriginated(): bool
    {
        return $this->created_by_customer_id !== null;
    }
}
