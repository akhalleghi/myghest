<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Rebuilds principal and unpaid simple late charges from dated receipts. */
final class InstallmentDebtCalculator
{
    public function balance(CustomerLoanInstallment $installment, float $dailyLateCoefficient, ?Carbon $at = null, ?int $excludePaymentId = null, float $dailyEarlyCoefficient = 0): array
    {
        $at = ($at ?? Carbon::today())->copy()->startOfDay();
        $due = Carbon::parse($installment->due_date)->startOfDay();
        $principal = max(0, (int) $installment->amount_toman);
        $nominal = $principal;
        $accrued = 0.0;
        $feePaid = 0;
        $received = 0;
        $earlyEarned = 0;
        $cursor = $due->copy();
        $installment->loadMissing('payments');
        $receipts = $installment->payments
            ->filter(static fn ($p): bool => $excludePaymentId === null || (int) $p->id !== $excludePaymentId)
            ->map(static fn ($p): array => ['id' => (int) $p->id, 'date' => Carbon::parse($p->deposited_at)->startOfDay(), 'amount' => max(0, (int) $p->amount_toman), 'rounded' => (bool) $p->overdue_rounding_applied])
            ->all();
        // Older installments may have only a receipt total and payment date.
        if ($installment->payments->isEmpty() && (int) $installment->paid_amount_toman > 0) {
            $receipts[] = ['id' => 0, 'date' => Carbon::parse($installment->paid_at ?? $at)->startOfDay(), 'amount' => (int) $installment->paid_amount_toman, 'rounded' => false];
        }
        usort($receipts, static fn (array $a, array $b): int => ($a['date']->getTimestamp() <=> $b['date']->getTimestamp()) ?: ($a['id'] <=> $b['id']));
        $hasRoundedReceipt = false;
        foreach ($receipts as $receipt) {
            $date = $receipt['date'];
            if ($date->gt($at)) {
                continue;
            }
            $hasRoundedReceipt = $hasRoundedReceipt || ($receipt['rounded'] && $date->gt($due));
            if ($date->gt($cursor)) {
                $accrued += $principal * max(0, $dailyLateCoefficient) * (int) $cursor->diffInDays($date);
                $cursor = $date->copy();
            }
            // Receipts cover nominal installments first; any excess covers charges.
            // Unpaid charges survive completion of the nominal installment.
            $rate = $date->lt($due) ? min(1.0, max(0, $dailyEarlyCoefficient) * (int) $date->diffInDays($due)) : 0.0;
            $covered = $rate >= 1 ? $principal : min($principal, (int) round($receipt['amount'] / (1 - $rate)));
            $rebate = min($covered, max(0, (int) round($covered * $rate)));
            $applied = min($covered - $rebate, $receipt['amount']);
            $earlyEarned += $covered - $applied;
            $principal -= $covered;
            $feePaid += $receipt['amount'] - $applied;
            $received += $receipt['amount'];
        }
        if ($at->gt($cursor)) {
            $accrued += $principal * max(0, $dailyLateCoefficient) * (int) $cursor->diffInDays($at);
        }
        $assessed = max(0, (int) round($accrued));
        $lateRemaining = max(0, $assessed - $feePaid);
        $earlyAvailable = $at->lt($due)
            ? min($principal, max(0, (int) round($principal * max(0, $dailyEarlyCoefficient) * (int) $at->diffInDays($due)))) : 0;
        $netPrincipal = $principal - $earlyAvailable;

        $unroundedAssessed = $assessed;
        $finalAmount = max(0, $nominal + $assessed - $earlyEarned - $earlyAvailable);
        $roundingAdjustment = 0;
        // A dated receipt remembers the new rule, so paying only the old raw
        // amount cannot silently waive the rounding remainder. Historical fully
        // paid installments have no marker and keep their original settlement.
        $legacyClosed = ! $hasRoundedReceipt && $installment->relationLoaded('loanFile')
            && ($installment->loanFile?->is_settled || $installment->loanFile?->revoked_at !== null);
        if (! $legacyClosed && $at->gt($due) && ($netPrincipal + $lateRemaining > 0 || $hasRoundedReceipt)) {
            $roundingAdjustment = (1000 - $finalAmount % 1000) % 1000;
            $assessed += $roundingAdjustment;
            $finalAmount += $roundingAdjustment;
            $lateRemaining = max(0, $assessed - $feePaid);
        }

        // A waiver only reduces unpaid fees, after rounding. It can never repay
        // principal, manufacture a receipt, or refund fees already collected.
        $beforeWaiver = $assessed;
        $waiver = max(0, (int) $installment->late_fee_waived_toman);
        // Historical quotes use the waiver that was in effect on that day.
        if ($waiver > 0 && $installment->late_fee_waived_at !== null
            && $at->lt($installment->late_fee_waived_at->copy()->startOfDay())) {
            $waiver = (int) DB::table('installment_late_fee_waivers')->where('installment_id', $installment->id)
                ->where('created_at', '<=', $at->copy()->endOfDay())->orderByDesc('id')->value('new_waived_toman');
        }
        $waived = min($lateRemaining, $waiver);
        $assessed -= $waived;
        $lateRemaining -= $waived;
        $finalAmount -= $waived;
        $roundingAdjustment = max(0, $roundingAdjustment - $waived);

        return [
            'principal_paid_toman' => $nominal - $principal - $earlyEarned,
            'principal_remaining_toman' => $netPrincipal,
            'early_benefit_toman' => $earlyEarned + $earlyAvailable,
            'early_benefit_earned_toman' => $earlyEarned,
            'early_benefit_available_toman' => $earlyAvailable,
            'final_amount_toman' => $finalAmount,
            'rounding_adjustment_toman' => $roundingAdjustment,
            'late_fee_unrounded_toman' => $unroundedAssessed,
            'late_fee_assessed_toman' => $assessed,
            'late_fee_before_waiver_toman' => $beforeWaiver,
            'late_fee_waived_toman' => $waived,
            'late_fee_paid_toman' => min($feePaid, $assessed),
            'late_fee_remaining_toman' => $lateRemaining,
            'remaining_toman' => $netPrincipal + $lateRemaining,
            'total_paid_toman' => $received,
            'credit_toman' => max(0, $feePaid - $assessed),
        ];
    }
}
