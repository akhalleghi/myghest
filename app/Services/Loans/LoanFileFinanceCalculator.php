<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\LoanType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * منطق مالی پروندهٔ وام (هم‌سو با محاسبات پنل ادمین) برای نمای امن در پنل کاربر.
 */
final class LoanFileFinanceCalculator
{
    public function calculateLoanProfitToman(
        int $amountToman,
        float $interestRatePercent,
        string $profitMethod,
        int $installmentsCount,
        int $intervalCount,
        string $intervalUnit
    ): int {
        if ($amountToman <= 0 || $interestRatePercent <= 0 || $installmentsCount <= 0 || $intervalCount <= 0) {
            return 0;
        }
        $months = $this->repaymentDurationInMonths($installmentsCount, $intervalCount, $intervalUnit);
        if ($months <= 0) {
            return 0;
        }
        $rate = $interestRatePercent / 100;
        $profit = $profitMethod === LoanType::PROFIT_BANK
            ? ($amountToman * $rate * ($months / 12))
            : ($amountToman * $rate * $months);

        return max(0, (int) round($profit));
    }

    public function repaymentDurationInMonths(int $installmentsCount, int $intervalCount, string $intervalUnit): float
    {
        $multiplier = $intervalUnit === LoanType::GAP_WEEKLY ? (12 / 52) : 1.0;

        return max(0, $installmentsCount * $intervalCount * $multiplier);
    }

    public function totalRepayableToman(CustomerLoanFile $file): int
    {
        $profit = $this->calculateLoanProfitToman(
            (int) $file->amount_toman,
            (float) $file->effective_interest_rate,
            (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $file->installments_count,
            (int) $file->installment_interval_count,
            (string) $file->installment_interval_unit
        );

        return max(0, ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman);
    }

    /**
     * @return array{schedule_remaining_toman: int, total_paid_toman: int, paid_installments_count: int, paid_installments_slot_count: int, late_fee_so_far_toman: int}
     */
    public function loanInstallmentFinancialSnapshot(CustomerLoanFile $file, int $totalRepayableContract): array
    {
        $installments = $file->installments;
        $lateCoef = (float) ($file->loanType?->daily_late_coefficient ?? 0);
        $discount = (int) ($file->discount_amount_toman ?? 0);

        if ($installments->isEmpty()) {
            return [
                'schedule_remaining_toman' => $totalRepayableContract,
                'total_paid_toman' => 0,
                'paid_installments_count' => 0,
                'paid_installments_slot_count' => 0,
                'late_fee_so_far_toman' => 0,
            ];
        }

        $totalPaid = (int) $installments->sum(static fn (CustomerLoanInstallment $i): int => (int) $i->paid_amount_toman);
        $scheduleRemaining = max(0, $totalRepayableContract - $totalPaid);
        $remainingAfterDiscount = $file->is_settled
            ? 0
            : max(0, $scheduleRemaining - $discount);
        $slotFullyPaidCount = (int) $installments->filter(static function (CustomerLoanInstallment $i): bool {
            return (int) $i->amount_toman > 0 && (int) $i->paid_amount_toman >= (int) $i->amount_toman;
        })->count();
        $periodCount = $installments->count();
        $paidInstallmentsCountReport = $remainingAfterDiscount <= 0 && $periodCount > 0 ? $periodCount : $slotFullyPaidCount;

        return [
            'schedule_remaining_toman' => $scheduleRemaining,
            'total_paid_toman' => $totalPaid,
            'paid_installments_count' => $paidInstallmentsCountReport,
            'paid_installments_slot_count' => $slotFullyPaidCount,
            'late_fee_so_far_toman' => $this->estimateLateFeeSoFarToman($installments, $lateCoef, $remainingAfterDiscount),
        ];
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    public function estimateLateFeeSoFarToman(Collection $installments, float $dailyLateCoef, int $contractDebtRemainingAfterDiscount): int
    {
        if ($dailyLateCoef <= 0 || $contractDebtRemainingAfterDiscount <= 0) {
            return 0;
        }

        $now = Carbon::now()->startOfDay();
        $sum = 0;

        foreach ($installments as $i) {
            $unpaid = max(0, (int) $i->amount_toman - (int) $i->paid_amount_toman);
            if ($unpaid <= 0) {
                continue;
            }
            $due = Carbon::parse($i->due_date)->startOfDay();
            if ($due->gte($now)) {
                continue;
            }
            $days = (int) $due->diffInDays($now);
            if ($days < 1) {
                continue;
            }
            $sum += (int) round($unpaid * $dailyLateCoef * $days);
        }

        return max(0, $sum);
    }

    public function estimateBookletPenaltyTomanForInstallment(CustomerLoanInstallment $inst, float $dailyLateCoef): int
    {
        if ($dailyLateCoef <= 0) {
            return 0;
        }
        $unpaid = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
        if ($unpaid <= 0) {
            return 0;
        }
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $now = Carbon::now()->startOfDay();
        if ($due->gte($now)) {
            return 0;
        }
        $days = (int) $due->diffInDays($now);
        if ($days < 1) {
            return 0;
        }

        return max(0, (int) round($unpaid * $dailyLateCoef * $days));
    }

    /**
     * برآورد دیرکرد تا تاریخ پرداخت (برای اقساط تسویه‌شده با تأخیر).
     */
    public function estimatePenaltyAtDateToman(CustomerLoanInstallment $inst, float $dailyLateCoef, Carbon $untilDate): int
    {
        if ($dailyLateCoef <= 0) {
            return 0;
        }
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $until = $untilDate->copy()->startOfDay();
        if ($until->lte($due)) {
            return 0;
        }
        $days = (int) $due->diffInDays($until);
        if ($days < 1) {
            return 0;
        }
        $principal = (int) $inst->amount_toman;

        return max(0, (int) round($principal * $dailyLateCoef * $days));
    }
}
