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
        return $this->finance->installmentPaymentCeilingToman($file);
    }
}
