<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerLoanGuarantee extends Model
{
    public const TYPE_ORG_SELF = 'org_self';

    public const TYPE_ORG_OTHER = 'org_other';

    public const TYPE_CHEQUE = 'cheque';

    public const TYPE_GOLD = 'gold';

    public const TYPE_OTHER = 'other';

    public const GOLD_ITEM_BROKEN_GOLD = 'broken_gold';

    public const GOLD_ITEM_QUARTER_COIN = 'quarter_coin';

    public const GOLD_ITEM_HALF_COIN = 'half_coin';

    public const GOLD_ITEM_FULL_COIN = 'full_coin';

    public const GOLD_ITEM_PARSIAN_GRAM = 'parsian_gram';

    /**
     * @return array<int, string>
     */
    public static function goldItemCodes(): array
    {
        return [
            self::GOLD_ITEM_BROKEN_GOLD,
            self::GOLD_ITEM_QUARTER_COIN,
            self::GOLD_ITEM_HALF_COIN,
            self::GOLD_ITEM_FULL_COIN,
            self::GOLD_ITEM_PARSIAN_GRAM,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function goldItemLabels(): array
    {
        return [
            self::GOLD_ITEM_BROKEN_GOLD => 'طلای شکن',
            self::GOLD_ITEM_QUARTER_COIN => 'ربع سکه',
            self::GOLD_ITEM_HALF_COIN => 'نیم سکه',
            self::GOLD_ITEM_FULL_COIN => 'تمام بهار',
            self::GOLD_ITEM_PARSIAN_GRAM => 'گرمی پارسیان',
        ];
    }

    protected $fillable = [
        'customer_id',
        'loan_file_id',
        'type',
        'description',
        'meta',
        'attachment_path',
        'return_document_path',
        'returned_at',
        'returned_by_admin_id',
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
            'returned_at' => 'datetime',
            'returned_by_admin_id' => 'integer',
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
        // Guarantee attachments are private; use the admin download route.
        return null;
    }

    public function returnDocumentUrl(): ?string
    {
        // Return documents are intentionally private; use the admin download route.
        return null;
    }

    public function isMarkedReturned(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        if ($this->type === self::TYPE_CHEQUE) {
            return ! empty($meta['cheque_returned']);
        }
        if (in_array($this->type, [self::TYPE_GOLD, self::TYPE_OTHER], true)) {
            return ! empty($meta['returned']);
        }

        return false;
    }
}
