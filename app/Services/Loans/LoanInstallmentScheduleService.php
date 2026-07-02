<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\LoanType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ایجاد ردیف‌های قسط در دیتابیس در صورت نبود (همان منطق پنل ادمین).
 */
final class LoanInstallmentScheduleService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $calculator,
        private readonly LoanInstallmentAmountAllocator $allocator,
    ) {}

    public function ensureSchedule(CustomerLoanFile $file): void
    {
        if ($file->revoked_at !== null) {
            return;
        }

        if ($file->installments()->exists()) {
            return;
        }

        $n = (int) $file->installments_count;
        if ($n < 1) {
            return;
        }

        $payable = $this->calculator->totalRepayableToman($file);
        $allocation = $this->allocator->allocate($payable, $n);
        $amounts = $allocation['amounts_toman'];
        if ($amounts === []) {
            return;
        }

        $start = Carbon::parse($file->loan_start_date)->startOfDay();
        $intervalCount = max(1, (int) $file->installment_interval_count);
        $unit = (string) $file->installment_interval_unit;

        DB::transaction(function () use ($file, $n, $start, $intervalCount, $unit, $amounts): void {
            for ($i = 1; $i <= $n; $i++) {
                $due = $start->copy();
                if ($unit === LoanType::GAP_WEEKLY) {
                    $due->addWeeks($i * $intervalCount);
                } else {
                    $due->addMonths($i * $intervalCount);
                }
                CustomerLoanInstallment::query()->create([
                    'customer_loan_file_id' => $file->id,
                    'sequence' => $i,
                    'amount_toman' => (int) ($amounts[$i - 1] ?? 0),
                    'due_date' => $due->toDateString(),
                    'paid_amount_toman' => 0,
                    'paid_at' => null,
                    'recorded_by_admin_id' => null,
                ]);
            }
        });
    }
}
