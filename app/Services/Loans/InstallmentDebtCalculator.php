<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanInstallment;
use Carbon\Carbon;

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
            ->map(static fn ($p): array => ['id' => (int) $p->id, 'date' => Carbon::parse($p->deposited_at)->startOfDay(), 'amount' => max(0, (int) $p->amount_toman)])
            ->all();
        // Older installments may have only a receipt total and payment date.
        if ($installment->payments->isEmpty() && (int) $installment->paid_amount_toman > 0) {
            $receipts[] = ['id' => 0, 'date' => Carbon::parse($installment->paid_at ?? $at)->startOfDay(), 'amount' => (int) $installment->paid_amount_toman];
        }
        usort($receipts, static fn (array $a, array $b): int => ($a['date']->getTimestamp() <=> $b['date']->getTimestamp()) ?: ($a['id'] <=> $b['id']));
        foreach ($receipts as $receipt) {
            $date = $receipt['date'];
            if ($date->gt($at)) {
                continue;
            }
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

        return [
            'principal_paid_toman' => $nominal - $principal - $earlyEarned,
            'principal_remaining_toman' => $netPrincipal,
            'early_benefit_toman' => $earlyEarned + $earlyAvailable,
            'early_benefit_earned_toman' => $earlyEarned,
            'early_benefit_available_toman' => $earlyAvailable,
            'final_amount_toman' => max(0, $nominal + $assessed - $earlyEarned - $earlyAvailable),
            'late_fee_assessed_toman' => $assessed,
            'late_fee_paid_toman' => min($feePaid, $assessed),
            'late_fee_remaining_toman' => $lateRemaining,
            'remaining_toman' => $netPrincipal + $lateRemaining,
            'total_paid_toman' => $received,
            'credit_toman' => max(0, $feePaid - $assessed),
        ];
    }
}
