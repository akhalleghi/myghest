<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\CustomerLoanInstallmentPayment;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DepositsByDateReportService
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
        string $paymentMethodFilter = '',
    ): array {
        $query = CustomerLoanInstallmentPayment::query()
            ->whereNotNull('deposited_at')
            ->whereBetween('deposited_at', [$from->toDateString(), $to->toDateString()])
            ->whereHas('installment.loanFile', static function (Builder $lf): void {
                $lf->whereNull('revoked_at');
            })
            ->with([
                'installment' => static function ($inst): void {
                    $inst->with([
                        'loanFile' => static function ($lf): void {
                            $lf->with(['customer:id,first_name,last_name,national_id,mobile,customer_code', 'loanType:id,title']);
                        },
                    ]);
                },
            ])
            ->orderByDesc('deposited_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $methodLabels = CustomerLoanInstallmentPayment::methodLabels();
            $methodKeys = $this->methodKeysMatchingSearch($search, $methodLabels);
            $query->where(function (Builder $q) use ($like, $methodKeys): void {
                $q->where('note', 'like', $like);
                if ($methodKeys !== []) {
                    $q->orWhereIn('payment_method', $methodKeys);
                }
                $q->orWhereHas('installment', function (Builder $inst) use ($like): void {
                        $inst->whereHas('loanFile', function (Builder $lf) use ($like): void {
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
            });
        }

        if ($paymentMethodFilter !== '') {
            $query->where('payment_method', $paymentMethodFilter);
        }

        /** @var Collection<int, CustomerLoanInstallmentPayment> $payments */
        $payments = $query->get();

        return $payments->map(fn (CustomerLoanInstallmentPayment $payment): array => $this->mapRow($payment))->values()->all();
    }

    /**
     * جمع واریزهای بازهٔ تاریخ (بدون فیلتر نحوه پرداخت / جستجو — برای کارت‌های خلاصه بالای گزارش).
     *
     * @return array{
     *     total_amount_toman: int,
     *     total_amount_formatted: string,
     *     count: int,
     *     by_payment_method: list<array{key: string, label: string, amount_toman: int, amount_formatted: string, count: int}>
     * }
     */
    public function summarizeRange(Carbon $from, Carbon $to): array
    {
        /** @var Collection<int, object{payment_method: string, cnt: int|string, total_amount: int|string|null}> $grouped */
        $grouped = CustomerLoanInstallmentPayment::query()
            ->whereNotNull('deposited_at')
            ->whereBetween('deposited_at', [$from->toDateString(), $to->toDateString()])
            ->whereHas('installment.loanFile', static function (Builder $lf): void {
                $lf->whereNull('revoked_at');
            })
            ->selectRaw('payment_method, COUNT(*) as cnt, COALESCE(SUM(amount_toman), 0) as total_amount')
            ->groupBy('payment_method')
            ->get();

        $labels = CustomerLoanInstallmentPayment::methodLabels();
        $amountsByKey = [];
        $countsByKey = [];
        $totalAmount = 0;
        $totalCount = 0;

        foreach ($grouped as $row) {
            $key = (string) $row->payment_method;
            $amount = (int) $row->total_amount;
            $count = (int) $row->cnt;
            $amountsByKey[$key] = $amount;
            $countsByKey[$key] = $count;
            $totalAmount += $amount;
            $totalCount += $count;
        }

        $byMethod = [];
        foreach ($labels as $key => $label) {
            $amount = (int) ($amountsByKey[$key] ?? 0);
            $count = (int) ($countsByKey[$key] ?? 0);
            if ($amount <= 0 && $count <= 0) {
                continue;
            }
            $byMethod[] = [
                'key' => $key,
                'label' => $label,
                'amount_toman' => $amount,
                'amount_formatted' => $this->formatAmount($amount),
                'count' => $count,
            ];
        }

        foreach ($amountsByKey as $key => $amount) {
            if (isset($labels[$key])) {
                continue;
            }
            $count = (int) ($countsByKey[$key] ?? 0);
            if ($amount <= 0 && $count <= 0) {
                continue;
            }
            $byMethod[] = [
                'key' => $key,
                'label' => $key,
                'amount_toman' => (int) $amount,
                'amount_formatted' => $this->formatAmount((int) $amount),
                'count' => $count,
            ];
        }

        return [
            'total_amount_toman' => $totalAmount,
            'total_amount_formatted' => $this->formatAmount($totalAmount),
            'count' => $totalCount,
            'by_payment_method' => $byMethod,
        ];
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'مشتری',
            'وام',
            'مبلغ قسط',
            'مبلغ واریزی',
            'تاریخ سررسید',
            'تاریخ واریز',
            'نحوه پرداخت',
            'دیرکرد/زودکرد',
            'توضیحات',
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
            (string) ($row['deposit_amount_formatted'] ?? ''),
            (string) ($row['due_jdate'] ?? ''),
            (string) ($row['deposit_jdate'] ?? ''),
            (string) ($row['payment_method_label'] ?? ''),
            (string) ($row['early_late_label'] ?? ''),
            (string) ($row['notes_text'] ?? ''),
            'مشاهده اقساط',
        ];
    }

    /**
     * @param  array<string, string>  $methodLabels
     * @return list<string>
     */
    private function methodKeysMatchingSearch(string $search, array $methodLabels): array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return [];
        }

        $keys = [];
        foreach ($methodLabels as $key => $label) {
            if (mb_strpos(mb_strtolower($label), $needle) !== false) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerLoanInstallmentPayment $payment): array
    {
        $inst = $payment->installment;
        $file = $inst?->loanFile;
        $customer = $file?->customer;
        $customerName = $customer !== null ? trim($customer->first_name.' '.$customer->last_name) : '—';

        $loanCode = (string) ($file?->loan_code ?? '—');
        $loanTypeTitle = (string) ($file?->loanType?->title ?? '—');
        $loanDescription = trim((string) ($file?->description ?? ''));

        $dueDate = $payment->reference_due_date ?? $inst?->due_date;
        $dueJdate = $dueDate !== null
            ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($dueDate))->format('Y/m/d'))
            : '—';

        $depositedAt = $payment->deposited_at !== null
            ? Carbon::parse($payment->deposited_at)->startOfDay()
            : null;
        $depositJdate = $depositedAt !== null
            ? Jalali::enToFaNumbers(Jalali::instance($depositedAt)->format('Y/m/d'))
            : '—';

        $methodLabels = CustomerLoanInstallmentPayment::methodLabels();
        $methodKey = (string) $payment->payment_method;
        $paymentMethodLabel = $methodLabels[$methodKey] ?? $methodKey;

        $depositAmount = (int) $payment->amount_toman;
        $installmentAmount = (int) ($inst?->amount_toman ?? 0);

        $earlyLate = $this->resolveEarlyLateLabel($dueDate, $depositedAt);

        $customerId = (int) ($file?->customer_id ?? 0);
        $loanFileId = (int) ($inst?->customer_loan_file_id ?? 0);
        $note = trim((string) ($payment->note ?? ''));

        return [
            'payment_id' => (int) $payment->id,
            'installment_id' => (int) ($inst?->id ?? 0),
            'loan_file_id' => $loanFileId,
            'customer_id' => $customerId,
            'sequence' => (int) ($inst?->sequence ?? 0),
            'customer_name' => $customerName !== '' ? $customerName : '—',
            'customer_national_id' => $customer !== null ? (string) $customer->national_id : '—',
            'customer_mobile' => $customer !== null ? (string) $customer->mobile : '—',
            'loan_code' => $loanCode,
            'loan_type_title' => $loanTypeTitle,
            'loan_description' => $loanDescription !== '' ? $loanDescription : null,
            'installment_amount_toman' => $installmentAmount,
            'installment_amount_formatted' => $this->formatAmount($installmentAmount),
            'deposit_amount_toman' => $depositAmount,
            'deposit_amount_formatted' => $this->formatAmount($depositAmount),
            'due_jdate' => $dueJdate,
            'deposit_jdate' => $depositJdate,
            'payment_method' => $methodKey,
            'payment_method_label' => $paymentMethodLabel,
            'early_late_label' => $earlyLate,
            'notes_text' => $note,
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
                $loanTypeTitle !== '—' ? $loanTypeTitle : null,
                'پرونده: '.Jalali::enToFaNumbers($loanCode),
                $loanDescription !== '' ? $loanDescription : null,
                $inst !== null ? 'قسط '.Jalali::enToFaNumbers((string) $inst->sequence) : null,
            ])),
        ];
    }

    private function resolveEarlyLateLabel(mixed $dueDate, ?Carbon $depositedAt): string
    {
        if ($dueDate === null || $depositedAt === null) {
            return '—';
        }

        $due = Carbon::parse($dueDate)->startOfDay();
        if ($depositedAt->lt($due)) {
            return 'زودکرد '.Jalali::enToFaNumbers((string) (int) $due->diffInDays($depositedAt)).' روز';
        }
        if ($depositedAt->gt($due)) {
            return 'دیرکرد '.Jalali::enToFaNumbers((string) (int) $depositedAt->diffInDays($due)).' روز';
        }

        return 'به‌موقع';
    }

    private function formatAmount(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format($toman, 0, '.', ','));
    }
}
