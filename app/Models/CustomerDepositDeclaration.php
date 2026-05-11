<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerDepositDeclaration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPROVED_APPLIED = 'approved_applied';

    public const STATUS_REJECTED = 'rejected';

    public const USER_METHOD_CASH = 'cash';

    public const USER_METHOD_BANK = 'bank';

    public const USER_METHOD_ONLINE = 'online';

    protected $fillable = [
        'customer_id',
        'customer_loan_file_id',
        'customer_loan_installment_id',
        'deposited_at',
        'amount_toman',
        'user_payment_method',
        'tracking_number',
        'customer_note',
        'attachment_path',
        'status',
        'admin_note',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_acknowledged_at',
        'applied_payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'customer_loan_file_id' => 'integer',
            'customer_loan_installment_id' => 'integer',
            'deposited_at' => 'date',
            'amount_toman' => 'integer',
            'reviewed_by_admin_id' => 'integer',
            'reviewed_at' => 'datetime',
            'review_acknowledged_at' => 'datetime',
            'applied_payment_id' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabelsFa(): array
    {
        return [
            self::STATUS_PENDING => 'در حال بررسی',
            self::STATUS_APPROVED => 'تایید شده',
            self::STATUS_APPROVED_APPLIED => 'تایید و ثبت در پرداختی قسط',
            self::STATUS_REJECTED => 'عدم تایید',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function userPaymentMethodLabelsFa(): array
    {
        return [
            self::USER_METHOD_CASH => 'نقدی',
            self::USER_METHOD_BANK => 'بانک (فیش، کارت به کارت)',
            self::USER_METHOD_ONLINE => 'آنلاین',
        ];
    }

    /**
     * @return list<string>
     */
    public static function userPaymentMethodKeys(): array
    {
        return [self::USER_METHOD_CASH, self::USER_METHOD_BANK, self::USER_METHOD_ONLINE];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanFile(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanFile::class, 'customer_loan_file_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanInstallment::class, 'customer_loan_installment_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function appliedPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanInstallmentPayment::class, 'applied_payment_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * اعلام واریزی رسیدگی‌شده که کاربر هنوز «دیده» نشمارده یا پس از آن رکورد دوباره به‌روز شده است.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWhereNeedsCustomerReviewNotification(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', self::STATUS_PENDING)
            ->whereNotNull('reviewed_at')
            ->where(function (Builder $w): void {
                $w->whereNull('review_acknowledged_at')
                    ->orWhereRaw('review_acknowledged_at < updated_at');
            });
    }
}
