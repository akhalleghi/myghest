<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InstallmentLateFeeWaiverService
{
    public function __construct(private readonly LoanFileFinanceCalculator $finance) {}

    public function preview(CustomerLoanFile $file, CustomerLoanInstallment $installment): array
    {
        $snapshot = $this->finance->loanInstallmentFinancialSnapshot($file, $this->finance->totalRepayableToman($file));
        $balance = $snapshot['installment_balances'][$installment->id];
        $payable = $snapshot['installment_payables'][$installment->id];
        $allowed = ! $file->is_settled && $file->revoked_at === null
            && $installment->due_date->lt(today()) && $balance['total_paid_toman'] > 0
            && $balance['principal_remaining_toman'] === 0 && $payable['late_fee_remaining_toman'] > 0;

        return [
            'allowed' => $allowed,
            'remaining_late_fee_toman' => $payable['late_fee_remaining_toman'],
            'waived_toman' => (int) $installment->late_fee_waived_toman,
            'installment_remaining_toman' => $payable['remaining_toman'],
            'loan_remaining_toman' => $snapshot['remaining_amount_toman'],
            'version' => hash('sha256', json_encode([$file->id, $installment->id,
                $installment->late_fee_waived_toman, $balance, $payable, $snapshot['remaining_amount_toman'],
                $file->is_settled, $file->revoked_at], JSON_THROW_ON_ERROR)),
        ];
    }

    public function reduce(int $customerId, int $fileId, int $installmentId, int $remaining, string $version, string $reason, int $adminId): array
    {
        return DB::transaction(function () use ($customerId, $fileId, $installmentId, $remaining, $version, $reason, $adminId): array {
            $file = CustomerLoanFile::where('customer_id', $customerId)->whereKey($fileId)->lockForUpdate()->firstOrFail();
            $installment = $file->installments()->whereKey($installmentId)->lockForUpdate()->firstOrFail();
            $before = $this->preview($file, $installment);
            if (! hash_equals($before['version'], $version)) {
                throw ValidationException::withMessages(['version' => 'مبالغ تغییر کرده‌اند. پنجره را ببندید و دوباره باز کنید.']);
            }
            if (! $before['allowed']) {
                throw ValidationException::withMessages(['remaining_toman' => 'بخشودگی فقط برای قسطی مجاز است که اصل آن پرداخت شده و مانده دیرکرد دارد.']);
            }
            if ($remaining < 0 || $remaining >= $before['remaining_late_fee_toman']) {
                throw ValidationException::withMessages(['remaining_toman' => 'مبلغ جدید باید نامنفی و کمتر از جریمه پرداخت‌نشده فعلی باشد.']);
            }
            $installment->late_fee_waived_toman = $before['waived_toman'] + $before['remaining_late_fee_toman'] - $remaining;
            $installment->late_fee_waived_at = now();
            $installment->save();
            $file->unsetRelation('installments');
            $after = $this->preview($file, $installment);
            DB::table('installment_late_fee_waivers')->insert([
                'installment_id' => $installmentId, 'admin_id' => $adminId,
                'old_waived_toman' => $before['waived_toman'], 'new_waived_toman' => $after['waived_toman'],
                'old_remaining_toman' => $before['remaining_late_fee_toman'], 'new_remaining_toman' => $after['remaining_late_fee_toman'],
                'reason' => $reason, 'created_at' => now(),
            ]);

            return $after;
        }, 3);
    }
}
