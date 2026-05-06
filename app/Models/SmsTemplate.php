<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsTemplate extends Model
{
    protected $fillable = [
        'template_key',
        'is_system',
        'title',
        'category',
        'body',
        'placeholders',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'placeholders' => 'array',
        ];
    }
}
