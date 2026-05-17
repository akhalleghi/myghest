<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

final class InstallmentSmsTemplateVarsBuilder
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $financeCalculator,
    ) {}

    /**
     * @return array<string, string>
     */
    public function build(Customer $customer, CustomerLoanFile $loanFile, CustomerLoanInstallment $installment, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= Carbon::now()->startOfDay();
        $due = Carbon::parse($installment->due_date)->startOfDay();
        $daysUntil = $due->gt($referenceDate)
            ? (string) (int) $referenceDate->diffInDays($due)
            : '0';
        $daysOverdue = $due->lt($referenceDate)
            ? (string) (int) $due->diffInDays($referenceDate)
            : '0';

        $loanFile->loadMissing(['loanType', 'installments']);
        $totalRepayable = $this->financeCalculator->totalRepayableToman($loanFile);
        $snapshot = $this->financeCalculator->loanInstallmentFinancialSnapshot($loanFile, $totalRepayable);
        $discount = (int) ($loanFile->discount_amount_toman ?? 0);
        $remaining = $loanFile->is_settled
            ? 0
            : max(0, (int) $snapshot['schedule_remaining_toman'] - $discount);

        $paid = (int) $installment->paid_amount_toman;
        $amount = (int) $installment->amount_toman;
        $unpaidOnInstallment = max(0, $amount - $paid);

        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'installment_number' => (string) $installment->sequence,
            'installment_amount' => $this->formatToman($amount),
            'installment_unpaid_amount' => $this->formatToman($unpaidOnInstallment),
            'paid_amount' => $this->formatToman($paid),
            'remaining_loan' => $this->formatToman($remaining),
            'days_until_due' => $daysUntil,
            'days_overdue' => $daysOverdue,
            'due_date' => Jalali::enToFaNumbers(Jalali::instance($due)->format('Y/m/d')),
        ];
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && trim($v) !== '' ? trim($v) : (string) config('app.name');
    }

    private function formatToman(int $amount): string
    {
        return number_format(max(0, $amount), 0, '.', ',').' تومان';
    }
}
