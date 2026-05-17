<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoginAccessBlock extends Model
{
    public const GUARD_CUSTOMER = 'customer';

    public const GUARD_ADMIN = 'admin';

    protected $fillable = [
        'guard',
        'username',
        'ip_address',
        'failed_attempts',
        'blocked_at',
        'is_active',
        'unblocked_at',
        'unblocked_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'blocked_at' => 'datetime',
            'is_active' => 'boolean',
            'unblocked_at' => 'datetime',
        ];
    }

    public function unblockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'unblocked_by_admin_id');
    }

    public function guardLabel(): string
    {
        return match ($this->guard) {
            self::GUARD_ADMIN => 'ورود ادمین',
            self::GUARD_CUSTOMER => 'ورود مشتری',
            default => $this->guard,
        };
    }
}
