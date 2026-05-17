<?php

namespace App\Models;

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
            'login_count' => 'integer',
            'last_login_at' => 'datetime',
        ];
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
