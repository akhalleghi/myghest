<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Loans\LoanInstallmentScheduleService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\DB;

/**
 * پیش‌نمایش و ثبت پرداخت دسته‌ای اقساط معوق همهٔ پرونده‌های فعال یک مشتری (پنل ادمین).
 * فقط ماندهٔ پرداخت‌نشدهٔ اقساط سررسیدگذشته ثبت می‌شود؛ پرونده به‌صورت خودکار تسویهٔ کامل نمی‌شود.
 */
final class AdminCustomerSettleAllOverdueService
{
    public function __construct(
        private readonly LoanInstallmentScheduleService $schedule,
        private readonly LoanFileFinanceCalculator $finance,
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Customer $customer): array
    {
        $quote = $this->buildQuote($customer);
        if ($quote === null) {
            return [
                'settleable' => false,
                'files' => [],
                'files_count' => 0,
                'installments_count' => 0,
                'principal_toman' => 0,
                'late_fee_toman' => 0,
                'amount_toman' => 0,
                'principal_fa' => $this->formatMoneyFa(0),
                'late_fee_fa' => $this->formatMoneyFa(0),
                'amount_fa' => $this->formatMoneyFa(0),
                'installments_count_fa' => Jalali::enToFaNumbers('0'),
            ];
        }

        return array_merge(['settleable' => true], $quote);
    }

    /**
     * @return array{ok: bool, message: string, paid_installments_count?: int, total_principal_toman?: int, files_count?: int}
     */
    public function settle(
        Customer $customer,
        string $paymentMethod,
        Carbon $depositedAt,
        ?string $note,
        int $adminId,
    ): array {
        if (! in_array($paymentMethod, CustomerLoanInstallmentPayment::creatablePaymentMethodKeys(), true)) {
            return ['ok' => false, 'message' => 'نحوهٔ پرداخت انتخاب‌شده مجاز نیست.'];
        }

        $noteTrim = $note !== null ? trim($note) : '';
        $depositedDate = $depositedAt->startOfDay()->format('Y-m-d');

        try {
            return DB::transaction(function () use ($customer, $paymentMethod, $depositedDate, $noteTrim, $adminId): array {
                $quote = $this->buildQuote($customer);
                if ($quote === null) {
                    return ['ok' => false, 'message' => 'قسط معوق قابل پرداختی یافت نشد.'];
                }

                $paidInstallments = 0;
                $totalPrincipal = 0;
                $filesTouched = 0;

                foreach ($quote['files'] as $fileItem) {
                    $fileId = (int) ($fileItem['loan_file_id'] ?? 0);
                    $expectedFilePrincipal = (int) ($fileItem['amount_toman'] ?? 0);
                    $expectedInstallments = is_array($fileItem['installments'] ?? null) ? $fileItem['installments'] : [];

                    $file = CustomerLoanFile::query()
                        ->where('customer_id', (int) $customer->id)
                        ->whereKey($fileId)
                        ->lockForUpdate()
                        ->first();

                    if ($file === null || $file->revoked_at !== null || $file->is_settled) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $this->schedule->ensureSchedule($file);
                    $file->unsetRelation('installments');
                    $file->load([
                        'loanType',
                        'installments' => static function ($q): void {
                            $q->orderBy('sequence');
                        },
                    ]);

                    $liveFileQuote = $this->quoteForLoanFile($file);
                    if ($liveFileQuote === null
                        || (int) $liveFileQuote['amount_toman'] !== $expectedFilePrincipal
                        || count($liveFileQuote['installments']) !== count($expectedInstallments)
                    ) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $ceiling = $this->finance->installmentPaymentCeilingToman($file);
                    if ($expectedFilePrincipal > $ceiling) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $noteBase = $noteTrim !== ''
                        ? $noteTrim
                        : 'ثبت پرداخت اقساط معوق (دسته‌ای)';

                    $filePaid = 0;
                    foreach ($expectedInstallments as $instItem) {
                        $installmentId = (int) ($instItem['installment_id'] ?? 0);
                        $expectedUnpaid = (int) ($instItem['unpaid_toman'] ?? 0);
                        if ($installmentId < 1 || $expectedUnpaid < 1) {
                            throw new \RuntimeException('QUOTE_MISMATCH');
                        }

                        $installment = $file->installments->first(
                            static fn (CustomerLoanInstallment $i): bool => (int) $i->id === $installmentId
                        );
                        if ($installment === null) {
                            throw new \RuntimeException('QUOTE_MISMATCH');
                        }

                        $liveUnpaid = (int) (collect($liveFileQuote['installments'])->firstWhere('installment_id', $installmentId)['unpaid_toman'] ?? 0);
                        $due = Carbon::parse($installment->due_date)->startOfDay();
                        if ($liveUnpaid !== $expectedUnpaid || ! $due->lt(Carbon::today())) {
                            throw new \RuntimeException('QUOTE_MISMATCH');
                        }

                        CustomerLoanInstallmentPayment::query()->create([
                            'customer_loan_installment_id' => (int) $installment->id,
                            'payment_method' => $paymentMethod,
                            'amount_toman' => $expectedUnpaid,
                            'reference_due_date' => $due->format('Y-m-d'),
                            'deposited_at' => $depositedDate,
                            'note' => $noteBase,
                            'recorded_by_admin_id' => $adminId,
                        ]);

                        $installment->refresh();
                        $this->syncer->syncFromPaymentRows($installment);
                        $filePaid += $expectedUnpaid;
                        $paidInstallments++;
                    }

                    if ($filePaid !== $expectedFilePrincipal) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $totalPrincipal += $filePaid;
                    $filesTouched++;
                }

                return [
                    'ok' => true,
                    'message' => 'پرداخت '.$paidInstallments.' قسط معوق در '.$filesTouched.' پرونده با موفقیت ثبت شد.',
                    'paid_installments_count' => $paidInstallments,
                    'total_principal_toman' => $totalPrincipal,
                    'files_count' => $filesTouched,
                ];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'QUOTE_MISMATCH') {
                return ['ok' => false, 'message' => 'وضعیت اقساط معوق هنگام ثبت تغییر کرد؛ لطفاً پیش‌نمایش را دوباره بگیرید.'];
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'خطای غیرمنتظره هنگام ثبت پرداخت معوق؛ لطفاً دوباره تلاش کنید.'];
        }
    }

    /**
     * @return array{
     *     files: list<array<string, mixed>>,
     *     files_count: int,
     *     installments_count: int,
     *     principal_toman: int,
     *     late_fee_toman: int,
     *     amount_toman: int,
     *     principal_fa: string,
     *     late_fee_fa: string,
     *     amount_fa: string,
     *     installments_count_fa: string
     * }|null
     */
    private function buildQuote(Customer $customer): ?array
    {
        $files = CustomerLoanFile::query()
            ->where('customer_id', (int) $customer->id)
            ->whereNull('revoked_at')
            ->where('is_settled', false)
            ->orderBy('id')
            ->get();

        $items = [];
        $principalTotal = 0;
        $lateFeeTotal = 0;
        $installmentsCount = 0;

        foreach ($files as $file) {
            $this->schedule->ensureSchedule($file);
            $file->unsetRelation('installments');
            $file->load([
                'loanType',
                'installments' => static function ($q): void {
                    $q->orderBy('sequence');
                },
            ]);

            $fileQuote = $this->quoteForLoanFile($file);
            if ($fileQuote === null) {
                continue;
            }

            $items[] = $fileQuote;
            $principalTotal += (int) $fileQuote['principal_toman'];
            $lateFeeTotal += (int) $fileQuote['late_fee_toman'];
            $installmentsCount += count($fileQuote['installments']);
        }

        if ($items === [] || $principalTotal + $lateFeeTotal < 1) {
            return null;
        }

        return [
            'files' => $items,
            'files_count' => count($items),
            'installments_count' => $installmentsCount,
            'principal_toman' => $principalTotal,
            'late_fee_toman' => $lateFeeTotal,
            /** مبلغ قابل ثبت شامل اصل اقساط معوق و دیرکرد پرداخت‌نشده است. */
            'amount_toman' => $principalTotal + $lateFeeTotal,
            'principal_fa' => $this->formatMoneyFa($principalTotal),
            'late_fee_fa' => $this->formatMoneyFa($lateFeeTotal),
            'amount_fa' => $this->formatMoneyFa($principalTotal + $lateFeeTotal),
            'installments_count_fa' => Jalali::enToFaNumbers((string) $installmentsCount),
        ];
    }

    /**
     * @return array{
     *     loan_file_id: int,
     *     loan_code: string,
     *     loan_type_title: string,
     *     principal_toman: int,
     *     late_fee_toman: int,
     *     amount_toman: int,
     *     principal_fa: string,
     *     late_fee_fa: string,
     *     amount_fa: string,
     *     installments: list<array<string, mixed>>
     * }|null
     */
    private function quoteForLoanFile(CustomerLoanFile $file): ?array
    {
        if ($file->revoked_at !== null || $file->is_settled) {
            return null;
        }
        $snapshot = $this->finance->loanInstallmentFinancialSnapshot($file, $this->finance->totalRepayableToman($file));
        if ($snapshot['requires_schedule_review']) {
            return null;
        }
        $installments = [];
        $principal = 0;
        $late = 0;
        foreach ($file->installments->sortBy('sequence') as $inst) {
            $due = Carbon::parse($inst->due_date)->startOfDay();
            $payable = $snapshot['installment_payables'][(int) $inst->id];
            if (! $due->lt(Carbon::today()) || $payable['remaining_toman'] <= 0) {
                continue;
            }
            $principal += $payable['principal_remaining_toman'];
            $late += $payable['late_fee_remaining_toman'];
            $installments[] = [
                'installment_id' => (int) $inst->id,
                'sequence' => (int) $inst->sequence,
                'sequence_fa' => Jalali::enToFaNumbers((string) $inst->sequence),
                'due_date' => $due->toDateString(),
                'due_jdate_fa' => Jalali::enToFaNumbers(Jalali::instance($due)->format('Y/m/d')),
                'amount_toman' => (int) $inst->amount_toman,
                'paid_amount_toman' => (int) $inst->paid_amount_toman,
                'unpaid_toman' => $payable['remaining_toman'],
                'unpaid_fa' => $this->formatMoneyFa($payable['remaining_toman']),
            ];
        }
        if ($installments === []) {
            return null;
        }

        return [
            'loan_file_id' => (int) $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'principal_toman' => $principal,
            'late_fee_toman' => $late,
            'amount_toman' => $principal + $late,
            'principal_fa' => $this->formatMoneyFa($principal),
            'late_fee_fa' => $this->formatMoneyFa($late),
            'amount_fa' => $this->formatMoneyFa($principal + $late),
            'installments' => $installments,
        ];
    }

    private function formatMoneyFa(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $toman), 0, '.', ',')).' تومان';
    }
}
