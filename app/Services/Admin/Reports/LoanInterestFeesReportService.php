<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\LoanType;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LoanInterestFeesReportService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $financeCalculator,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon}
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

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    public function searchCustomersForSelect(?string $term, int $limit = 40): array
    {
        $query = Customer::query()->orderBy('last_name')->orderBy('first_name')->orderBy('id');
        $search = $term !== null ? trim($term) : '';

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $w) use ($like, $search): void {
                $w->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('national_id', 'like', $like);
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                if ($digits !== '' && strlen($digits) >= 4) {
                    $w->orWhere('mobile', 'like', '%'.$digits.'%')
                        ->orWhere('national_id', 'like', '%'.$digits.'%');
                }
            });
        }

        return $query
            ->limit(max(1, min(80, $limit)))
            ->get()
            ->map(function (Customer $customer): array {
                $name = trim($customer->first_name.' '.$customer->last_name);
                $text = $name !== '' ? $name : 'مشتری #'.$customer->id;
                $code = trim((string) ($customer->customer_code ?? ''));
                $mobile = trim((string) ($customer->mobile ?? ''));
                if ($code !== '') {
                    $text .= ' — کد '.Jalali::enToFaNumbers($code);
                }
                if ($mobile !== '') {
                    $text .= ' — '.Jalali::enToFaNumbers($mobile);
                }

                return ['id' => (int) $customer->id, 'text' => $text];
            })
            ->all();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function fetchResult(
        Carbon $from,
        Carbon $to,
        string $search = '',
        ?int $customerId = null,
        string $settledFilter = '',
    ): array {
        $query = CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->whereBetween('loan_start_date', [$from->toDateString(), $to->toDateString()])
            ->with(['customer:id,first_name,last_name,national_id,mobile,customer_code', 'loanType:id,title', 'installments'])
            ->orderByDesc('loan_start_date')
            ->orderByDesc('id');

        if ($customerId !== null && $customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('loan_code', 'like', $like)
                    ->orWhere('sub_file_number', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('customer', function (Builder $c) use ($like): void {
                        $c->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('national_id', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('customer_code', 'like', $like);
                    })
                    ->orWhereHas('loanType', static fn (Builder $lt): Builder => $lt->where('title', 'like', $like));
            });
        }

        if ($settledFilter === 'yes') {
            $query->where('is_settled', true);
        } elseif ($settledFilter === 'no') {
            $query->where('is_settled', false);
        }

        /** @var Collection<int, CustomerLoanFile> $files */
        $files = $query->get();

        $summary = [
            'loan_count' => 0,
            'principal_total' => 0,
            'profit_total' => 0,
            'down_payment_total' => 0,
            'repayable_total' => 0,
            'discount_total' => 0,
            'paid_total' => 0,
            'remaining_total' => 0,
        ];

        $rows = [];
        foreach ($files as $file) {
            $row = $this->mapRow($file);
            $rows[] = $row;

            $summary['loan_count']++;
            $summary['principal_total'] += (int) ($row['principal_toman'] ?? 0);
            $summary['profit_total'] += (int) ($row['profit_toman'] ?? 0);
            $summary['down_payment_total'] += (int) ($row['down_payment_toman'] ?? 0);
            $summary['repayable_total'] += (int) ($row['total_repayable_toman'] ?? 0);
            $summary['discount_total'] += (int) ($row['discount_toman'] ?? 0);
            $summary['paid_total'] += (int) ($row['paid_amount_toman'] ?? 0);
            $summary['remaining_total'] += (int) ($row['remaining_amount_toman'] ?? 0);
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerLoanFile $file): array
    {
        $customer = $file->customer;
        $profitMethod = (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY);
        $profit = $this->financeCalculator->calculateLoanProfitToman(
            (int) $file->amount_toman,
            (float) $file->effective_interest_rate,
            $profitMethod,
            (int) $file->installments_count,
            (int) $file->installment_interval_count,
            (string) $file->installment_interval_unit
        );
        $totalRepayable = max(0, ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman);
        $snap = $this->financeCalculator->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $remainingAfterDiscount = $file->is_settled
            ? 0
            : max(0, (int) ($snap['schedule_remaining_toman'] ?? 0) - $discount);

        $customerName = $customer !== null ? trim($customer->first_name.' '.$customer->last_name) : '—';
        $loanTitleParts = array_filter([
            (string) $file->loan_code,
            (string) ($file->loanType?->title ?? ''),
            trim((string) ($file->sub_file_number ?? '')) !== '' ? 'زیرپرونده: '.$file->sub_file_number : null,
        ]);

        return [
            'loan_file_id' => (int) $file->id,
            'customer_id' => (int) $file->customer_id,
            'loan_title' => implode(' — ', $loanTitleParts),
            'loan_code' => (string) $file->loan_code,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'customer_name' => $customerName !== '' ? $customerName : '—',
            'customer_national_id' => $customer !== null ? (string) $customer->national_id : '—',
            'customer_mobile' => $customer !== null ? (string) $customer->mobile : '—',
            'principal_toman' => (int) $file->amount_toman,
            'profit_toman' => $profit,
            'down_payment_toman' => (int) $file->down_payment_toman,
            'total_repayable_toman' => $totalRepayable,
            'effective_interest_rate' => (float) $file->effective_interest_rate,
            'effective_interest_rate_label' => $this->formatInterestRateLabel((float) $file->effective_interest_rate),
            'profit_calculation_method' => $profitMethod,
            'profit_calculation_method_label' => $this->profitMethodLabel($profitMethod),
            'installments_count' => (int) $file->installments_count,
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'loan_start_jdate' => $file->loan_start_date
                ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d'))
                : '—',
            'is_settled' => (bool) $file->is_settled,
            'is_settled_label' => $file->is_settled ? 'بلی' : 'خیر',
            'paid_amount_toman' => (int) ($snap['total_paid_toman'] ?? 0),
            'remaining_amount_toman' => $remainingAfterDiscount,
            'discount_toman' => $discount,
            'customer_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => (int) $file->customer_id,
            ]),
            'loan_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => (int) $file->customer_id,
                'open_loan_installments' => (int) $file->id,
            ]),
        ];
    }

    private function profitMethodLabel(string $profitMethod): string
    {
        return $profitMethod === LoanType::PROFIT_BANK
            ? 'بانکی (روز شمار)'
            : 'ماهانه (روز شمار)';
    }

    private function formatInterestRateLabel(float $rate): string
    {
        if ($rate <= 0) {
            return '—';
        }

        $formatted = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');

        return Jalali::enToFaNumbers($formatted).'%';
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'مشتری',
            'پرونده وام',
            'اصل (تومان)',
            'بهره (تومان)',
            'پیش‌پرداخت/کارمزد (تومان)',
            'قابل بازپرداخت (تومان)',
            'نرخ بهره',
            'روش محاسبه',
            'تعداد اقساط',
            'مبلغ قسط',
            'تاریخ شروع',
            'پرداختی',
            'مانده',
            'تخفیف',
            'تسویه',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        $customerParts = array_filter([
            (string) ($row['customer_name'] ?? ''),
            ($row['customer_mobile'] ?? '') !== '—'
                ? $this->formatExportNumber((string) $row['customer_mobile'])
                : null,
            ($row['customer_national_id'] ?? '') !== '' && ($row['customer_national_id'] ?? '') !== '—'
                ? 'کد ملی: '.$this->formatExportNumber((string) $row['customer_national_id'])
                : null,
        ]);

        $loanParts = array_filter([
            'پرونده: '.$this->formatExportNumber((string) ($row['loan_code'] ?? '')),
            ($row['loan_type_title'] ?? '') !== '—' ? (string) ($row['loan_type_title'] ?? '') : null,
        ]);

        return [
            $customerParts !== [] ? implode(' | ', $customerParts) : '—',
            $loanParts !== [] ? implode(' | ', $loanParts) : '—',
            $this->formatExportAmount((int) ($row['principal_toman'] ?? 0)),
            $this->formatExportAmount((int) ($row['profit_toman'] ?? 0)),
            $this->formatExportAmount((int) ($row['down_payment_toman'] ?? 0)),
            $this->formatExportAmount((int) ($row['total_repayable_toman'] ?? 0)),
            (string) ($row['effective_interest_rate_label'] ?? '—'),
            (string) ($row['profit_calculation_method_label'] ?? '—'),
            $this->formatExportNumber((string) ($row['installments_count'] ?? 0)),
            $this->formatExportAmount((int) ($row['installment_amount_toman'] ?? 0)),
            (string) ($row['loan_start_jdate'] ?? '—'),
            $this->formatExportAmount((int) ($row['paid_amount_toman'] ?? 0)),
            $this->formatExportAmount((int) ($row['remaining_amount_toman'] ?? 0)),
            (int) ($row['discount_toman'] ?? 0) > 0
                ? $this->formatExportAmount((int) $row['discount_toman'])
                : '',
            (string) ($row['is_settled_label'] ?? '—'),
        ];
    }

    private function formatExportAmount(int $toman): string
    {
        return $this->formatExportNumber(number_format($toman, 0, '.', ','));
    }

    private function formatExportNumber(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '—') {
            return '—';
        }

        return Jalali::enToFaNumbers($trimmed);
    }
}
