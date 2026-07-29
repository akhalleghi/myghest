<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\LoanType;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ایجاد و همگام‌سازی ردیف‌های قسط در دیتابیس (همان منطق پنل ادمین).
 */
final class LoanInstallmentScheduleService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $calculator,
        private readonly LoanInstallmentAmountAllocator $allocator,
    ) {}

    public function ensureSchedule(CustomerLoanFile $file, ?int $equalInstallmentAmountToman = null): void
    {
        if ($file->revoked_at !== null) {
            return;
        }

        if ($file->installments()->exists()) {
            return;
        }

        $this->createScheduleRows($file, $equalInstallmentAmountToman);
    }

    /**
     * پس از ویرایش پروندهٔ وام، اقساط موجود را با مقادیر و تاریخ‌های جدید همگام می‌کند.
     */
    public function syncScheduleFromLoanFile(CustomerLoanFile $file, ?int $equalInstallmentAmountToman = null): void
    {
        if ($file->revoked_at !== null) {
            return;
        }

        $installmentsCount = (int) $file->installments_count;
        if ($installmentsCount < 1) {
            $hasPaidInstallment = $file->installments()
                ->where('paid_amount_toman', '>', 0)
                ->exists();
            if ($hasPaidInstallment) {
                throw ValidationException::withMessages([
                    'installments_count' => 'کاهش تعداد اقساط به صفر ممکن نیست؛ برخی اقساط پرداخت ثبت‌شده دارند.',
                ]);
            }

            $file->installments()->delete();

            return;
        }

        $schedule = $this->buildScheduleData($file, $equalInstallmentAmountToman);
        if ($schedule === null) {
            return;
        }

        DB::transaction(function () use ($file, $installmentsCount, $schedule): void {
            $existing = $file->installments()
                ->orderBy('sequence')
                ->get()
                ->keyBy(static fn (CustomerLoanInstallment $installment): int => (int) $installment->sequence);

            $totalPaid = (int) $existing->sum(static fn (CustomerLoanInstallment $installment): int => (int) $installment->paid_amount_toman);
            $payable = (int) $schedule['payable_toman'];
            if ($totalPaid > $payable) {
                throw ValidationException::withMessages([
                    'amount_toman' => 'مجموع پرداخت‌های ثبت‌شده از مبلغ قابل بازپرداخت جدید بیشتر است.',
                ]);
            }

            if ($totalPaid === 0 && $existing->isNotEmpty()) {
                $file->installments()->delete();
                $this->insertScheduleRows(
                    $file,
                    $installmentsCount,
                    $schedule['amounts'],
                    $schedule['start'],
                    $schedule['interval_count'],
                    $schedule['unit'],
                );

                return;
            }

            if ($existing->isEmpty()) {
                $this->insertScheduleRows(
                    $file,
                    $installmentsCount,
                    $schedule['amounts'],
                    $schedule['start'],
                    $schedule['interval_count'],
                    $schedule['unit'],
                );

                return;
            }

            for ($sequence = 1; $sequence <= $installmentsCount; $sequence++) {
                $newAmount = (int) ($schedule['amounts'][$sequence - 1] ?? 0);
                $dueDate = $this->dueDateForInstallment(
                    $schedule['start'],
                    $sequence,
                    $schedule['interval_count'],
                    $schedule['unit'],
                )->toDateString();

                $installment = $existing->get($sequence);
                if ($installment !== null) {
                    $paidAmount = (int) $installment->paid_amount_toman;
                    if ($newAmount < $paidAmount) {
                        throw ValidationException::withMessages([
                            'installment_amount_toman' => 'مبلغ قسط شماره '.(string) $sequence.' نمی‌تواند کمتر از مبلغ پرداخت‌شده باشد.',
                        ]);
                    }

                    $installment->update([
                        'amount_toman' => $newAmount,
                        'due_date' => $dueDate,
                    ]);

                    continue;
                }

                CustomerLoanInstallment::query()->create([
                    'customer_loan_file_id' => $file->id,
                    'sequence' => $sequence,
                    'amount_toman' => $newAmount,
                    'due_date' => $dueDate,
                    'paid_amount_toman' => 0,
                    'paid_at' => null,
                    'recorded_by_admin_id' => null,
                ]);
            }

            foreach ($existing as $sequence => $installment) {
                if ((int) $sequence <= $installmentsCount) {
                    continue;
                }

                if ((int) $installment->paid_amount_toman > 0) {
                    throw ValidationException::withMessages([
                        'installments_count' => 'کاهش تعداد اقساط ممکن نیست؛ برخی اقساط حذفی پرداخت ثبت‌شده دارند.',
                    ]);
                }

                $installment->delete();
            }
        });
    }

    private function createScheduleRows(CustomerLoanFile $file, ?int $equalInstallmentAmountToman = null): void
    {
        $installmentsCount = (int) $file->installments_count;
        if ($installmentsCount < 1) {
            return;
        }

        $schedule = $this->buildScheduleData($file, $equalInstallmentAmountToman);
        if ($schedule === null) {
            return;
        }

        DB::transaction(function () use ($file, $installmentsCount, $schedule): void {
            $this->insertScheduleRows(
                $file,
                $installmentsCount,
                $schedule['amounts'],
                $schedule['start'],
                $schedule['interval_count'],
                $schedule['unit'],
            );
        });
    }

    /**
     * @return array{
     *     payable_toman: int,
     *     amounts: list<int>,
     *     start: Carbon,
     *     interval_count: int,
     *     unit: string
     * }|null
     */
    private function buildScheduleData(CustomerLoanFile $file, ?int $equalInstallmentAmountToman = null): ?array
    {
        $installmentsCount = (int) $file->installments_count;
        if ($installmentsCount < 1 || $file->loan_start_date === null) {
            return null;
        }

        if ($equalInstallmentAmountToman !== null && $equalInstallmentAmountToman > 0) {
            $amounts = array_fill(0, $installmentsCount, $equalInstallmentAmountToman);

            return [
                'payable_toman' => $equalInstallmentAmountToman * $installmentsCount,
                'amounts' => $amounts,
                'start' => Carbon::parse($file->loan_start_date)->startOfDay(),
                'interval_count' => max(1, (int) $file->installment_interval_count),
                'unit' => (string) $file->installment_interval_unit,
            ];
        }

        $payable = $this->calculator->totalRepayableToman($file);
        $allocation = $this->allocator->allocate($payable, $installmentsCount);
        $amounts = $allocation['amounts_toman'];
        if ($amounts === []) {
            return null;
        }

        return [
            'payable_toman' => $payable,
            'amounts' => $amounts,
            'start' => Carbon::parse($file->loan_start_date)->startOfDay(),
            'interval_count' => max(1, (int) $file->installment_interval_count),
            'unit' => (string) $file->installment_interval_unit,
        ];
    }

    /**
     * @param  list<int>  $amounts
     */
    private function insertScheduleRows(
        CustomerLoanFile $file,
        int $installmentsCount,
        array $amounts,
        Carbon $start,
        int $intervalCount,
        string $unit,
    ): void {
        for ($sequence = 1; $sequence <= $installmentsCount; $sequence++) {
            CustomerLoanInstallment::query()->create([
                'customer_loan_file_id' => $file->id,
                'sequence' => $sequence,
                'amount_toman' => (int) ($amounts[$sequence - 1] ?? 0),
                'due_date' => $this->dueDateForInstallment($start, $sequence, $intervalCount, $unit)->toDateString(),
                'paid_amount_toman' => 0,
                'paid_at' => null,
                'recorded_by_admin_id' => null,
            ]);
        }
    }

    /**
     * تاریخ سررسید قسط Nاُم بر اساس تاریخ شروع وام.
     * برای فاصلهٔ ماهانه از تقویم شمسی استفاده می‌شود تا روز ماه (مثلاً ۳) در همهٔ ماه‌ها ثابت بماند.
     */
    private function dueDateForInstallment(Carbon $start, int $sequence, int $intervalCount, string $unit): Carbon
    {
        if ($unit === LoanType::GAP_WEEKLY) {
            return $start->copy()->addWeeks($sequence * $intervalCount)->startOfDay();
        }

        $jalaliDue = Jalali::instance($start->copy())
            ->startDay()
            ->addMonths($sequence * $intervalCount);

        return Carbon::createFromTimestamp(
            $jalaliDue->getTimestamp(),
            $start->getTimezone()
        )->startOfDay();
    }
}
