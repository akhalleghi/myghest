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
                    $expectedFilePrincipal = (int) ($fileItem['principal_toman'] ?? 0);
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
                        || (int) $liveFileQuote['principal_toman'] !== $expectedFilePrincipal
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

                        $liveUnpaid = max(0, (int) $installment->amount_toman - (int) $installment->paid_amount_toman);
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

        if ($items === [] || $principalTotal < 1) {
            return null;
        }

        return [
            'files' => $items,
            'files_count' => count($items),
            'installments_count' => $installmentsCount,
            'principal_toman' => $principalTotal,
            'late_fee_toman' => $lateFeeTotal,
            /** مبلغ قابل ثبت در این عملیات = فقط ماندهٔ اقساط معوق (بدون جریمه). */
            'amount_toman' => $principalTotal,
            'principal_fa' => $this->formatMoneyFa($principalTotal),
            'late_fee_fa' => $this->formatMoneyFa($lateFeeTotal),
            'amount_fa' => $this->formatMoneyFa($principalTotal),
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

        $today = Carbon::today();
        /** @var list<array{inst: CustomerLoanInstallment, unpaid: int, due: Carbon}> $candidates */
        $candidates = [];

        foreach ($file->installments->sortBy('sequence')->values() as $inst) {
            if (! $inst instanceof CustomerLoanInstallment) {
                continue;
            }
            $amount = (int) $inst->amount_toman;
            $paid = (int) $inst->paid_amount_toman;
            if ($amount <= 0 || $paid >= $amount) {
                continue;
            }
            $due = Carbon::parse($inst->due_date)->startOfDay();
            if (! $due->lt($today)) {
                continue;
            }

            $unpaid = max(0, $amount - $paid);
            if ($unpaid < 1) {
                continue;
            }

            $candidates[] = [
                'inst' => $inst,
                'unpaid' => $unpaid,
                'due' => $due,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        /**
         * سقف قابل ثبت واقعی (مانده قرارداد پس از تخفیف و پرداخت‌ها).
         * ممکن است به‌خاطر پرداخت نامتوازن روی یک قسط، سقف صفر باشد ولی اقساط بعدی هنوز نامی unpaid باشند؛
         * در آن حالت مبلغ قابل ثبت برای تسویه معوق صفر است.
         */
        $ceiling = $this->finance->installmentPaymentCeilingToman($file);
        if ($ceiling < 1) {
            return null;
        }

        $pool = $ceiling;
        $installmentsOut = [];
        $principalToman = 0;

        foreach ($candidates as $row) {
            if ($pool < 1) {
                break;
            }
            $inst = $row['inst'];
            $due = $row['due'];
            $pay = min((int) $row['unpaid'], $pool);
            if ($pay < 1) {
                continue;
            }

            $principalToman += $pay;
            $pool -= $pay;
            $installmentsOut[] = [
                'installment_id' => (int) $inst->id,
                'sequence' => (int) $inst->sequence,
                'sequence_fa' => Jalali::enToFaNumbers((string) (int) $inst->sequence),
                'due_date' => $due->format('Y-m-d'),
                'due_jdate_fa' => Jalali::enToFaNumbers(Jalali::instance($due)->format('Y/m/d')),
                'amount_toman' => (int) $inst->amount_toman,
                'paid_amount_toman' => (int) $inst->paid_amount_toman,
                'unpaid_toman' => $pay,
                'unpaid_fa' => $this->formatMoneyFa($pay),
            ];
        }

        if ($installmentsOut === [] || $principalToman < 1) {
            return null;
        }

        $totalRepayable = $this->finance->totalRepayableToman($file);
        $snap = $this->finance->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $lateFeeToman = max(0, (int) ($snap['late_fee_so_far_toman'] ?? 0));

        return [
            'loan_file_id' => (int) $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'principal_toman' => $principalToman,
            'late_fee_toman' => $lateFeeToman,
            'amount_toman' => $principalToman,
            'principal_fa' => $this->formatMoneyFa($principalToman),
            'late_fee_fa' => $this->formatMoneyFa($lateFeeToman),
            'amount_fa' => $this->formatMoneyFa($principalToman),
            'installments' => $installmentsOut,
        ];
    }

    private function formatMoneyFa(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $toman), 0, '.', ',')).' تومان';
    }
}
