<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;

/**
 * ماندهٔ قابل ثبت به‌صورت مجموع پرداخت‌های اقساط (بعد از تخفیف)، مثل سقف ثبت پرداخت در پنل ادمین.
 */
final class LoanRemainingPayableOnFileToman
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $finance,
    ) {}

    public function value(CustomerLoanFile $file): int
    {
        $file->loadMissing(['loanType', 'installments']);
        $totalRepayable = $this->finance->totalRepayableToman($file);
        $totalPaid = (int) $file->installments->sum(static function ($i): int {
            return (int) $i->paid_amount_toman;
        });
        $discount = (int) ($file->discount_amount_toman ?? 0);

        return max(0, $totalRepayable - $discount - $totalPaid);
    }
}
