<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Support\IranMobile;
use Carbon\Carbon;

final class SmsFreeSendAudienceResolver
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $financeCalculator,
    ) {}

    /**
     * @param  list<int>  $customerIds
     * @return list<array{customer_id:int,mobile:string,name:string,wallet_balance_toman:int}>
     */
    public function resolve(string $source, array $customerIds = []): array
    {
        $query = Customer::query()
            ->where(function ($query): void {
                $query->whereNull('sms_sending_enabled')->orWhere('sms_sending_enabled', true);
            })
            ->with('wallet:id,customer_id,balance_toman')
            ->orderBy('id');

        if ($source === 'selected_customers') {
            $selectedIds = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn (int $id): bool => $id > 0)));
            $query->whereIn('id', $selectedIds);
        } elseif ($source === 'wallet_positive_customers') {
            $query->whereHas('wallet', static fn ($walletQuery) => $walletQuery->where('balance_toman', '>', 0));
        } elseif ($source !== 'all_customers') {
            $eligibleIds = $this->customerIdsForSource($source);
            $query->whereIn('id', $eligibleIds);
        }

        $recipients = [];
        $seenMobiles = [];
        foreach ($query->get() as $customer) {
            $mobile = IranMobile::normalize((string) $customer->mobile);
            if ($mobile === null || isset($seenMobiles[$mobile])) {
                continue;
            }

            $seenMobiles[$mobile] = true;
            $recipients[] = [
                'customer_id' => (int) $customer->id,
                'mobile' => $mobile,
                'name' => $customer->fullName(),
                'wallet_balance_toman' => max(0, (int) ($customer->wallet?->balance_toman ?? 0)),
            ];
        }

        return $recipients;
    }

    /**
     * @return list<int>
     */
    private function customerIdsForSource(string $source): array
    {
        if (! in_array($source, ['overdue_customers', 'settled_customers', 'no_debt_customers'], true)) {
            return [];
        }

        $hasDebt = [];
        $hasReview = [];
        $hasSettledLoan = [];
        $overdue = [];
        $today = Carbon::today();

        CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->with(['loanType', 'installments.payments'])
            ->chunkById(150, function ($loanFiles) use ($source, &$hasDebt, &$hasReview, &$hasSettledLoan, &$overdue, $today): void {
                foreach ($loanFiles as $loanFile) {
                    $customerId = (int) $loanFile->customer_id;
                    $snapshot = $this->financeCalculator->loanInstallmentFinancialSnapshot(
                        $loanFile,
                        $this->financeCalculator->totalRepayableToman($loanFile),
                    );

                    if ($snapshot['requires_schedule_review']) {
                        $hasReview[$customerId] = true;

                        continue;
                    }

                    if ($snapshot['remaining_amount_toman'] > 0) {
                        $hasDebt[$customerId] = true;
                    } elseif ($snapshot['settled_by_balance']) {
                        $hasSettledLoan[$customerId] = true;
                    }

                    if ($source !== 'overdue_customers') {
                        continue;
                    }

                    foreach ($loanFile->installments as $installment) {
                        $remaining = (int) ($snapshot['installment_payables'][(int) $installment->id]['remaining_toman'] ?? 0);
                        if ($remaining > 0 && Carbon::parse($installment->due_date)->startOfDay()->lt($today)) {
                            $overdue[$customerId] = true;
                            break;
                        }
                    }
                }
            });

        return match ($source) {
            'overdue_customers' => array_map('intval', array_keys($overdue)),
            'settled_customers' => array_map('intval', array_keys(array_diff_key($hasSettledLoan, $hasDebt, $hasReview))),
            'no_debt_customers' => Customer::query()
                ->whereNotIn('id', array_values(array_unique(array_merge(array_keys($hasDebt), array_keys($hasReview)))))
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            default => [],
        };
    }
}
