<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Support\LoanInstallmentRoundingSettings;

/**
 * تقسیم مبلغ قابل بازپرداخت به اقساط با رندسازی پایه بر اساس حد رند تنظیم‌شده.
 */
final class LoanInstallmentAmountAllocator
{
    /**
     * @return array{
     *     base_amount_toman: int,
     *     amounts_toman: list<int>,
     *     remainder_toman: int,
     *     payable_after_down_payment_toman: int,
     *     adjusted_down_payment_toman: int
     * }
     */
    public function allocateForLoanFile(
        int $amountToman,
        int $profitToman,
        int $downPaymentToman,
        int $installmentsCount,
        ?string $remainderTarget = null,
        ?int $roundingStepToman = null,
    ): array {
        $downPaymentToman = max(0, $downPaymentToman);
        $payable = max(0, ($amountToman + $profitToman) - $downPaymentToman);
        $target = $this->normalizeRemainderTarget($remainderTarget);
        $step = $this->normalizeRoundingStep($roundingStepToman);

        $schedule = $this->allocate($payable, $installmentsCount, $target, $step);
        $adjustedDownPayment = $downPaymentToman;
        $reportedRemainder = (int) $schedule['remainder_toman'];

        if ($target === LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT && $schedule['remainder_toman'] > 0) {
            $adjustedDownPayment += $schedule['remainder_toman'];
            $payable = max(0, ($amountToman + $profitToman) - $adjustedDownPayment);
            $schedule = $this->allocate($payable, $installmentsCount, $target, $step);
        }

        return [
            'base_amount_toman' => $schedule['base_amount_toman'],
            'amounts_toman' => $schedule['amounts_toman'],
            'remainder_toman' => $reportedRemainder,
            'payable_after_down_payment_toman' => $payable,
            'adjusted_down_payment_toman' => $adjustedDownPayment,
        ];
    }

    /**
     * @return array{base_amount_toman: int, amounts_toman: list<int>, remainder_toman: int}
     */
    public function allocate(
        int $payableAfterDownPaymentToman,
        int $installmentsCount,
        ?string $remainderTarget = null,
        ?int $roundingStepToman = null,
    ): array {
        $installmentsCount = max(0, $installmentsCount);
        $payableAfterDownPaymentToman = max(0, $payableAfterDownPaymentToman);
        $target = $this->normalizeRemainderTarget($remainderTarget);
        $step = $this->normalizeRoundingStep($roundingStepToman);

        if ($installmentsCount < 1 || $payableAfterDownPaymentToman < 1) {
            return [
                'base_amount_toman' => 0,
                'amounts_toman' => [],
                'remainder_toman' => 0,
            ];
        }

        $rawPerInstallment = intdiv($payableAfterDownPaymentToman, $installmentsCount);
        $base = intdiv($rawPerInstallment, $step) * $step;
        if ($base < 1) {
            $base = $rawPerInstallment;
        }

        $amounts = array_fill(0, $installmentsCount, $base);
        $remainder = $payableAfterDownPaymentToman - ($base * $installmentsCount);

        if ($remainder > 0) {
            if ($target === LoanInstallmentRoundingSettings::REMAINDER_FIRST) {
                $amounts[0] = $base + $remainder;
            } elseif ($target === LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT) {
                // remainder is applied outside this method via down payment adjustment.
            } elseif ($target === LoanInstallmentRoundingSettings::REMAINDER_DISTRIBUTE) {
                $share = intdiv($remainder, $installmentsCount);
                $extra = $remainder % $installmentsCount;
                for ($i = 0; $i < $installmentsCount; $i++) {
                    $amounts[$i] = $base + $share + ($i < $extra ? 1 : 0);
                }
                $base = $base + $share;
            } else {
                $amounts[$installmentsCount - 1] = $base + $remainder;
            }
        }

        return [
            'base_amount_toman' => $base,
            'amounts_toman' => array_values($amounts),
            'remainder_toman' => max(0, $remainder),
        ];
    }

    private function normalizeRemainderTarget(?string $remainderTarget): string
    {
        if (is_string($remainderTarget) && in_array($remainderTarget, LoanInstallmentRoundingSettings::remainderTargetOptions(), true)) {
            return $remainderTarget;
        }

        return LoanInstallmentRoundingSettings::remainderTarget();
    }

    private function normalizeRoundingStep(?int $roundingStepToman): int
    {
        if (is_int($roundingStepToman) && LoanInstallmentRoundingSettings::isValidRoundingStep($roundingStepToman)) {
            return $roundingStepToman;
        }

        return LoanInstallmentRoundingSettings::roundingStepToman();
    }
}
