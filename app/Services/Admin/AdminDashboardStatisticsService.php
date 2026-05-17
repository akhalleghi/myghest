<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerTransaction;
use App\Models\CustomerWalletTransaction;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Collection;

/**
 * آمار واقعی داشبورد ادمین (هم‌سو با منطق مالی پروندهٔ وام در سامانه).
 */
final class AdminDashboardStatisticsService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $financeCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $today = Carbon::now()->startOfDay();
        $activeFiles = CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->with(['installments', 'loanType'])
            ->get();

        $systemStats = $this->buildSystemStats($activeFiles);
        $overdue = $this->buildOverdueInstallmentStats($today);
        $depositsPending = $this->buildPendingDepositStats();
        $loanRequests = $this->buildLoanRequestStats($today);
        $disbursementDue = $this->buildDisbursementDueStats($today);
        $charts = $this->buildTwelveMonthCharts($today);

        return [
            'systemStatRows' => $systemStats,
            'summaryCards' => $this->buildSummaryCards($overdue, $depositsPending, $loanRequests, $disbursementDue),
            'tables' => $this->buildRecentTransactionTables(),
            'installmentChart' => $charts['installments'],
            'newLoansChart' => $charts['new_loans'],
        ];
    }

    /**
     * @param  Collection<int, CustomerLoanFile>  $activeFiles
     * @return list<array{label: string, value: string}>
     */
    private function buildSystemStats(Collection $activeFiles): array
    {
        $customersCount = Customer::query()->count();
        $loanFilesCount = $activeFiles->count();
        $settledCount = $activeFiles->where('is_settled', true)->count();

        $principalSum = 0;
        $withProfitSum = 0;
        $withProfitAndLateSum = 0;
        $collectedSum = 0;
        $uncollectedSum = 0;
        $uncollectedNotDueSum = 0;

        foreach ($activeFiles as $file) {
            $principal = (int) $file->amount_toman;
            $totalRepayable = $this->financeCalculator->totalRepayableToman($file);
            $snapshot = $this->financeCalculator->loanInstallmentFinancialSnapshot($file, $totalRepayable);
            $lateCoef = (float) ($file->loanType?->daily_late_coefficient ?? 0);
            $lateFee = $this->financeCalculator->estimateLateFeeSoFarToman(
                $file->installments,
                $lateCoef,
                (int) ($snapshot['schedule_remaining_toman'] ?? 0)
            );
            $discount = (int) ($file->discount_amount_toman ?? 0);
            $remainingAfterDiscount = $file->is_settled
                ? 0
                : max(0, (int) ($snapshot['schedule_remaining_toman'] ?? 0) - $discount);
            $paid = (int) ($snapshot['total_paid_toman'] ?? 0);

            $principalSum += $principal;
            $withProfitSum += $totalRepayable;
            $withProfitAndLateSum += $totalRepayable + $lateFee;
            $collectedSum += $paid;
            $uncollectedSum += $remainingAfterDiscount + $lateFee;

            $now = Carbon::now()->startOfDay();
            foreach ($file->installments as $inst) {
                $unpaid = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
                if ($unpaid <= 0) {
                    continue;
                }
                $due = Carbon::parse($inst->due_date)->startOfDay();
                if ($due->gte($now)) {
                    $uncollectedNotDueSum += $unpaid;
                }
            }
        }

        return [
            ['label' => 'تعداد مشتری', 'value' => $this->formatCount($customersCount)],
            ['label' => 'تعداد پرونده وام', 'value' => $this->formatCount($loanFilesCount)],
            ['label' => 'تعداد وام تسویه', 'value' => $this->formatCount($settledCount)],
            ['label' => 'مجموع خالص وام ها', 'value' => $this->formatToman($principalSum)],
            ['label' => 'مجموع وام ها با احتساب بهره', 'value' => $this->formatToman($withProfitSum)],
            ['label' => 'مجموع وام ها با احتساب بهره و دیرکرد', 'value' => $this->formatToman($withProfitAndLateSum)],
            ['label' => 'مجموع وصول شده', 'value' => $this->formatToman($collectedSum)],
            ['label' => 'مجموع وصول نشده', 'value' => $this->formatToman($uncollectedSum)],
            ['label' => 'مجموع وصول نشده سررسید نشده', 'value' => $this->formatToman($uncollectedNotDueSum)],
        ];
    }

    /**
     * @return array{amount_toman: int, count: int}
     */
    private function buildOverdueInstallmentStats(Carbon $today): array
    {
        $installments = CustomerLoanInstallment::query()
            ->whereColumn('paid_amount_toman', '<', 'amount_toman')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereHas('loanFile', static function ($q): void {
                $q->whereNull('revoked_at')->where('is_settled', false);
            })
            ->with(['loanFile.loanType'])
            ->get();

        $amount = 0;
        foreach ($installments as $inst) {
            $unpaid = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
            $lateCoef = (float) ($inst->loanFile?->loanType?->daily_late_coefficient ?? 0);
            $amount += $unpaid + $this->financeCalculator->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);
        }

        return [
            'amount_toman' => $amount,
            'count' => $installments->count(),
        ];
    }

    /**
     * @return array{count: int, amount_toman: int}
     */
    private function buildPendingDepositStats(): array
    {
        $pending = CustomerDepositDeclaration::query()
            ->where('status', CustomerDepositDeclaration::STATUS_PENDING);

        return [
            'count' => (int) $pending->count(),
            'amount_toman' => (int) $pending->sum('amount_toman'),
        ];
    }

    /**
     * @return array{pending_expert: int, expert_re_review: int, new_today: int}
     */
    private function buildLoanRequestStats(Carbon $today): array
    {
        $base = CustomerLoanRequest::query()->whereNull('converted_to_loan_at');

        return [
            'pending_expert' => (int) (clone $base)
                ->where('status', CustomerLoanRequest::STATUS_PENDING_EXPERT_REVIEW)
                ->count(),
            'expert_re_review' => (int) (clone $base)
                ->where('status', CustomerLoanRequest::STATUS_EXPERT_RE_REVIEW)
                ->count(),
            'new_today' => (int) (clone $base)
                ->whereBetween('submitted_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->count(),
        ];
    }

    /**
     * سررسید واریز به مشتری (پرونده‌های فعال با تاریخ سررسید گذشته).
     *
     * @return array{amount_toman: int, count: int}
     */
    private function buildDisbursementDueStats(Carbon $today): array
    {
        $files = CustomerLoanFile::query()
            ->whereNull('revoked_at')
            ->where('is_settled', false)
            ->whereNotNull('disbursement_due_date')
            ->whereDate('disbursement_due_date', '<', $today->toDateString())
            ->get(['id', 'amount_toman']);

        return [
            'amount_toman' => (int) $files->sum('amount_toman'),
            'count' => $files->count(),
        ];
    }

    /**
     * @param  array{amount_toman: int, count: int}  $overdue
     * @param  array{count: int, amount_toman: int}  $depositsPending
     * @param  array{pending_expert: int, expert_re_review: int, new_today: int}  $loanRequests
     * @param  array{amount_toman: int, count: int}  $disbursementDue
     * @return list<array<string, mixed>>
     */
    private function buildSummaryCards(
        array $overdue,
        array $depositsPending,
        array $loanRequests,
        array $disbursementDue,
    ): array {
        return [
            [
                'widget_id' => 'summary-overdue',
                'title' => 'اقساط سررسید شده و معوق ها',
                'icon' => 'fa-calendar-xmark',
                'c' => '#8b5cf6',
                'clickable' => true,
                'href' => route('admin.customers.index'),
                'lines' => [
                    ['text' => $this->formatToman($overdue['amount_toman']), 'ltr' => true],
                    ['text' => $this->formatCount($overdue['count']).' مورد'],
                ],
                'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
            ],
            [
                'widget_id' => 'summary-deposit-notifications',
                'title' => 'اعلام واریزی های جدید',
                'icon' => 'fa-money-bill-transfer',
                'c' => '#ec4899',
                'clickable' => true,
                'href' => route('admin.deposit-declarations.index', ['status' => CustomerDepositDeclaration::STATUS_PENDING]),
                'lines' => [
                    ['text' => $this->formatCount($depositsPending['count']).' مورد'],
                ],
                'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
            ],
            [
                'widget_id' => 'summary-loan-requests',
                'title' => 'درخواست وام ها',
                'icon' => 'fa-file-circle-check',
                'c' => '#06b6d4',
                'clickable' => true,
                'href' => route('admin.loan-requests.index'),
                'lines' => [
                    ['k' => 'در انتظار بررسی کارشناس', 'v' => $this->formatCount($loanRequests['pending_expert'])],
                    ['k' => 'بررسی مجدد کارشناس', 'v' => $this->formatCount($loanRequests['expert_re_review'])],
                    ['k' => 'درخواست های جدید امروز', 'v' => $this->formatCount($loanRequests['new_today'])],
                ],
                'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
            ],
            [
                'widget_id' => 'summary-sms-email',
                'title' => 'وضعیت پیامک',
                'icon' => 'fa-paper-plane',
                'c' => '#f97316',
                'clickable' => true,
                'href' => route('admin.sms.index'),
                'lines' => [],
                'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
            ],
            [
                'widget_id' => 'summary-counterparty-matured',
                'title' => 'سررسید شده های طرف حساب',
                'icon' => 'fa-user-clock',
                'c' => '#2563eb',
                'clickable' => true,
                'href' => route('admin.customers.index'),
                'lines' => [
                    ['text' => $this->formatToman($disbursementDue['amount_toman']), 'ltr' => true],
                    ['text' => $this->formatCount($disbursementDue['count']).' مورد'],
                ],
                'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentTransactionTables(): array
    {
        return [
            $this->paymentTable(
                'tbl-online-installments',
                'واریز قسط‌های آنلاین',
                '#6366f1',
                [
                    CustomerLoanInstallmentPayment::METHOD_ONLINE,
                    CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE,
                ],
            ),
            $this->paymentTable(
                'tbl-bank-transactions',
                'تراکنش‌های بانک',
                '#06b6d4',
                [CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER],
            ),
            $this->paymentTable(
                'tbl-fund-transactions',
                'تراکنش‌های صندوق',
                '#10b981',
                [CustomerLoanInstallmentPayment::METHOD_CASH],
            ),
            $this->specialTransactionsTable(),
        ];
    }

    /**
     * @param  list<string>  $methods
     * @return array<string, mixed>
     */
    private function paymentTable(string $widgetId, string $title, string $color, array $methods): array
    {
        $payments = CustomerLoanInstallmentPayment::query()
            ->whereIn('payment_method', $methods)
            ->with(['installment.loanFile.customer'])
            ->orderByDesc('deposited_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $rows = $payments->map(function (CustomerLoanInstallmentPayment $pay): array {
            $date = $pay->deposited_at !== null
                ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($pay->deposited_at))->format('Y/m/d'))
                : '—';
            $amount = (int) $pay->amount_toman;
            $methodLabel = CustomerLoanInstallmentPayment::methodLabels()[$pay->payment_method] ?? $pay->payment_method;
            $customer = $pay->installment?->loanFile?->customer;
            $customerLabel = $customer !== null
                ? trim($customer->first_name.' '.$customer->last_name)
                : '—';
            if ($customerLabel === '') {
                $customerLabel = 'مشتری #'.(string) ($customer?->id ?? '—');
            }

            return [
                $date,
                $amount > 0 ? $this->formatTomanPlain($amount) : '—',
                '—',
                '—',
                $methodLabel.' — '.$customerLabel,
            ];
        })->all();

        return [
            'widget_id' => $widgetId,
            'title' => $title,
            'color' => $color,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specialTransactionsTable(): array
    {
        $items = [];

        $walletTxs = CustomerWalletTransaction::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        foreach ($walletTxs as $tx) {
            $at = $tx->created_at !== null ? Carbon::parse($tx->created_at) : Carbon::now();
            $date = Jalali::enToFaNumbers(Jalali::instance($at)->format('Y/m/d'));
            $amount = (int) $tx->amount_toman;
            $isDeposit = $tx->direction === CustomerWalletTransaction::DIRECTION_DEPOSIT;
            $items[] = [
                'ts' => $at->getTimestamp(),
                'row' => [
                    $date,
                    $isDeposit ? $this->formatTomanPlain($amount) : '—',
                    $isDeposit ? '—' : $this->formatTomanPlain($amount),
                    $tx->balance_after_toman !== null ? $this->formatTomanPlain((int) $tx->balance_after_toman) : '—',
                    trim((string) ($tx->description ?? '')) !== ''
                        ? (string) $tx->description
                        : 'تراکنش کیف پول',
                ],
            ];
        }

        $gatewayTxs = CustomerTransaction::query()
            ->where('status', CustomerTransaction::STATUS_COMPLETED)
            ->whereIn('kind', [
                CustomerTransaction::KIND_INSTALLMENT_ONLINE_PAYMENT,
                CustomerTransaction::KIND_FULL_SETTLEMENT_ONLINE_PAYMENT,
                CustomerTransaction::KIND_INSTALLMENT_WALLET_PAYMENT,
                CustomerTransaction::KIND_FULL_SETTLEMENT_WALLET_PAYMENT,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        foreach ($gatewayTxs as $tx) {
            $at = Carbon::parse($tx->created_at);
            $date = Jalali::enToFaNumbers(Jalali::instance($at)->format('Y/m/d'));
            $amount = (int) $tx->amount_toman;
            $items[] = [
                'ts' => $at->getTimestamp(),
                'row' => [
                    $date,
                    $amount > 0 ? $this->formatTomanPlain($amount) : '—',
                    '—',
                    '—',
                    (string) ($tx->title ?? 'تراکنش سیستمی'),
                ],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
        $rows = array_map(static fn (array $item): array => $item['row'], array_slice($items, 0, 10));

        return [
            'widget_id' => 'tbl-special-box',
            'title' => 'جعبه‌شکن / تراکنش ویژه',
            'color' => '#ec4899',
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, polyline: string}
     */
    private function buildTwelveMonthCharts(Carbon $today): array
    {
        $buckets = $this->lastTwelveJalaliMonthBuckets($today);

        $installmentValues = [];
        $newLoanValues = [];

        foreach ($buckets as $bucket) {
            /** @var Carbon $start */
            $start = $bucket['start'];
            /** @var Carbon $end */
            $end = $bucket['end'];

            $installmentValues[] = (int) CustomerLoanInstallmentPayment::query()
                ->whereBetween('deposited_at', [$start->toDateString(), $end->toDateString()])
                ->sum('amount_toman');

            $newLoanValues[] = (int) CustomerLoanFile::query()
                ->whereNull('revoked_at')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_toman');
        }

        return [
            'installments' => $this->buildChartPayload(
                $buckets,
                $installmentValues,
                '#0ea5e9',
                'مجموع وصول اقساط',
            ),
            'new_loans' => $this->buildChartPayload(
                $buckets,
                $newLoanValues,
                '#22c55e',
                'مجموع مبلغ وام‌های جدید',
            ),
        ];
    }

    /**
     * @param  list<array{label: string, period: string, start: Carbon, end: Carbon}>  $buckets
     * @param  list<int>  $values
     * @return array{color: string, title: string, series: list<array{label: string, period: string, value: int, valueLabel: string}>}
     */
    private function buildChartPayload(array $buckets, array $values, string $color, string $title): array
    {
        $series = [];
        foreach ($buckets as $i => $bucket) {
            $amount = (int) ($values[$i] ?? 0);
            $series[] = [
                'label' => (string) $bucket['label'],
                'period' => (string) $bucket['period'],
                'value' => $amount,
                'valueLabel' => $this->formatToman($amount),
            ];
        }

        return [
            'color' => $color,
            'title' => $title,
            'series' => $series,
        ];
    }

    /**
     * @return list<array{label: string, start: Carbon, end: Carbon}>
     */
    private function lastTwelveJalaliMonthBuckets(Carbon $today): array
    {
        $current = Jalali::instance($today);
        $buckets = [];

        for ($i = 11; $i >= 0; $i--) {
            $j = $current->clone()->subMonths($i);
            $start = Carbon::createFromTimestamp($j->clone()->startMonth()->getTimestamp())->startOfDay();
            $end = Carbon::createFromTimestamp($j->clone()->endMonth()->getTimestamp())->endOfDay();
            $buckets[] = [
                'label' => (string) $j->format('F'),
                'period' => Jalali::enToFaNumbers((string) $j->format('F Y')),
                'start' => $start,
                'end' => $end,
            ];
        }

        return $buckets;
    }

    private function formatCount(int $n): string
    {
        return Jalali::enToFaNumbers(number_format($n, 0, '.', ','));
    }

    private function formatToman(int $amount): string
    {
        return $this->formatTomanPlain($amount).' تومان';
    }

    private function formatTomanPlain(int $amount): string
    {
        return Jalali::enToFaNumbers(number_format($amount, 0, '.', ','));
    }
}
