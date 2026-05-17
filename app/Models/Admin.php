<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'mobile',
        'password',
        'is_active',
        'is_super_admin',
        'login_count',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'login_count' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AdminPermissionGrant, $this>
     */
    public function permissionGrants(): HasMany
    {
        return $this->hasMany(AdminPermissionGrant::class);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function fullName(): string
    {
        $composed = trim(((string) ($this->first_name ?? '')).' '.((string) ($this->last_name ?? '')));
        if ($composed !== '') {
            return $composed;
        }

        return trim((string) ($this->name ?? ''));
    }
}
