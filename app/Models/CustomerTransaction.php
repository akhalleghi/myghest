<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * دفتر تراکنش‌های عمومی مشتری (درگاه، کیف پول، …).
 */
final class CustomerTransaction extends Model
{
    public const KIND_INSTALLMENT_ONLINE_PAYMENT = 'installment_online_payment';

    public const KIND_INSTALLMENT_WALLET_PAYMENT = 'installment_wallet_payment';

    /** رزرو برای آینده — شارژ کیف پول */
    public const KIND_WALLET_TOPUP = 'wallet_topup';

    public const KIND_FULL_SETTLEMENT_ONLINE_PAYMENT = 'full_settlement_online_payment';

    public const KIND_FULL_SETTLEMENT_WALLET_PAYMENT = 'full_settlement_wallet_payment';

    public const STATUS_CREATED = 'created';

    public const STATUS_REDIRECTED = 'redirected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function kindKeys(): array
    {
        return [
            self::KIND_INSTALLMENT_ONLINE_PAYMENT,
            self::KIND_INSTALLMENT_WALLET_PAYMENT,
            self::KIND_WALLET_TOPUP,
            self::KIND_FULL_SETTLEMENT_ONLINE_PAYMENT,
            self::KIND_FULL_SETTLEMENT_WALLET_PAYMENT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusKeys(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_REDIRECTED,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }

    protected $table = 'customer_transactions';

    protected $fillable = [
        'customer_id',
        'kind',
        'status',
        'amount_toman',
        'amount_rial',
        'gateway_key',
        'track_id',
        'bank_reference',
        'title',
        'detail',
        'meta',
        'source_type',
        'source_id',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'amount_toman' => 'integer',
            'amount_rial' => 'integer',
            'track_id' => 'integer',
            'meta' => 'array',
            'source_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
