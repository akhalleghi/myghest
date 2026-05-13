<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerLoanInstallmentPayment extends Model
{
    /** قبل از وجود جدول پرداخت‌های جزئی، فقط مجموعِ پرداخت روی خود قسط ذخیره می‌شد. */
    public const METHOD_LEGACY_IMPORTED = 'legacy_import';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_GOLD = 'gold';

    public const METHOD_ONLINE = 'online';

    /** پرداخت تسویهٔ کلی بدهی از درگاه (ثبت خودکار پس از بازگشت IPG) */
    public const METHOD_FULL_SETTLEMENT_ONLINE = 'full_settlement_online';

    public const METHOD_CARD_TERMINAL = 'card_terminal';

    protected $table = 'customer_loan_installment_payments';

    protected $fillable = [
        'customer_loan_installment_id',
        'payment_method',
        'amount_toman',
        'reference_due_date',
        'deposited_at',
        'note',
        'recorded_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    public static function methodLabels(): array
    {
        return [
            self::METHOD_LEGACY_IMPORTED => 'ثبت قبلی (یکپارچه)',
            self::METHOD_CASH => 'نقدی',
            self::METHOD_BANK_TRANSFER => 'واریز بانکی',
            self::METHOD_GOLD => 'طلا',
            self::METHOD_ONLINE => 'آنلاین',
            self::METHOD_FULL_SETTLEMENT_ONLINE => 'تسویهٔ یکجای بدهی (درگاه)',
            self::METHOD_CARD_TERMINAL => 'کارتخوان',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function methodKeys(): array
    {
        return array_keys(self::methodLabels());
    }

    /**
     * @return list<string>
     */
    public static function creatablePaymentMethodKeys(): array
    {
        return array_values(array_diff(self::methodKeys(), [
            self::METHOD_LEGACY_IMPORTED,
            self::METHOD_FULL_SETTLEMENT_ONLINE,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_loan_installment_id' => 'integer',
            'amount_toman' => 'integer',
            'reference_due_date' => 'date',
            'deposited_at' => 'date',
            'recorded_by_admin_id' => 'integer',
        ];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CustomerLoanInstallment::class, 'customer_loan_installment_id');
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }
}
