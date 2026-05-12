<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $stage_slot
 * @property int|null $sms_template_id
 * @property bool $is_mutable
 * @property bool $allow_duplicate_request
 * @property int $sort_order
 */
final class LoanRequestStatusDefinition extends Model
{
    /**
     * کلیدهای «جایگاه» در فرم ادمین (با برچسب فارسی در API/فرانت).
     *
     * @var array<string, string>
     */
    public const STAGE_SLOT_LABELS = [
        'before_documents' => 'قبل از تکمیل مدارک',
        'documents_step' => 'تکمیل مدارک',
        'after_documents' => 'بعد از تکمیل مدارک',
    ];

    protected $fillable = [
        'code',
        'title',
        'stage_slot',
        'sms_template_id',
        'is_mutable',
        'allow_duplicate_request',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mutable' => 'boolean',
            'allow_duplicate_request' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function smsTemplate(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    /**
     * @return array<string, string> code => title
     */
    public static function titlesByCode(): array
    {
        return self::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('title', 'code')
            ->all();
    }
}
