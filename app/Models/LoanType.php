<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $profit_calculation_method
 * @property string $interest_rate
 * @property string $daily_late_coefficient
 * @property string $daily_early_coefficient
 * @property int|null $max_loan_amount
 * @property int|null $max_installment_gap
 * @property string $installment_gap_unit
 * @property array{type: string, max_months?: int|null, allowed_rows?: list<array{months: int, cap: float|int}>} $repayment_periods
 * @property bool $sms_reminder_enabled
 * @property bool $registration_suspended
 * @property string|null $registration_suspended_message
 * @property bool $plan_list_enabled
 * @property string|null $plan_image_path
 * @property string|null $plan_title
 * @property string|null $plan_summary
 * @property string|null $plan_body
 * @property list<array{preset_key: string, title: string, description: ?string, timing: string}>|null $required_documents
 */
final class LoanType extends Model
{
    public const REQUIRED_DOCUMENT_CUSTOM_PREFIX = 'custom_';

    public const PROFIT_MONTHLY = 'monthly';

    public const PROFIT_BANK = 'bank';

    public const GAP_MONTHLY = 'monthly';

    public const GAP_WEEKLY = 'weekly';

    public const REPAY_UNLIMITED = 'unlimited';

    public const REPAY_MAX_UNTIL = 'max_until';

    public const REPAY_ALLOWED_MONTHS = 'allowed_months';

    /** مدارک اولیه هنگام ثبت درخواست */
    public const DOC_TIMING_INITIAL = 'initial';

    /** مدارک پس از ارزیابی کارشناس */
    public const DOC_TIMING_AFTER_EVALUATION = 'after_evaluation';

    /**
     * کلیدهای از پیش تعریف‌شده و عنوان پیش‌فرض (فارسی).
     *
     * @var array<string, string>
     */
    public const REQUIRED_DOCUMENT_PRESETS = [
        'sayadi_check_register' => 'ثبت و تحویل چک صیادی به مبلغ وام مبادا',
        'retirement_card_image' => 'آپلود تصویر کارت بازنشستگی',
        'national_id_front_image' => 'آپلود تصویر روی کارت ملی',
        'salary_slip_or_order_image' => 'آپلود تصویر فیش یا حکم حقوقی',
        'national_id_back_image' => 'آپلود تصویر پشت کارت ملی',
        'credit_validation_file' => 'فایل اعتبار سنجی',
        'birth_certificate_page2_image' => 'آپلود تصویر صفحه دوم شناسنامه',
        'birth_certificate_page3_image' => 'آپلود تصویر صفحه سوم شناسنامه',
        'bank_statement_file' => 'آپلود فایل گردش حساب',
        'cheque_front_image' => 'آپلود تصویر روی چک',
        'cheque_back_image' => 'آپلود تصویر پشت چک',
        'sayadi_cheque_registration_image' => 'آپلود تصویر ثبت چک در سامانه صیادی',
        'insurance_list' => 'لیست بیمه',
        'identity_info' => 'اطلاعات هویتی',
        'national_id_card' => 'کارت ملی',
        'guarantee_images' => 'تصویر ضمانت ها',
        'medical_payment_documents' => 'مدارک پرداخت هزینه های پزشکی',
        'mehr_fund_entry_fee' => 'حق ورودی به صندوق اتحاد و پشتیبانی مهر',
    ];

    /**
     * مدارکی که در رابط زیر «معادل سیستمی» نمایش داده می‌شوند.
     *
     * @var list<string>
     */
    public const REQUIRED_DOCUMENT_SYSTEM_EQUIVALENT_KEYS = [
        'national_id_front_image',
    ];

    protected $fillable = [
        'title',
        'profit_calculation_method',
        'interest_rate',
        'daily_late_coefficient',
        'daily_early_coefficient',
        'max_loan_amount',
        'max_installment_gap',
        'installment_gap_unit',
        'repayment_periods',
        'sms_reminder_enabled',
        'registration_suspended',
        'registration_suspended_message',
        'plan_list_enabled',
        'plan_image_path',
        'plan_title',
        'plan_summary',
        'plan_body',
        'required_documents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'daily_late_coefficient' => 'decimal:6',
            'daily_early_coefficient' => 'decimal:6',
            'max_loan_amount' => 'integer',
            'max_installment_gap' => 'integer',
            'repayment_periods' => 'array',
            'sms_reminder_enabled' => 'boolean',
            'registration_suspended' => 'boolean',
            'plan_list_enabled' => 'boolean',
            'required_documents' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredDocumentPresetKeys(): array
    {
        return array_keys(self::REQUIRED_DOCUMENT_PRESETS);
    }

    public static function requiredDocumentDefaultTitle(string $key): string
    {
        return self::REQUIRED_DOCUMENT_PRESETS[$key] ?? $key;
    }

    public static function isSystemEquivalentPreset(string $key): bool
    {
        return in_array($key, self::REQUIRED_DOCUMENT_SYSTEM_EQUIVALENT_KEYS, true);
    }

    /**
     * برای JSON در فرانت (مودال انتخاب مدارک).
     *
     * @return list<array{key: string, defaultTitle: string, systemEquivalent: bool}>
     */
    public static function requiredDocumentsPresetListForFrontEnd(): array
    {
        $out = [];

        foreach (self::REQUIRED_DOCUMENT_PRESETS as $key => $title) {
            $out[] = [
                'key' => $key,
                'defaultTitle' => $title,
                'systemEquivalent' => self::isSystemEquivalentPreset($key),
            ];
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array{preset_key: string, title: string, description: string|null, timing: string}>
     */
    public static function normalizeRequiredDocumentsPayload(array $rows): array
    {
        $allowed = array_flip(self::requiredDocumentPresetKeys());
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = isset($row['preset_key']) ? (string) $row['preset_key'] : '';

            if ($key === '') {
                continue;
            }

            $isPreset = isset($allowed[$key]);
            $isCustom = str_starts_with($key, self::REQUIRED_DOCUMENT_CUSTOM_PREFIX);

            if (! $isPreset && ! $isCustom) {
                continue;
            }

            $title = isset($row['title']) ? trim((string) $row['title']) : '';

            if ($title === '') {
                $title = $isPreset
                    ? self::requiredDocumentDefaultTitle($key)
                    : 'مدرک جدید';
            }

            $description = $row['description'] ?? null;
            $description = $description !== null && $description !== '' ? trim((string) $description) : null;

            $timing = isset($row['timing']) ? (string) $row['timing'] : self::DOC_TIMING_AFTER_EVALUATION;

            if ($timing !== self::DOC_TIMING_INITIAL && $timing !== self::DOC_TIMING_AFTER_EVALUATION) {
                $timing = self::DOC_TIMING_AFTER_EVALUATION;
            }

            $out[] = [
                'preset_key' => $key,
                'title' => $title,
                'description' => $description,
                'timing' => $timing,
            ];
        }

        return $out;
    }

    protected static function booted(): void
    {
        self::deleting(function (LoanType $loanType): void {
            if ($loanType->plan_image_path) {
                Storage::disk('public')->delete($loanType->plan_image_path);
            }
        });
    }

    public function planImagePublicUrl(): ?string
    {
        if (! $this->plan_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->plan_image_path);
    }

    public function profitCalculationLabel(): string
    {
        return match ($this->profit_calculation_method) {
            self::PROFIT_MONTHLY => 'سود ماهانه',
            self::PROFIT_BANK => 'سود بانکی',
            default => $this->profit_calculation_method,
        };
    }

    public function installmentGapLabel(): string
    {
        return match ($this->installment_gap_unit) {
            self::GAP_MONTHLY => 'ماهانه',
            self::GAP_WEEKLY => 'هفتگی',
            default => $this->installment_gap_unit,
        };
    }
}
