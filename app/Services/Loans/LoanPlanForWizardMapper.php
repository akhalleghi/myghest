<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\LoanType;
use Hekmatinasser\Jalali\Jalali;

/**
 * همان ساختار امن طرح برای ویزارد (اشتراک پنل کاربر و API ویرایش).
 */
final class LoanPlanForWizardMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(LoanType $lt): array
    {
        $rd = is_array($lt->required_documents) ? $lt->required_documents : [];

        $filterByTiming = static fn (string $timing): array => array_values(array_filter(
            $rd,
            static fn ($d) => is_array($d) && ($d['timing'] ?? null) === $timing
        ));

        $initialRaw = $filterByTiming(LoanType::DOC_TIMING_INITIAL);
        $initialDocs = [];
        foreach ($initialRaw as $idx => $d) {
            if (! is_array($d)) {
                continue;
            }
            $presetKey = isset($d['preset_key']) ? trim((string) $d['preset_key']) : '';
            if ($presetKey === '') {
                $presetKey = LoanType::REQUIRED_DOCUMENT_CUSTOM_PREFIX.'wizard_'.$idx;
            }
            $title = isset($d['title']) ? trim((string) $d['title']) : '';
            if ($title === '') {
                $title = LoanType::requiredDocumentDefaultTitle($presetKey);
            }
            $initialDocs[] = [
                'preset_key' => $presetKey,
                'title' => $title,
                'description' => isset($d['description']) ? (string) $d['description'] : null,
            ];
        }

        $afterDocs = array_map(static fn (array $d) => [
            'title' => (string) ($d['title'] ?? ''),
        ], $filterByTiming(LoanType::DOC_TIMING_AFTER_EVALUATION));

        $rep = is_array($lt->repayment_periods) ? $lt->repayment_periods : ['type' => 'unlimited'];
        $repType = (string) ($rep['type'] ?? 'unlimited');
        $repMaxMonths = isset($rep['max_months']) ? (int) $rep['max_months'] : null;
        $allowedRows = [];
        if (isset($rep['allowed_rows']) && is_array($rep['allowed_rows'])) {
            foreach ($rep['allowed_rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $months = isset($row['months']) ? (int) $row['months'] : 0;
                if ($months <= 0) {
                    continue;
                }
                $allowedRows[] = [
                    'months' => $months,
                    'cap' => isset($row['cap']) ? (int) $row['cap'] : null,
                ];
            }
        }

        $gapUnit = (string) ($lt->installment_gap_unit ?? LoanType::GAP_MONTHLY);
        $maxAmount = $lt->max_loan_amount !== null ? (int) $lt->max_loan_amount : null;
        $maxGap = $lt->max_installment_gap !== null ? (int) $lt->max_installment_gap : null;
        $interestRate = (float) $lt->interest_rate;
        $lateCoef = (float) $lt->daily_late_coefficient;
        $earlyCoef = (float) $lt->daily_early_coefficient;
        $profitMethod = (string) ($lt->profit_calculation_method ?? LoanType::PROFIT_MONTHLY);

        $displayTitle = trim((string) ($lt->plan_title ?? '')) !== ''
            ? (string) $lt->plan_title
            : (string) $lt->title;

        return [
            'id' => (int) $lt->id,
            'title' => $displayTitle,
            'title_with_code' => sprintf('%s (کد طرح: %s)', $displayTitle, Jalali::enToFaNumbers((string) $lt->id)),
            'profit_method' => $profitMethod,
            'profit_method_label' => $lt->profitCalculationLabel(),
            'interest_rate' => $interestRate,
            'interest_rate_fa' => Jalali::enToFaNumbers($this->trimDecimal($interestRate)),
            'daily_late_coefficient' => $lateCoef,
            'daily_early_coefficient' => $earlyCoef,
            'max_loan_amount' => $maxAmount,
            'max_loan_amount_fa' => $maxAmount !== null ? Jalali::enToFaNumbers(number_format($maxAmount, 0, '.', ',')) : null,
            'max_installment_gap' => $maxGap,
            'installment_gap_unit' => $gapUnit,
            'installment_gap_unit_label' => $lt->installmentGapLabel(),
            'repayment' => [
                'type' => $repType,
                'max_months' => $repMaxMonths,
                'allowed_rows' => $allowedRows,
            ],
            'initial_documents' => $initialDocs,
            'after_evaluation_documents' => $afterDocs,
        ];
    }

    private function trimDecimal(float $n): string
    {
        $s = number_format($n, 2, '.', '');

        return rtrim(rtrim($s, '0'), '.');
    }
}
