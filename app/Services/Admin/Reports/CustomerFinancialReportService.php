<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Customer;
use App\Models\CustomerLoanGuarantee;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

final class CustomerFinancialReportService
{
    public function __construct(private readonly LoanFileFinanceCalculator $finance) {}

    /** @return array<string, string> */
    public function columns(): array
    {
        return [
            'customer_code' => 'کد مشتری',
            'customer_name' => 'نام مشتری',
            'mobile' => 'موبایل',
            'national_id' => 'کد ملی',
            'membership_jdate' => 'تاریخ عضویت',
            'customer_group' => 'گروه مشتری در بازه',
            'loans_count' => 'تعداد وام در بازه',
            'principal_toman' => 'اصل وام‌های ثبت‌شده در بازه (تومان)',
            'profit_toman' => 'کارمزد قراردادی وام‌های بازه (تومان)',
            'paid_toman' => 'واریزی اقساط در بازه (تومان)',
            'late_assessed_toman' => 'دیرکرد ایجادشده در بازه (تومان)',
            'late_paid_toman' => 'دیرکرد پرداخت‌شده در بازه (تومان)',
            'early_earned_toman' => 'زودکرد تحقق‌یافته در بازه (تومان)',
            'remaining_toman' => 'مانده بدهی فعلی با دیرکرد و زودکرد (تومان)',
            'late_remaining_toman' => 'دیرکرد پرداخت‌نشده فعلی (تومان)',
            'settled_count' => 'تعداد وام تسویه‌شده فعلی',
            'current_count' => 'تعداد وام جاری فعلی',
            'review_count' => 'تعداد پرونده نیازمند بررسی اقساط',
            'wallet_toman' => 'موجودی فعلی کیف پول (تومان)',
            'guarantee_toman' => 'مبلغ ثبت‌شده تضامین مستردنشده (تومان)',
            'unvalued_guarantees_count' => 'تعداد تضامین فاقد مبلغ ثبت‌شده',
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>} */
    public function fetchResult(Carbon $from, Carbon $to, string $group = 'all', string $search = ''): array
    {
        $query = Customer::query()
            ->where(static function ($q) use ($to): void {
                $q->whereRaw('DATE(COALESCE(membership_at, created_at)) <= ?', [$to->toDateString()])
                    ->orWhereHas('loanFiles', static fn ($loans) => $loans->whereNull('revoked_at')->whereDate('loan_start_date', '<=', $to->toDateString()))
                    ->orWhereHas('loanFiles.installments.payments', static fn ($payments) => $payments->whereDate('deposited_at', '<=', $to->toDateString())->whereHas('installment.loanFile', static fn ($loans) => $loans->whereNull('revoked_at')));
            })
            ->with(['wallet', 'loanFiles' => static fn ($q) => $q->whereNull('revoked_at')->with(['loanType', 'installments.payments'])])
            ->orderByDesc('id');
        if ($group === 'new') {
            $query->whereRaw('DATE(COALESCE(membership_at, created_at)) >= ?', [$from->toDateString()])
                ->whereRaw('DATE(COALESCE(membership_at, created_at)) <= ?', [$to->toDateString()]);
        } elseif ($group === 'old') {
            $query->whereRaw('DATE(COALESCE(membership_at, created_at)) < ?', [$from->toDateString()]);
        }
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('mobile', 'like', $like)->orWhere('national_id', 'like', $like)
                    ->orWhere('customer_code', 'like', $like);
            });
        }
        $rows = [];
        $summary = array_fill_keys(array_slice(array_keys($this->columns()), 6), 0);
        foreach ($query->lazy(150)->chunk(150) as $customers) {
            $guarantees = CustomerLoanGuarantee::query()->whereIn('customer_id', $customers->pluck('id'))
                ->whereHas('loanFile', static fn ($q) => $q->whereNull('revoked_at'))->get()->groupBy('customer_id');
            foreach ($customers as $customer) {
                $membership = $customer->membership_at ?? $customer->created_at;
                $row = array_fill_keys(array_keys($summary), 0);
                $row += [
                    'customer_id' => (int) $customer->id,
                    'customer_code' => (string) $customer->customer_code,
                    'customer_name' => $customer->fullName(),
                    'mobile' => (string) $customer->mobile,
                    'national_id' => (string) $customer->national_id,
                    'membership_jdate' => $membership ? Jalali::instance($membership)->format('Y/m/d') : '—',
                    'customer_group' => $membership && $membership->gt($to) ? 'عضویت پس از بازه' : ($membership && $membership->gte($from) ? 'جدید' : 'قدیمی'),
                ];
                foreach ($customer->loanFiles as $file) {
                    $contract = $this->finance->totalRepayableToman($file);
                    $current = $this->finance->loanInstallmentFinancialSnapshot($file, $contract);
                    $end = $this->finance->loanInstallmentFinancialSnapshot($file, $contract, $to);
                    $before = $this->finance->loanInstallmentFinancialSnapshot($file, $contract, $from->copy()->subDay());
                    if ($file->loan_start_date && $file->loan_start_date->betweenIncluded($from, $to)) {
                        $row['loans_count']++;
                        $row['principal_toman'] += (int) $file->amount_toman;
                        $row['profit_toman'] += $this->finance->calculateLoanProfitToman(
                            (int) $file->amount_toman, (float) $file->effective_interest_rate,
                            (string) $file->profit_calculation_method, (int) $file->installments_count,
                            (int) $file->installment_interval_count, (string) $file->installment_interval_unit
                        );
                    }
                    $row['paid_toman'] += max(0, $end['total_paid_toman'] - $before['total_paid_toman']);
                    foreach ($end['installment_balances'] as $id => $balance) {
                        $previous = $before['installment_balances'][$id];
                        $row['late_assessed_toman'] += max(0, $balance['late_fee_assessed_toman'] - $previous['late_fee_assessed_toman']);
                        $row['late_paid_toman'] += max(0, $balance['late_fee_paid_toman'] - $previous['late_fee_paid_toman']);
                        $row['early_earned_toman'] += max(0, $balance['early_benefit_earned_toman'] - $previous['early_benefit_earned_toman']);
                    }
                    $row['remaining_toman'] += $current['remaining_amount_toman'];
                    $row['late_remaining_toman'] += $current['late_fee_payable_toman'];
                    $row[$current['settled_by_balance'] ? 'settled_count' : 'current_count']++;
                    $row['review_count'] += (int) $current['requires_schedule_review'];
                }
                $row['wallet_toman'] = (int) ($customer->wallet?->balance_toman ?? 0);
                foreach ($guarantees->get($customer->id, collect()) as $guarantee) {
                    if ($guarantee->isMarkedReturned()) {
                        continue;
                    }
                    $amount = max(0, (int) ($guarantee->meta['amount_toman'] ?? 0));
                    $row['guarantee_toman'] += $amount;
                    $row['unvalued_guarantees_count'] += (int) ($amount === 0);
                }
                foreach ($summary as $key => $value) {
                    $summary[$key] += $row[$key];
                }
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /** @return list<string> */
    public function excelDataRow(array $row): array
    {
        return array_map(static fn (string $key): string => (string) ($row[$key] ?? ''), array_keys($this->columns()));
    }
}
