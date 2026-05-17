<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SettledMembersReportService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $financeCalculator,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon, from_jdate: string, to_jdate: string}
     */
    public function resolveDateRange(?string $fromJdate, ?string $toJdate): array
    {
        $from = JalaliInputParser::toCarbonDate($fromJdate);
        $to = JalaliInputParser::toCarbonDate($toJdate);

        if ($from === null || $to === null) {
            $today = Carbon::today();
            $jToday = Jalali::instance($today);
            $from = Carbon::createFromTimestamp($jToday->clone()->startYear()->getTimestamp())->startOfDay();
            $to = $today->copy()->endOfDay();
        } else {
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            } else {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_jdate' => Jalali::instance($from)->format('Y/m/d'),
            'to_jdate' => Jalali::instance($to)->format('Y/m/d'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(
        Carbon $from,
        Carbon $to,
        string $search = '',
        int $minSettledLoans = 1,
    ): array {
        $query = CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->with([
                'customer:id,first_name,last_name,mobile,national_id,customer_code',
                'loanType:id,daily_late_coefficient',
                'installments.payments',
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('loan_code', 'like', $like)
                    ->orWhereHas('customer', function (Builder $c) use ($like): void {
                        $c->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('national_id', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('customer_code', 'like', $like);
                    });
            });
        }

        /** @var Collection<int, CustomerLoanFile> $files */
        $files = $query->get();

        /** @var Collection<int, Collection<int, CustomerLoanFile>> $byCustomer */
        $byCustomer = $files
            ->filter(fn (CustomerLoanFile $file): bool => $this->isSettledForReport($file))
            ->filter(function (CustomerLoanFile $file) use ($from, $to): bool {
                $settledOn = $this->settlementDate($file);

                return $settledOn !== null && $settledOn->betweenIncluded($from, $to);
            })
            ->groupBy('customer_id');

        $rows = [];
        foreach ($byCustomer as $customerId => $customerFiles) {
            if ($customerFiles->count() < $minSettledLoans) {
                continue;
            }

            /** @var Customer|null $customer */
            $customer = $customerFiles->first()?->customer;
            if ($customer === null) {
                continue;
            }

            $lastSettledAt = $customerFiles
                ->map(fn (CustomerLoanFile $file): Carbon => $this->settlementDate($file))
                ->filter()
                ->sortByDesc(fn (Carbon $d): int => $d->getTimestamp())
                ->first();

            $rows[] = $this->mapCustomerRow(
                $customer,
                (int) $customerId,
                $customerFiles->count(),
                (int) $customerFiles->sum(fn (CustomerLoanFile $file): int => (int) $file->amount_toman),
                $lastSettledAt,
            );
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['last_settled_sort'] ?? ''), (string) ($a['last_settled_sort'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            $lastCmp = strcmp((string) ($a['last_name'] ?? ''), (string) ($b['last_name'] ?? ''));
            if ($lastCmp !== 0) {
                return $lastCmp;
            }

            return strcmp((string) ($a['first_name'] ?? ''), (string) ($b['first_name'] ?? ''));
        });

        return array_map(static function (array $row): array {
            unset($row['last_settled_sort']);

            return $row;
        }, $rows);
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'نام',
            'نام خانوادگی',
            'موبایل',
            'تعداد وام',
            'مجموع وام‌ها',
            'تاریخ آخرین تسویه',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        return [
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            (string) ($row['mobile'] ?? ''),
            Jalali::enToFaNumbers((string) ($row['settled_loans_count'] ?? '')),
            (string) ($row['total_loans_formatted'] ?? ''),
            (string) ($row['last_settled_jdate'] ?? ''),
        ];
    }

    /**
     * همان منطق نمایش «تسویه شده» در پنل مشتری: تسویه رسمی یا ماندهٔ تعهدی صفر (بدون وضعیت بستانکار).
     */
    private function isSettledForReport(CustomerLoanFile $file): bool
    {
        if ($file->is_settled) {
            return true;
        }

        $totalRepayable = $this->financeCalculator->totalRepayableToman($file);
        $snap = $this->financeCalculator->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $signedRemaining = (int) ($snap['schedule_remaining_toman'] ?? 0) - $discount;

        if ($signedRemaining < 0) {
            return false;
        }

        return $signedRemaining <= 0;
    }

    private function settlementDate(CustomerLoanFile $file): ?Carbon
    {
        if ($file->settled_at !== null) {
            return Carbon::parse((string) $file->settled_at)->startOfDay();
        }

        $lastPayment = $this->lastInstallmentPaymentDate($file);
        if ($lastPayment !== null) {
            return $lastPayment;
        }

        if ($file->is_settled && $file->updated_at !== null) {
            return Carbon::parse((string) $file->updated_at)->startOfDay();
        }

        return null;
    }

    private function lastInstallmentPaymentDate(CustomerLoanFile $file): ?Carbon
    {
        $latest = null;

        foreach ($file->installments as $installment) {
            foreach ($installment->payments as $payment) {
                if ($payment->deposited_at === null) {
                    continue;
                }
                $deposited = Carbon::parse((string) $payment->deposited_at)->startOfDay();
                if ($latest === null || $deposited->gt($latest)) {
                    $latest = $deposited;
                }
            }
        }

        return $latest;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCustomerRow(
        Customer $customer,
        int $customerId,
        int $loansCount,
        int $totalToman,
        ?Carbon $lastSettledAt,
    ): array {
        $firstName = trim((string) ($customer->first_name ?? ''));
        $lastName = trim((string) ($customer->last_name ?? ''));
        $mobile = (string) ($customer->mobile ?? '');

        return [
            'customer_id' => $customerId,
            'first_name' => $firstName !== '' ? $firstName : '—',
            'last_name' => $lastName !== '' ? $lastName : '—',
            'mobile' => $mobile !== '' ? Jalali::enToFaNumbers($mobile) : '—',
            'national_id' => (string) ($customer->national_id ?? ''),
            'settled_loans_count' => $loansCount,
            'total_loans_toman' => $totalToman,
            'total_loans_formatted' => $this->formatAmount($totalToman),
            'last_settled_jdate' => $lastSettledAt !== null
                ? Jalali::enToFaNumbers(Jalali::instance($lastSettledAt)->format('Y/m/d'))
                : '—',
            'last_settled_sort' => $lastSettledAt?->toDateString() ?? '',
            'customer_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => $customerId,
            ]),
        ];
    }

    private function formatAmount(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format($toman, 0, '.', ','));
    }
}
