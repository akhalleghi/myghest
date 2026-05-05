<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\LoanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->input('repayment_rule_type') === LoanType::REPAY_ALLOWED_MONTHS) {
                $rows = $this->input('allowed_rows', []);

                if (! is_array($rows) || count($rows) < 1) {
                    $v->errors()->add('allowed_rows', 'برای «تعیین ماه‌های مجاز» حداقل یک ردیف (ماه و سقف) وارد کنید.');
                }
            }

            $docs = $this->input('required_documents', []);

            if (! is_array($docs)) {
                return;
            }

            $seen = [];

            foreach ($docs as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $pk = isset($row['preset_key']) ? (string) $row['preset_key'] : '';

                if ($pk === '') {
                    continue;
                }

                if (isset($seen[$pk])) {
                    $titleForMsg = str_starts_with($pk, LoanType::REQUIRED_DOCUMENT_CUSTOM_PREFIX)
                        ? 'مدرک جدید'
                        : LoanType::requiredDocumentDefaultTitle($pk);
                    $v->errors()->add(
                        'required_documents',
                        'مدرک «'.$titleForMsg.'» بیش از یک بار انتخاب شده است.',
                    );

                    break;
                }

                $seen[$pk] = true;
            }
        });
    }

    /**
     * @return array<string, array<int, Unique|string>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'profit_calculation_method' => ['required', Rule::in([LoanType::PROFIT_MONTHLY, LoanType::PROFIT_BANK])],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_late_coefficient' => ['required', 'numeric', 'min:0'],
            'daily_early_coefficient' => ['required', 'numeric', 'min:0'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'max_installment_gap' => ['nullable', 'integer', 'min:1'],
            'installment_gap_unit' => ['required', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'repayment_rule_type' => ['required', Rule::in([
                LoanType::REPAY_UNLIMITED,
                LoanType::REPAY_MAX_UNTIL,
                LoanType::REPAY_ALLOWED_MONTHS,
            ])],
            'repayment_max_months' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn (): bool => $this->input('repayment_rule_type') === LoanType::REPAY_MAX_UNTIL),
            ],
            'allowed_rows' => [
                Rule::requiredIf(fn (): bool => $this->input('repayment_rule_type') === LoanType::REPAY_ALLOWED_MONTHS),
                'array',
            ],
            'allowed_rows.*.months' => ['required_if:repayment_rule_type,allowed_months', 'integer', 'min:1'],
            'allowed_rows.*.cap' => ['required_if:repayment_rule_type,allowed_months', 'numeric', 'min:0'],
            'sms_reminder_enabled' => ['sometimes', 'boolean'],
            'registration_suspended' => ['sometimes', 'boolean'],
            'registration_suspended_message' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => (bool) $this->boolean('registration_suspended')),
            ],
            'plan_list_enabled' => ['sometimes', 'boolean'],
            'plan_title' => [
                Rule::requiredIf(fn (): bool => $this->boolean('plan_list_enabled')),
                'nullable',
                'string',
                'max:255',
            ],
            'plan_summary' => ['nullable', 'string', 'max:2000'],
            'plan_body' => ['nullable', 'string', 'max:50000'],
            'plan_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'plan_remove_image' => ['sometimes', 'boolean'],
            'required_documents' => ['present', 'array'],
            'required_documents.*.preset_key' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail): void {
                    $key = (string) $value;
                    $isPreset = in_array($key, LoanType::requiredDocumentPresetKeys(), true);
                    $isCustom = str_starts_with($key, LoanType::REQUIRED_DOCUMENT_CUSTOM_PREFIX);

                    if (! $isPreset && ! $isCustom) {
                        $fail('نوع مدرک نامعتبر است.');
                    }
                },
            ],
            'required_documents.*.title' => ['required', 'string', 'max:500'],
            'required_documents.*.description' => ['nullable', 'string', 'max:2000'],
            'required_documents.*.timing' => [
                'required',
                Rule::in([LoanType::DOC_TIMING_INITIAL, LoanType::DOC_TIMING_AFTER_EVALUATION]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان وام',
            'profit_calculation_method' => 'نحوه محاسبه سود',
            'interest_rate' => 'درصد بهره',
            'daily_late_coefficient' => 'ضریب دیرکرد روزانه',
            'daily_early_coefficient' => 'ضریب زودکرد روزانه',
            'max_loan_amount' => 'سقف مبلغ وام',
            'max_installment_gap' => 'حداکثر فاصله اقساط',
            'installment_gap_unit' => 'نوع فاصله اقساط',
            'repayment_rule_type' => 'دوره‌های بازپرداخت مجاز',
            'repayment_max_months' => 'حداکثر ماه',
            'allowed_rows' => 'ماه‌های مجاز',
            'allowed_rows.*.months' => 'ماه (ردیف)',
            'allowed_rows.*.cap' => 'سقف مبلغ (ردیف)',
            'registration_suspended_message' => 'متن غیرفعال بودن',
            'plan_title' => 'عنوان طرح',
            'plan_summary' => 'توضیحات کوتاه طرح',
            'plan_body' => 'توضیحات کامل طرح',
            'plan_image' => 'تصویر طرح',
            'required_documents' => 'مدارک لازم',
            'required_documents.*.preset_key' => 'نوع مدرک',
            'required_documents.*.title' => 'عنوان مدرک',
            'required_documents.*.description' => 'توضیحات مدرک',
            'required_documents.*.timing' => 'زمان ارائه مدرک',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('max_loan_amount')) {
            $raw = $this->input('max_loan_amount');
            if ($raw === null || $raw === '') {
                $this->merge(['max_loan_amount' => '']);
            } else {
                $digits = preg_replace('/\D+/', '', (string) $raw);

                $this->merge(['max_loan_amount' => $digits === '' ? '' : $digits]);
            }
        }

        if ($this->input('repayment_rule_type') !== LoanType::REPAY_ALLOWED_MONTHS) {
            $this->merge(['allowed_rows' => []]);
        }

        if (! $this->boolean('registration_suspended')) {
            $this->merge(['registration_suspended_message' => null]);
        }

        foreach (['max_loan_amount', 'max_installment_gap', 'repayment_max_months'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        if ($this->has('allowed_rows') && is_array($this->input('allowed_rows'))) {
            $clean = [];

            foreach ($this->input('allowed_rows', []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $months = $row['months'] ?? null;
                $cap = $row['cap'] ?? null;

                if ($months === null || $months === '' || $cap === null || $cap === '') {
                    continue;
                }

                $clean[] = ['months' => $months, 'cap' => $cap];
            }

            $this->merge(['allowed_rows' => $clean]);
        }

        $this->merge([
            'plan_list_enabled' => $this->boolean('plan_list_enabled'),
            'plan_remove_image' => $this->boolean('plan_remove_image'),
        ]);

        if (! $this->boolean('plan_list_enabled')) {
            $this->merge([
                'plan_title' => null,
                'plan_summary' => null,
                'plan_body' => null,
                'plan_remove_image' => false,
            ]);
        }

        $this->merge([
            'sms_reminder_enabled' => $this->boolean('sms_reminder_enabled'),
            'registration_suspended' => $this->boolean('registration_suspended'),
        ]);

        $rawJson = $this->input('required_documents_json');

        if ($rawJson === null || $rawJson === '') {
            $this->merge(['required_documents' => []]);
        } elseif (is_string($rawJson)) {
            $decoded = json_decode($rawJson, true);
            $this->merge(['required_documents' => is_array($decoded) ? array_values($decoded) : []]);
        } else {
            $this->merge(['required_documents' => []]);
        }
    }
}
