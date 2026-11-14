<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\ChequeInstrument;
use Carbon\Carbon;

/** All cheque views, receipts, reports and SMS use this same assessment. */
final class ChequeFinance
{
    public function contract(array $data): array
    {
        $amount = (int) $data['purchase_amount_toman'];
        $months = (int) $data['months'];
        $rate = (float) $data['interest_rate'] / 100;
        $profit = (int) round($amount * $rate * ($data['profit_method'] === 'bank' ? $months / 12 : $months));

        return ['profit_toman' => $profit, 'total_toman' => max(0, $amount + $profit - (int) $data['down_payment_toman'] - (int) $data['discount_toman'])];
    }

    public function balance(ChequeInstrument $cheque, ?Carbon $at = null): array
    {
        $cheque->loadMissing(['sale', 'receipts']);
        $at = ($at ?? Carbon::today())->copy()->startOfDay();
        if ($cheque->cleared_at && $cheque->cleared_at->lt($at)) {
            $at = $cheque->cleared_at->copy();
        }
        $due = $cheque->due_date->copy()->startOfDay();
        $principal = (int) $cheque->amount_toman;
        $paid = $feePaid = $benefit = 0;
        $fee = 0.0;
        $cursor = $due->copy();
        $coefficient = $cheque->bounced_at && $cheque->bounced_at->lte($at) ? (float) $cheque->sale->daily_late_coefficient : 0;
        foreach ($cheque->receipts->sortBy(fn ($row) => $row->paid_at->toDateString().sprintf('%020d', $row->id)) as $receipt) {
            $date = $receipt->paid_at->copy()->startOfDay();
            if ($date->gt($at)) {
                continue;
            }
            if ($date->gt($cursor)) {
                $fee += $principal * $coefficient * $cursor->diffInDays($date);
                $cursor = $date->copy();
            }
            $principal = max(0, $principal - (int) $receipt->principal_toman - (int) $receipt->early_benefit_toman);
            $feePaid += (int) $receipt->late_fee_toman;
            $benefit += (int) $receipt->early_benefit_toman;
            $paid += (int) $receipt->amount_toman;
        }
        if ($at->gt($cursor)) {
            $fee += $principal * $coefficient * $cursor->diffInDays($at);
        }
        $late = max(0, (int) round($fee) - $feePaid);
        $early = $at->lt($due) && $cheque->bounced_at === null
            ? min($principal, (int) round($principal * min(.95, (float) $cheque->sale->daily_early_coefficient * $at->diffInDays($due)))) : 0;

        return ['principal_remaining_toman' => $principal, 'late_fee_toman' => max(0, (int) round($fee)), 'late_remaining_toman' => $late,
            'early_quote_toman' => $early, 'early_earned_toman' => $benefit, 'paid_toman' => $paid,
            'remaining_toman' => max(0, $principal + $late - $early), 'days_to_due' => (int) $at->diffInDays($due, false)];
    }

    public function allocate(array $balance, int $amount): array
    {
        $principal = $balance['principal_remaining_toman'];
        $net = max(0, $principal - $balance['early_quote_toman']);
        // Principal first, as in installment payments: unpaid penalties never
        // become new principal or generate a second layer of late charges.
        $cash = min($amount, $net);
        $late = $amount - $cash;
        $rebate = $cash >= $net ? $balance['early_quote_toman'] : (int) floor($cash * $balance['early_quote_toman'] / max(1, $net));

        return ['principal_toman' => $cash, 'late_fee_toman' => $late, 'early_benefit_toman' => min($rebate, max(0, $principal - $cash))];
    }
}
