<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminActivityLog extends Model
{
    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_SESSION_EXPIRED = 'session_expired';

    public const ACTION_HTTP = 'http';

    protected $fillable = [
        'admin_id',
        'action',
        'description',
        'route_name',
        'http_method',
        'url_path',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'http_status',
        'metadata',
        'performed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_id' => 'integer',
            'http_status' => 'integer',
            'metadata' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
