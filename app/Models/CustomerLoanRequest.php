<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $loan_type_id
 * @property string $status
 * @property int $amount_toman
 * @property int $installments_count
 * @property int $installment_interval_count
 * @property string $installment_interval_unit
 * @property string $profit_calculation_method
 * @property string $interest_rate
 * @property string $daily_late_coefficient
 * @property string $daily_early_coefficient
 * @property string|null $description
 * @property string|null $expert_note
 * @property string|null $expert_note_customer
 * @property bool $documents_physical_received
 * @property array<int, string>|null $waived_initial_preset_keys
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property int|null $customer_loan_file_id
 * @property \Illuminate\Support\Carbon|null $converted_to_loan_at
 * @property int|null $converted_by_admin_id
 */
final class CustomerLoanRequest extends Model
{
    public const STATUS_INITIAL = 'initial';

    public const STATUS_DOCUMENTS_COMPLETE = 'documents_complete';

    public const STATUS_DOCUMENTS_INCOMPLETE = 'documents_incomplete';

    public const STATUS_PENDING_EXPERT_REVIEW = 'pending_expert_review';

    public const STATUS_NEEDS_FOLLOWUP = 'needs_followup';

    public const STATUS_EXPERT_RE_REVIEW = 'expert_re_review';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PRIORITIZED = 'prioritized';

    public const STATUS_PAID = 'paid';

    /** @deprecated use STATUS_PENDING_EXPERT_REVIEW */
    public const STATUS_PENDING_REVIEW = 'pending_expert_review';

    /** @deprecated use STATUS_EXPERT_RE_REVIEW */
    public const STATUS_UNDER_REVIEW = 'expert_re_review';

    /** @deprecated use STATUS_PRIORITIZED */
    public const STATUS_APPROVED = 'prioritized';

    /** @deprecated use STATUS_INITIAL */
    public const STATUS_WITHDRAWN = 'initial';

    protected $fillable = [
        'customer_id',
        'loan_type_id',
        'status',
        'amount_toman',
        'installments_count',
        'installment_interval_count',
        'installment_interval_unit',
        'profit_calculation_method',
        'interest_rate',
        'daily_late_coefficient',
        'daily_early_coefficient',
        'description',
        'expert_note',
        'expert_note_customer',
        'documents_physical_received',
        'waived_initial_preset_keys',
        'submitted_at',
        'customer_loan_file_id',
        'converted_to_loan_at',
        'converted_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_toman' => 'integer',
            'installments_count' => 'integer',
            'installment_interval_count' => 'integer',
            'interest_rate' => 'decimal:2',
            'daily_late_coefficient' => 'decimal:6',
            'daily_early_coefficient' => 'decimal:6',
            'documents_physical_received' => 'boolean',
            'waived_initial_preset_keys' => 'array',
            'submitted_at' => 'datetime',
            'customer_loan_file_id' => 'integer',
            'converted_to_loan_at' => 'datetime',
            'converted_by_admin_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    /**
     * پروندهٔ وامی که این درخواست به آن «تبدیل» شده است (در صورت وجود).
     */
    public function loanFile(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanFile::class, 'customer_loan_file_id');
    }

    /**
     * آیا این درخواست قبلاً به پروندهٔ وام تبدیل شده است؟
     */
    public function isConvertedToLoan(): bool
    {
        return $this->customer_loan_file_id !== null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerLoanRequestDocument::class, 'customer_loan_request_id')->orderBy('id');
    }

    /**
     * آیا مشتری مجاز است ویزارد مدارک این درخواست را باز کند و فایل‌ها را (در صورت مجاز بودن هر سند) عوض کند؟
     */
    public static function wizardAllowsCustomerDocumentEditing(self $loanRequest): bool
    {
        $loanRequest->loadMissing('documents');
        if ($loanRequest->status === self::STATUS_DOCUMENTS_INCOMPLETE) {
            return true;
        }

        return $loanRequest->documents->contains(static function (mixed $d): bool {
            return $d instanceof CustomerLoanRequestDocument
                && in_array($d->review_status, [
                    CustomerLoanRequestDocument::REVIEW_INCOMPLETE,
                    CustomerLoanRequestDocument::REVIEW_WAITING_USER,
                ], true);
        });
    }

    /**
     * آیا مشتری مجاز است فایل این سند را با آپلود جدید جایگزین کند؟
     */
    public static function customerCanReplaceDocument(self $loanRequest, CustomerLoanRequestDocument $doc): bool
    {
        if ($doc->review_status === CustomerLoanRequestDocument::REVIEW_APPROVED) {
            return false;
        }
        if (in_array($doc->review_status, [
            CustomerLoanRequestDocument::REVIEW_INCOMPLETE,
            CustomerLoanRequestDocument::REVIEW_WAITING_USER,
        ], true)) {
            return true;
        }

        return $loanRequest->status === self::STATUS_DOCUMENTS_INCOMPLETE;
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CustomerLoanRequestStatusLog::class, 'customer_loan_request_id')->orderByDesc('created_at');
    }

    protected static function booted(): void
    {
        self::deleting(function (CustomerLoanRequest $request): void {
            foreach ($request->documents()->get() as $doc) {
                $p = (string) ($doc->stored_path ?? '');
                if ($p !== '') {
                    Storage::disk('local')->delete($p);
                }
            }
        });
    }
}
