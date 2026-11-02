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
     * حداکثر مبلغی که هنوز می‌توان به‌صورت مجموع پرداخت‌های اقساط ثبت کرد (پس از کسر تخفیف)، بدون توجه به سهم نامی هر قسط.
     * (هم‌سو با پنل ادمین.)
     */
    public function installmentPaymentCeilingToman(CustomerLoanFile $file): int
    {
        $file->load(['loanType', 'installments.payments']);

        return (int) $this->loanInstallmentFinancialSnapshot($file, $this->totalRepayableToman($file))['remaining_amount_toman'];
    }

    /**
     * Shared debt balance: nominal installments plus unpaid charges using the file's coefficient (legacy files fall back to loan type), less registered discounts.
     *
     * @return array<string, mixed>
     */
    public function loanInstallmentFinancialSnapshot(CustomerLoanFile $file, int $totalRepayableContract, ?Carbon $at = null, ?int $excludePaymentId = null): array
    {
        $file->loadMissing(['loanType', 'installments.payments']);
        $balances = [];
        $principalPaid = 0;
        $lateRemaining = 0;
        $received = 0;
        $coefficient = $file->dailyLateCoefficient();
        foreach ($file->installments as $installment) {
            $installment->setRelation('loanFile', $file);
            $balance = app(InstallmentDebtCalculator::class)->balance($installment, $coefficient, $at, $excludePaymentId);
            $balances[(int) $installment->id] = $balance;
            $principalPaid += $balance['principal_paid_toman'];
            $lateRemaining += $balance['late_fee_remaining_toman'];
            $received += $balance['total_paid_toman'];
        }
        $principalRemaining = max(0, $totalRepayableContract - $principalPaid);
        $discount = max(0, (int) ($file->discount_amount_toman ?? 0));
        $closed = $file->is_settled || $file->revoked_at !== null;
        $principalPayable = $closed ? 0 : max(0, $principalRemaining - $discount);
        $latePayable = $closed ? 0 : max(0, $lateRemaining - max(0, $discount - $principalRemaining));
        $remaining = $principalPayable + $latePayable;
        $periodCount = $file->installments->count();
        $scheduledTotal = (int) $file->installments->sum('amount_toman');
        $requiresReview = ! $closed && $periodCount > 0 && $scheduledTotal !== $totalRepayableContract;
        $principalDiscount = min($discount, $principalRemaining);
        $lateDiscount = max(0, $discount - $principalRemaining);
        $payables = [];
        $paidCount = 0;
        foreach ($file->installments->sortBy('sequence') as $installment) {
            $id = (int) $installment->id;
            $principalWaived = min($principalDiscount, $balances[$id]['principal_remaining_toman']);
            $principalDiscount -= $principalWaived;
            $lateWaived = min($lateDiscount, $balances[$id]['late_fee_remaining_toman']);
            $lateDiscount -= $lateWaived;
            $principal = $closed ? 0 : $balances[$id]['principal_remaining_toman'] - $principalWaived;
            $late = $closed ? 0 : $balances[$id]['late_fee_remaining_toman'] - $lateWaived;
            $payables[$id] = [
                'principal_remaining_toman' => $principal,
                'late_fee_remaining_toman' => $late,
                'remaining_toman' => $principal + $late,
                'discount_applied_toman' => $principalWaived + $lateWaived,
            ];
            if ((int) $installment->amount_toman > 0 && $principal + $late === 0) {
                $paidCount++;
            }
        }

        return [
            'contract_total_toman' => $totalRepayableContract,
            'scheduled_total_toman' => $scheduledTotal,
            'schedule_difference_toman' => $scheduledTotal - $totalRepayableContract,
            'requires_schedule_review' => $requiresReview,
            'settled_by_balance' => $closed || (! $requiresReview && $remaining === 0),
            'schedule_remaining_toman' => $principalRemaining,
            'debt_remaining_toman' => $principalRemaining + $lateRemaining,
            'remaining_amount_toman' => $remaining,
            'remaining_signed_toman' => $closed ? 0 : $principalRemaining + $lateRemaining - $discount,
            'principal_payable_toman' => $principalPayable,
            'late_fee_payable_toman' => $latePayable,
            'total_paid_toman' => $received,
            'paid_installments_count' => $remaining === 0 && ! $requiresReview ? $periodCount : $paidCount,
            'paid_installments_slot_count' => $paidCount,
            'unpaid_installments_count' => $remaining === 0 && ! $requiresReview ? 0 : $periodCount - $paidCount,
            'late_fee_so_far_toman' => $closed ? 0 : $lateRemaining,
            'installment_balances' => $balances,
            'installment_payables' => $payables,
        ];
    }

    public function installmentPaymentAllowanceToman(CustomerLoanFile $file, CustomerLoanInstallment $installment, ?Carbon $at = null, ?int $excludePaymentId = null): int
    {
        $file->load(['loanType', 'installments.payments']);
        $snapshot = $this->loanInstallmentFinancialSnapshot($file, $this->totalRepayableToman($file), $at, $excludePaymentId);
        $current = $at === null ? $snapshot : $this->loanInstallmentFinancialSnapshot($file, $this->totalRepayableToman($file), null, $excludePaymentId);

        return min($snapshot['remaining_amount_toman'], (int) ($snapshot['installment_payables'][(int) $installment->id]['remaining_toman'] ?? 0), (int) ($current['installment_payables'][(int) $installment->id]['remaining_toman'] ?? 0));
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    public function estimateLateFeeSoFarToman(Collection $installments, float $dailyLateCoef, int $contractDebtRemainingAfterDiscount): int
    {
        return (int) $installments->sum(fn (CustomerLoanInstallment $i): int => $i->debtBalance($dailyLateCoef)['late_fee_remaining_toman']);
    }

    public function estimateBookletPenaltyTomanForInstallment(CustomerLoanInstallment $inst, float $dailyLateCoef): int
    {
        return (int) $inst->debtBalance($dailyLateCoef)['late_fee_remaining_toman'];
    }

    /**
     * برآورد دیرکرد تا تاریخ پرداخت (برای اقساط تسویه‌شده با تأخیر).
     */
    public function estimatePenaltyAtDateToman(CustomerLoanInstallment $inst, float $dailyLateCoef, Carbon $untilDate): int
    {
        return $this->estimatePenaltyForPrincipalAtDateToman(
            (int) $inst->amount_toman,
            Carbon::parse($inst->due_date),
            $dailyLateCoef,
            $untilDate,
        );
    }

    public function estimatePenaltyForPrincipalAtDateToman(
        int $principalToman,
        Carbon $dueDate,
        float $dailyLateCoef,
        Carbon $untilDate,
    ): int {
        if ($dailyLateCoef <= 0 || $principalToman <= 0) {
            return 0;
        }
        $due = $dueDate->copy()->startOfDay();
        $until = $untilDate->copy()->startOfDay();
        if ($until->lte($due)) {
            return 0;
        }
        $days = (int) $due->diffInDays($until);
        if ($days < 1) {
            return 0;
        }

        return max(0, (int) round($principalToman * $dailyLateCoef * $days));
    }
}
