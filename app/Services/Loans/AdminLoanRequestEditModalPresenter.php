<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Customer;
use App\Models\CustomerLoginLog;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\LoanRequestStatusDefinition;
use App\Models\LoanType;
use App\Services\Wallet\CustomerWalletService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

/**
 * payload امن برای مدال «مشخصات درخواست وام» در ادمین.
 */
final class AdminLoanRequestEditModalPresenter
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $calculator,
        private readonly CustomerWalletService $walletService,
        private readonly LoanRequestStatusPresentation $statusPresentation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CustomerLoanRequest $request, array $statusTitleByCode): array
    {
        $request->loadMissing(['customer', 'loanType', 'documents']);

        $customer = $request->customer;
        if ($customer === null) {
            abort(404);
        }

        $submitted = $request->submitted_at ?? $request->created_at;
        $submitted = $submitted instanceof Carbon ? $submitted : Carbon::parse((string) $submitted);

        $profit = $this->calculator->calculateLoanProfitToman(
            (int) $request->amount_toman,
            (float) $request->interest_rate,
            (string) $request->profit_calculation_method,
            (int) $request->installments_count,
            (int) $request->installment_interval_count,
            (string) $request->installment_interval_unit,
        );
        $totalRepayable = max(0, (int) $request->amount_toman + $profit);
        $n = max(1, (int) $request->installments_count);
        $perInstallment = (int) max(1, (int) round($totalRepayable / $n));

        $wallet = $this->walletService->ensureWallet($customer);
        $loanAgg = $this->customerLoanAggregates($customer);

        $statusCode = (string) $request->status;
        $chip = $this->statusPresentation->adminBadge($statusCode, $statusTitleByCode);

        $membershipFa = '—';
        if ($customer->membership_at !== null) {
            $membershipFa = Jalali::enToFaNumbers(
                Jalali::instance(Carbon::parse($customer->membership_at)->startOfDay())->format('Y/m/d')
            ).' ۰۰:۰۰:۰۰';
        }

        $lastLoginFa = '—';
        $lastLog = CustomerLoginLog::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('logged_in_at')
            ->orderByDesc('id')
            ->first();
        if ($lastLog !== null && $lastLog->logged_in_at !== null) {
            $ln = $lastLog->logged_in_at instanceof Carbon ? $lastLog->logged_in_at : Carbon::parse((string) $lastLog->logged_in_at);
            $lastLoginFa = Jalali::enToFaNumbers(Jalali::instance($ln)->format('Y/m/d'))
                .' '.Jalali::enToFaNumbers($ln->format('H:i:s'));
        }

        $loanTypes = LoanType::query()
            ->orderBy('title')
            ->get()
            ->map(static function (LoanType $lt): array {
                $label = trim((string) ($lt->plan_title ?? '')) !== ''
                    ? (string) $lt->plan_title
                    : (string) $lt->title;

                return [
                    'id' => (int) $lt->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();

        $statusOptions = LoanRequestStatusDefinition::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['code', 'title'])
            ->map(static fn (LoanRequestStatusDefinition $d): array => [
                'code' => $d->code,
                'title' => $d->title,
            ])
            ->values()
            ->all();

        return [
            'request' => [
                'id' => (int) $request->id,
                'loan_type_id' => (int) $request->loan_type_id,
                'status' => $statusCode,
                'status_label' => $chip['label'],
                'amount_toman' => (int) $request->amount_toman,
                'installments_count' => (int) $request->installments_count,
                'installment_interval_count' => (int) $request->installment_interval_count,
                'installment_interval_unit' => (string) $request->installment_interval_unit,
                'installment_interval_unit_fa' => $request->installment_interval_unit === LoanType::GAP_WEEKLY ? 'هفته' : 'ماه',
                'installment_amount_toman' => $perInstallment,
                'submitted_at_iso' => $submitted->toIso8601String(),
                'submitted_date_fa' => Jalali::enToFaNumbers(Jalali::instance($submitted)->format('Y/m/d')),
                'submitted_time_fa' => Jalali::enToFaNumbers($submitted->format('H:i:s')),
                'expert_note' => (string) ($request->expert_note ?? ''),
                'expert_note_customer' => (string) ($request->expert_note_customer ?? ''),
                'description' => (string) ($request->description ?? ''),
                'documents_physical_received' => (bool) $request->documents_physical_received,
            ],
            'customer' => [
                'id' => (int) $customer->id,
                'full_name' => $customer->fullName(),
                'username' => (string) $customer->username,
                'national_id_fa' => Jalali::enToFaNumbers((string) $customer->national_id),
                'mobile_fa' => Jalali::enToFaNumbers((string) $customer->mobile),
                'father_name_fa' => Jalali::enToFaNumbers((string) $customer->father_name),
                'loan_count' => $loanAgg['loan_count'],
                'loans_total_fa' => Jalali::enToFaNumbers(number_format($loanAgg['loan_total_with_profit'], 0, '.', ',')),
                'installments_remaining_fa' => Jalali::enToFaNumbers(number_format($loanAgg['remaining_installments'], 0, '.', ',')),
                'membership_at_fa' => $membershipFa,
                'last_login_fa' => $lastLoginFa,
                'wallet_balance_fa' => Jalali::enToFaNumbers(number_format((int) $wallet->balance_toman, 0, '.', ',')),
                'good_standing_label' => 'نامشخص',
            ],
            'loan_types' => $loanTypes,
            'status_options' => $statusOptions,
            'documents' => $request->documents
                ->map(function (CustomerLoanRequestDocument $d) use ($request): array {
                    $isPdf = str_contains((string) $d->mime_type, 'pdf');
                    $isImage = str_starts_with((string) $d->mime_type, 'image/');

                    return [
                        'id' => (int) $d->id,
                        'preset_key' => (string) $d->preset_key,
                        'document_title' => (string) $d->document_title,
                        'description' => $d->description !== null ? (string) $d->description : null,
                        'expert_note' => $d->expert_note !== null ? (string) $d->expert_note : null,
                        'review_status' => (string) $d->review_status,
                        'review_status_label' => CustomerLoanRequestDocument::reviewStatusLabels()[$d->review_status] ?? $d->review_status,
                        'mime_type' => (string) $d->mime_type,
                        'is_image' => $isImage,
                        'is_pdf' => $isPdf,
                        'file_url' => route('admin.loan-requests.documents.file', [
                            'customerLoanRequest' => $request->id,
                            'customerLoanRequestDocument' => $d->id,
                        ]),
                    ];
                })
                ->values()
                ->all(),
            'document_review_statuses' => collect(CustomerLoanRequestDocument::reviewStatusLabels())
                ->map(static fn (string $label, string $code): array => ['code' => $code, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{loan_count: int, loan_total_with_profit: int, remaining_installments: int}
     */
    private function customerLoanAggregates(Customer $customer): array
    {
        $customer->load(['loanFiles.loanType', 'loanFiles.installments']);

        $files = $customer->loanFiles;
        $loanCount = $files->count();
        $totalWithProfit = 0;
        $remaining = 0;

        foreach ($files as $file) {
            if (! $file instanceof CustomerLoanFile) {
                continue;
            }
            if ($file->revoked_at !== null) {
                continue;
            }
            $profit = $this->calculator->calculateLoanProfitToman(
                (int) $file->amount_toman,
                (float) $file->effective_interest_rate,
                (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
                (int) $file->installments_count,
                (int) $file->installment_interval_count,
                (string) $file->installment_interval_unit,
            );
            $totalRepayable = max(0, ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman);
            $totalWithProfit += $totalRepayable;

            $discount = (int) ($file->discount_amount_toman ?? 0);
            $totalPaid = (int) $file->installments->sum(static fn ($i): int => (int) $i->paid_amount_toman);
            $scheduleRemaining = max(0, $totalRepayable - $totalPaid);
            $remaining += $file->is_settled
                ? 0
                : max(0, $scheduleRemaining - $discount);
        }

        return [
            'loan_count' => $loanCount,
            'loan_total_with_profit' => $totalWithProfit,
            'remaining_installments' => $remaining,
        ];
    }
}
