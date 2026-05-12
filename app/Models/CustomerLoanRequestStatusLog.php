<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_loan_request_id
 * @property string $actor_type
 * @property int|null $admin_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
final class CustomerLoanRequestStatusLog extends Model
{
    public const ACTOR_ADMIN = 'admin';

    public const ACTOR_CUSTOMER = 'customer';

    public const ACTOR_SYSTEM = 'system';

    public $timestamps = false;

    protected $fillable = [
        'customer_loan_request_id',
        'actor_type',
        'admin_id',
        'from_status',
        'to_status',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanRequest::class, 'customer_loan_request_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
