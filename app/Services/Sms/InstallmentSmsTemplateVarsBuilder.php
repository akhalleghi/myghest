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

    /** @return array<string, string> */
    public function buildOverdueTotal(Customer $customer, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $total = 0;
        $count = 0;
        $files = $customer->loanFiles()->whereNull('revoked_at')->where('is_settled', false)
            ->with(['loanType', 'installments.payments'])->get();
        foreach ($files as $file) {
            $snapshot = $this->financeCalculator->loanInstallmentFinancialSnapshot($file, $this->financeCalculator->totalRepayableToman($file), $today);
            foreach ($file->installments as $installment) {
                $remaining = (int) ($snapshot['installment_payables'][$installment->id]['remaining_toman'] ?? 0);
                if ($installment->due_date->lt($today) && $remaining > 0) {
                    $total += $remaining;
                    $count++;
                }
            }
        }
        $amount = number_format(intdiv($total + 1999, 2000) * 2000, 0, '.', ',').' تومان';

        return [
            'store_name' => $this->appDisplayName(), 'customer_name' => $customer->fullName(),
            'installment_amount' => $amount, 'installment_unpaid_amount' => $amount,
            'overdue_total_toman' => (string) $total,
            'overdue_installment_count' => (string) $count, 'loan_code' => '—',
            'installment_number' => $count > 0 ? (string) $count : '—', 'days_until_due' => '0',
            'paid_amount' => '0 تومان', 'remaining_loan' => $amount,
            'payment_link' => '—', 'payment_link_variable' => '—',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function build(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
        ?Carbon $referenceDate = null,
        ?string $smsType = null,
    ): array {
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
            : (int) $snapshot['remaining_amount_toman'];

        $paid = (int) $installment->paid_amount_toman;
        $amount = (int) $installment->amount_toman;
        $unpaidOnInstallment = (int) ($snapshot['installment_payables'][(int) $installment->id]['remaining_toman'] ?? 0);
        // پیامک قسط معوق: مبلغ قالب = باقیمانده پرداخت‌نشده (نه مبلغ کامل قسط)
        $useUnpaidAmount = $smsType === 'installment_overdue' || $due->lt($referenceDate);
        $amountForTemplate = $useUnpaidAmount ? $unpaidOnInstallment : $snapshot['installment_balances'][(int) $installment->id]['final_amount_toman'];

        return [
            ...CustomerSmsCredentialVariables::for($customer),
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'installment_number' => (string) $installment->sequence,
            'installment_amount' => $this->formatToman($amountForTemplate),
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
