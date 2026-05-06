<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsPanelSetting extends Model
{
    protected $fillable = [
        'provider',
        'is_active',
        'username',
        'password',
        'domain_name',
        'sender_number',
        'last_connection_status',
        'last_connection_message',
        'last_connected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }
}
