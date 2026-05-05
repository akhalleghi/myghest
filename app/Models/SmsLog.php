<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_UNDELIVERED = 'undelivered';

    protected $fillable = [
        'sms_panel',
        'status',
        'sent_at',
        'message_text',
        'recipient',
        'type',
        'cost',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'cost' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار',
            self::STATUS_DELIVERED => 'تحویل شده',
            self::STATUS_UNDELIVERED => 'تحویل نشده',
            default => $this->status,
        };
    }
}
