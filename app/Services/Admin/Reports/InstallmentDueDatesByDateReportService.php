<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class InstallmentDueDatesByDateReportService
{
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
            $fromJdate = Jalali::instance($from)->format('Y/m/d');
            $toJdate = Jalali::instance($to)->format('Y/m/d');
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
            'from_jdate' => $fromJdate,
            'to_jdate' => $toJdate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(
        Carbon $from,
        Carbon $to,
        string $search = '',
        string $paymentStatusFilter = '',
        string $overdueFilter = '',
    ): array {
        $query = CustomerLoanInstallment::query()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('loanFile', static function (Builder $lf): void {
                $lf->whereNull('revoked_at');
            })
            ->with([
                'loanFile' => static function ($lf): void {
                    $lf->with(['customer:id,first_name,last_name,national_id,mobile,customer_code', 'loanType:id,title']);
                },
                'payments',
                'recordedByAdmin:id,name,username',
            ])
            ->orderBy('due_date')
            ->orderBy('customer_loan_file_id')
            ->orderBy('sequence');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereHas('loanFile', function (Builder $lf) use ($like): void {
                    $lf->where('loan_code', 'like', $like)
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
            });
        }

        if ($paymentStatusFilter === 'paid') {
            $query->whereColumn('paid_amount_toman', '>=', 'amount_toman');
        } elseif ($paymentStatusFilter === 'unpaid') {
            $query->where('paid_amount_toman', '<=', 0);
        } elseif ($paymentStatusFilter === 'partial') {
            $query->where('paid_amount_toman', '>', 0)
                ->whereColumn('paid_amount_toman', '<', 'amount_toman');
        }

        $today = Carbon::today()->toDateString();
        if ($overdueFilter === 'yes') {
            $query->whereDate('due_date', '<', $today)
                ->whereColumn('paid_amount_toman', '<', 'amount_toman');
        } elseif ($overdueFilter === 'no') {
            $query->where(function (Builder $q) use ($today): void {
                $q->whereDate('due_date', '>=', $today)
                    ->orWhereColumn('paid_amount_toman', '>=', 'amount_toman');
            });
        }

        /** @var Collection<int, CustomerLoanInstallment> $installments */
        $installments = $query->get();

        return $installments->map(fn (CustomerLoanInstallment $inst): array => $this->mapRow($inst))->values()->all();
    }

    /**
     * آمار بازه/فیلتر فعلی گزارش سررسید اقساط.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     customers_count: int,
     *     installment_amount_total: int,
     *     installment_amount_formatted: string,
     *     paid_amount_total: int,
     *     paid_amount_formatted: string,
     *     unpaid_amount_total: int,
     *     unpaid_amount_formatted: string,
     *     installment_count: int
     * }
     */
    public function summarizeRows(array $rows): array
    {
        $customerIds = [];
        $installmentAmountTotal = 0;
        $paidAmountTotal = 0;
        $unpaidAmountTotal = 0;

        foreach ($rows as $row) {
            $customerId = (int) ($row['customer_id'] ?? 0);
            if ($customerId > 0) {
                $customerIds[$customerId] = true;
            }

            $amount = (int) ($row['installment_amount_toman'] ?? 0);
            $paid = (int) ($row['paid_amount_toman'] ?? 0);
            $installmentAmountTotal += $amount;
            $paidAmountTotal += max(0, $paid);
            $unpaidAmountTotal += max(0, $amount - $paid);
        }

        return [
            'customers_count' => count($customerIds),
            'installment_amount_total' => $installmentAmountTotal,
            'installment_amount_formatted' => $this->formatAmount($installmentAmountTotal),
            'paid_amount_total' => $paidAmountTotal,
            'paid_amount_formatted' => $this->formatAmount($paidAmountTotal),
            'unpaid_amount_total' => $unpaidAmountTotal,
            'unpaid_amount_formatted' => $this->formatAmount($unpaidAmountTotal),
            'installment_count' => count($rows),
        ];
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'مشتری',
            'اطلاعات وام',
            'مبلغ قسط',
            'مبلغ واریزی',
            'تاریخ سررسید',
            'تاریخ واریز',
            'نحوه پرداخت',
            'دیرکرد/زودکرد',
            'توضیحات',
            'پیامک',
            'عملیات',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        return [
            (string) ($row['customer_excel'] ?? ''),
            (string) ($row['loan_excel'] ?? ''),
            (string) ($row['installment_amount_formatted'] ?? ''),
            (string) ($row['paid_amount_formatted'] ?? ''),
            (string) ($row['due_jdate'] ?? ''),
            (string) ($row['paid_jdate'] ?? ''),
            (string) ($row['payment_methods_label'] ?? ''),
            (string) ($row['early_late_label'] ?? ''),
            (string) ($row['notes_text'] ?? ''),
            ! empty($row['sms_installment_id']) ? 'پ س م ت' : '',
            'مشاهده اقساط',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerLoanInstallment $inst): array
    {
        $file = $inst->loanFile;
        $customer = $file?->customer;
        $customerName = $customer !== null ? trim($customer->first_name.' '.$customer->last_name) : '—';

        $loanCode = (string) ($file?->loan_code ?? '—');
        $loanTypeTitle = (string) ($file?->loanType?->title ?? '—');
        $loanDescription = trim((string) ($file?->description ?? ''));

        $dueJdate = $inst->due_date
            ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($inst->due_date))->format('Y/m/d'))
            : '—';

        $paidAmount = (int) $inst->paid_amount_toman;
        $paidAt = $inst->paid_at !== null ? Carbon::parse($inst->paid_at)->startOfDay() : null;

        $paymentMeta = $this->resolvePaymentMeta($inst, $paidAt);
        $paidJdate = $paymentMeta['deposit_jdates_label'];

        $earlyLate = $this->resolveEarlyLateLabel($inst, $paidAt, $paidAmount);

        $customerId = (int) ($file?->customer_id ?? 0);
        $loanFileId = (int) $inst->customer_loan_file_id;

        return [
            'installment_id' => (int) $inst->id,
            'loan_file_id' => $loanFileId,
            'customer_id' => $customerId,
            'sequence' => (int) $inst->sequence,
            'customer_name' => $customerName !== '' ? $customerName : '—',
            'customer_national_id' => $customer !== null ? (string) $customer->national_id : '—',
            'customer_mobile' => $customer !== null ? (string) $customer->mobile : '—',
            'loan_code' => $loanCode,
            'loan_type_title' => $loanTypeTitle,
            'loan_description' => $loanDescription !== '' ? $loanDescription : null,
            'installment_amount_toman' => (int) $inst->amount_toman,
            'installment_amount_formatted' => $this->formatAmount((int) $inst->amount_toman),
            'paid_amount_toman' => $paidAmount,
            'paid_amount_formatted' => $paidAmount > 0 ? $this->formatAmount($paidAmount) : '—',
            'due_jdate' => $dueJdate,
            'paid_jdate' => $paidJdate,
            'payment_methods_label' => $paymentMeta['methods_label'],
            'early_late_label' => $earlyLate,
            'notes_text' => $paymentMeta['notes'],
            'sms_installment_id' => (int) $inst->id,
            'payment_status' => $this->resolvePaymentStatus($inst),
            'is_overdue' => $this->isOverdue($inst),
            'loan_locked' => (bool) ($file?->is_settled ?? false),
            'customer_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => $customerId,
            ]),
            'loan_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => $customerId,
                'open_loan_installments' => $loanFileId,
            ]),
            'customer_excel' => implode(' | ', array_filter([
                $customerName !== '' ? $customerName : '—',
                $customer !== null && (string) $customer->mobile !== '' ? Jalali::enToFaNumbers((string) $customer->mobile) : null,
                $customer !== null && (string) $customer->national_id !== '' ? 'کد ملی: '.Jalali::enToFaNumbers((string) $customer->national_id) : null,
            ])),
            'loan_excel' => implode(' | ', array_filter([
                'پرونده: '.Jalali::enToFaNumbers($loanCode),
                $loanTypeTitle !== '—' ? $loanTypeTitle : null,
                $loanDescription !== '' ? $loanDescription : null,
                'قسط '.Jalali::enToFaNumbers((string) $inst->sequence),
            ])),
        ];
    }

    /**
     * @return array{methods_label: string, notes: string, deposit_jdates_label: string}
     */
    private function resolvePaymentMeta(CustomerLoanInstallment $inst, ?Carbon $paidAt): array
    {
        $methodLabels = CustomerLoanInstallmentPayment::methodLabels();
        $methods = [];
        $notes = [];
        $depositJdates = [];

        foreach ($inst->payments as $payment) {
            $key = (string) $payment->payment_method;
            $methods[] = $methodLabels[$key] ?? $key;
            $note = trim((string) ($payment->note ?? ''));
            if ($note !== '') {
                $notes[] = $note;
            }
            if ($payment->deposited_at !== null) {
                $depositJdates[] = Jalali::enToFaNumbers(
                    Jalali::instance(Carbon::parse($payment->deposited_at)->startOfDay())->format('Y/m/d')
                );
            }
        }

        if ($methods === [] && (int) $inst->paid_amount_toman > 0) {
            $methods[] = $methodLabels[CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED]
                ?? CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED;
        }

        if ($depositJdates === [] && $paidAt !== null) {
            $depositJdates[] = Jalali::enToFaNumbers(Jalali::instance($paidAt)->format('Y/m/d'));
        }

        $methods = array_values(array_unique($methods));
        $notes = array_values(array_unique($notes));
        $depositJdates = array_values(array_unique($depositJdates));

        return [
            'methods_label' => $methods !== [] ? implode('، ', $methods) : '—',
            'notes' => $notes !== [] ? implode(' | ', $notes) : '',
            'deposit_jdates_label' => $depositJdates !== [] ? implode(' | ', $depositJdates) : '—',
        ];
    }

    private function resolvePaymentStatus(CustomerLoanInstallment $inst): string
    {
        $paid = (int) $inst->paid_amount_toman;
        $amount = (int) $inst->amount_toman;

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= $amount) {
            return 'paid';
        }

        return 'partial';
    }

    private function isOverdue(CustomerLoanInstallment $inst): bool
    {
        if ((int) $inst->paid_amount_toman >= (int) $inst->amount_toman) {
            return false;
        }

        return Carbon::parse($inst->due_date)->startOfDay()->lt(Carbon::today());
    }

    private function resolveEarlyLateLabel(CustomerLoanInstallment $inst, ?Carbon $paidAt, int $paidAmount): string
    {
        if ($paidAt === null || $paidAmount <= 0) {
            return '—';
        }

        $due = Carbon::parse($inst->due_date)->startOfDay();
        if ($paidAt->lt($due)) {
            return 'زودکرد '.Jalali::enToFaNumbers((string) (int) $due->diffInDays($paidAt)).' روز';
        }
        if ($paidAt->gt($due)) {
            return 'دیرکرد '.Jalali::enToFaNumbers((string) (int) $paidAt->diffInDays($due)).' روز';
        }

        return 'به‌موقع';
    }

    private function formatAmount(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format($toman, 0, '.', ','));
    }
}
