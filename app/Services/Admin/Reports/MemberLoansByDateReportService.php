<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\LoanType;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class MemberLoansByDateReportService
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

        foreach ([[$fromJdate, $from], [$toJdate, $to]] as [$input, $parsed]) {
            if (trim((string) $input) === '') {
                continue;
            }
            $normalized = JalaliInputParser::toEnglishDigits(trim($input));
            if (! preg_match('~^(\d{4})/(\d{1,2})/(\d{1,2})$~D', $normalized, $parts)
                || $parsed === null || Jalali::instance($parsed)->format('Y/m/d') !== sprintf('%04d/%02d/%02d', (int) $parts[1], (int) $parts[2], (int) $parts[3])) {
                throw ValidationException::withMessages(['from_jdate' => 'تاریخ معتبر شمسی با قالب سال/ماه/روز وارد کنید.']);
            }
        }
        $hasInput = trim((string) $fromJdate) !== '' || trim((string) $toJdate) !== '';
        if ($hasInput && ($from === null || $to === null)) {
            throw ValidationException::withMessages(['from_jdate' => 'هر دو تاریخ شروع و پایان را به‌صورت معتبر وارد کنید.']);
        }
        if ($from === null || $to === null) {
            $today = Carbon::today();
            $jToday = Jalali::instance($today);
            $from = Carbon::createFromTimestamp($jToday->clone()->startYear()->getTimestamp(), $today->getTimezone())->startOfDay();
            $to = $today->copy()->endOfDay();
            $fromJdate = Jalali::instance($from)->format('Y/m/d');
            $toJdate = Jalali::instance($to)->format('Y/m/d');
        } else {
            if ($from->gt($to)) {
                throw ValidationException::withMessages(['from_jdate' => 'تاریخ شروع باید قبل یا برابر تاریخ پایان باشد.']);
            } else {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_jdate' => $fromJdate,
            'to_jdate' => $toJdate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(Carbon $from, Carbon $to, string $search = '', string $settledFilter = '', array $loanTypeIds = []): array
    {
        $query = CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->whereDate('loan_start_date', '>=', $from->toDateString())
            ->whereDate('loan_start_date', '<=', $to->toDateString())
            ->with(['customer:id,first_name,last_name,national_id,mobile,customer_code', 'loanType:id,title,daily_late_coefficient,daily_early_coefficient', 'installments.payments'])
            ->orderByDesc('loan_start_date')
            ->orderByDesc('id');

        if ($loanTypeIds !== []) {
            $query->whereIn('loan_type_id', $loanTypeIds);
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

        /** @var Collection<int, CustomerLoanFile> $files */
        $files = $query->get();

        return $files->map(fn (CustomerLoanFile $file): array => $this->mapRow($file))
            ->filter(static fn (array $row): bool => ! ($settledFilter === 'yes' && ! $row['is_settled']) && ! ($settledFilter === 'no' && $row['is_settled']))
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerLoanFile $file): array
    {
        $customer = $file->customer;
        $profit = $this->financeCalculator->calculateLoanProfitToman(
            (int) $file->amount_toman,
            (float) $file->effective_interest_rate,
            (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $file->installments_count,
            (int) $file->installment_interval_count,
            (string) $file->installment_interval_unit
        );
        $totalRepayable = max(0, ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman);
        $snap = $this->financeCalculator->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $remainingAfterDiscount = $file->is_settled
            ? 0
            : (int) $snap['remaining_amount_toman'];
        $lateCoef = $file->dailyLateCoefficient();
        $lateFee = (int) $snap['late_fee_payable_toman'];

        $instColl = $file->installments;
        $unpaidInsts = $instColl->filter(static fn (CustomerLoanInstallment $i): bool => (int) ($snap['installment_payables'][(int) $i->id]['remaining_toman'] ?? 0) > 0);
        $unpaidCount = $unpaidInsts->count();
        $unpaidAmount = (int) $unpaidInsts->sum(static fn (CustomerLoanInstallment $i): int => (int) ($snap['installment_payables'][(int) $i->id]['remaining_toman'] ?? 0));

        $now = Carbon::now()->startOfDay();
        $overdueCount = (int) $unpaidInsts->filter(static function (CustomerLoanInstallment $i) use ($now): bool {
            return Carbon::parse($i->due_date)->startOfDay()->lt($now);
        })->count();

        $smsInstallment = $unpaidInsts->sortBy('sequence')->first()
            ?? $instColl->sortByDesc('sequence')->first();

        $loanTitleParts = array_filter([
            (string) $file->loan_code,
            (string) ($file->loanType?->title ?? ''),
            trim((string) ($file->sub_file_number ?? '')) !== '' ? 'زیرپرونده: '.$file->sub_file_number : null,
            trim((string) ($file->description ?? '')) !== '' ? $file->description : null,
        ]);

        $customerName = $customer !== null ? trim($customer->first_name.' '.$customer->last_name) : '—';

        return [
            'loan_file_id' => (int) $file->id,
            'customer_id' => (int) $file->customer_id,
            'loan_title' => implode(' — ', $loanTitleParts),
            'loan_code' => (string) $file->loan_code,
            'loan_type_id' => (int) $file->loan_type_id,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'loan_description' => trim((string) ($file->description ?? '')) !== ''
                ? trim((string) $file->description)
                : null,
            'customer_name' => $customerName !== '' ? $customerName : '—',
            'customer_national_id' => $customer !== null ? (string) $customer->national_id : '—',
            'customer_mobile' => $customer !== null ? (string) $customer->mobile : '—',
            'principal_toman' => (int) $file->amount_toman,
            'total_repayable_toman' => $snap['debt_base_total_toman'],
            'early_benefit_toman' => $snap['early_benefit_toman'],
            'installments_count' => (int) $file->installments_count,
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'loan_start_jdate' => $file->loan_start_date
                ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d'))
                : '—',
            'is_settled' => (bool) $snap['settled_by_balance'],
            'is_settled_label' => $snap['requires_schedule_review'] ? 'نیاز به بررسی اقساط' : ($snap['settled_by_balance'] ? 'بلی' : 'خیر'),
            'requires_schedule_review' => $snap['requires_schedule_review'],
            'paid_installments_count' => (int) ($snap['paid_installments_count'] ?? 0),
            'paid_amount_toman' => (int) ($snap['total_paid_toman'] ?? 0),
            'remaining_installments_count' => $unpaidCount,
            'remaining_amount_toman' => $remainingAfterDiscount,
            'overdue_installments_count' => $overdueCount,
            'late_fee_toman' => $lateFee,
            'discount_toman' => $discount,
            'sms_installment_id' => $smsInstallment !== null ? (int) $smsInstallment->id : null,
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

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'عنوان وام',
            'مشتری',
            'مبلغ وام',
            'اقساط',
            'مبلغ قسط',
            'شروع',
            'تسویه',
            'پرداختی',
            'مانده',
            'تأخیر',
            'تخفیف',
            'پیامک',
            'شماره پرونده',
            'نام مشتری',
            'موبایل',
            'کد ملی',
            'اصل وام (تومان)',
            'کل بازپرداخت اقساط (تومان)',
            'تعداد اقساط معوق',
            'دیرکرد پرداخت‌نشده (تومان)',
            'زودکرد (تومان)',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        $loanParts = array_filter([
            'پرونده: '.$this->formatExportNumber((string) ($row['loan_code'] ?? '')),
            ($row['loan_type_title'] ?? '') !== '—' ? (string) ($row['loan_type_title'] ?? '') : null,
            ! empty($row['loan_description']) ? (string) $row['loan_description'] : null,
        ]);

        $nationalId = (string) ($row['customer_national_id'] ?? '');
        $customerParts = array_filter([
            (string) ($row['customer_name'] ?? ''),
            ($row['customer_mobile'] ?? '') !== '—'
                ? $this->formatExportNumber((string) $row['customer_mobile'])
                : null,
            $nationalId !== '' && $nationalId !== '—'
                ? 'کد ملی: '.$this->formatExportNumber($nationalId)
                : null,
        ]);

        $principal = $this->formatExportAmount((int) ($row['principal_toman'] ?? 0));
        $total = $this->formatExportAmount((int) ($row['total_repayable_toman'] ?? 0));

        $delay = '';
        $overdueCount = (int) ($row['overdue_installments_count'] ?? 0);
        if ($overdueCount > 0) {
            $delay = $this->formatExportNumber((string) $overdueCount).' قسط معوق';
            $lateFee = (int) ($row['late_fee_toman'] ?? 0);
            if ($lateFee > 0) {
                $delay .= ' — '.$this->formatExportAmount($lateFee);
            }
        }

        $discount = (int) ($row['discount_toman'] ?? 0);

        return [
            $loanParts !== [] ? implode(' | ', $loanParts) : '—',
            $customerParts !== [] ? implode(' | ', $customerParts) : '—',
            $principal.' | '.$total,
            $this->formatExportNumber((string) ($row['installments_count'] ?? 0)),
            $this->formatExportAmount((int) ($row['installment_amount_toman'] ?? 0)),
            (string) ($row['loan_start_jdate'] ?? '—'),
            (string) ($row['is_settled_label'] ?? '—'),
            $this->formatExportAmount((int) ($row['paid_amount_toman'] ?? 0)),
            $this->formatExportAmount((int) ($row['remaining_amount_toman'] ?? 0)),
            $delay,
            $discount > 0 ? $this->formatExportAmount($discount) : '',
            ! empty($row['sms_installment_id']) ? 'پ س م ت' : '',
            (string) ($row['loan_code'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_mobile'] ?? ''),
            (string) ($row['customer_national_id'] ?? ''),
            (string) ($row['principal_toman'] ?? 0),
            (string) ($row['total_repayable_toman'] ?? 0),
            (string) ($row['overdue_installments_count'] ?? 0),
            (string) ($row['late_fee_toman'] ?? 0),
            (string) ($row['early_benefit_toman'] ?? 0),
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
