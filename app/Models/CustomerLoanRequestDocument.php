<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_loan_request_id
 * @property string $preset_key
 * @property string $document_title
 * @property int $row_index
 * @property int|null $client_row_id
 * @property string|null $description
 * @property string $stored_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $review_status
 * @property string|null $expert_note
 */
final class CustomerLoanRequestDocument extends Model
{
    public const REVIEW_WAITING_USER = 'waiting_user';

    public const REVIEW_SUBMITTED_BY_USER = 'submitted_by_user';

    public const REVIEW_WAITING_EXPERT = 'waiting_expert';

    public const REVIEW_INCOMPLETE = 'incomplete';

    public const REVIEW_APPROVED = 'approved';

    /**
     * @return array<string, string>
     */
    public static function reviewStatusLabels(): array
    {
        return [
            self::REVIEW_WAITING_USER => 'منتظر کاربر',
            self::REVIEW_SUBMITTED_BY_USER => 'ثبت‌شده توسط کاربر',
            self::REVIEW_WAITING_EXPERT => 'منتظر کارشناس',
            self::REVIEW_INCOMPLETE => 'ناقص',
            self::REVIEW_APPROVED => 'تأیید شده',
        ];
    }

    /**
     * @return list<string>
     */
    public static function reviewStatusCodes(): array
    {
        return [
            self::REVIEW_WAITING_USER,
            self::REVIEW_SUBMITTED_BY_USER,
            self::REVIEW_WAITING_EXPERT,
            self::REVIEW_INCOMPLETE,
            self::REVIEW_APPROVED,
        ];
    }

    protected $fillable = [
        'customer_loan_request_id',
        'preset_key',
        'document_title',
        'row_index',
        'client_row_id',
        'description',
        'stored_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'review_status',
        'expert_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_index' => 'integer',
            'client_row_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanRequest::class, 'customer_loan_request_id');
    }
}
