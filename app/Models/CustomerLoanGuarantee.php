<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class CustomerLoanGuarantee extends Model
{
    public const TYPE_ORG_SELF = 'org_self';

    public const TYPE_ORG_OTHER = 'org_other';

    public const TYPE_CHEQUE = 'cheque';

    public const TYPE_GOLD = 'gold';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'customer_id',
        'loan_file_id',
        'type',
        'description',
        'meta',
        'attachment_path',
        'created_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'loan_file_id' => 'integer',
            'meta' => 'array',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanFile(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanFile::class, 'loan_file_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function attachmentUrl(): ?string
    {
        if (! is_string($this->attachment_path) || $this->attachment_path === '') {
            return null;
        }
        if (! Storage::disk('public')->exists($this->attachment_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
