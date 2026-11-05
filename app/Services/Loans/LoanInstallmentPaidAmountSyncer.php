<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Wallet\InstallmentWalletRewardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * هم‌سو با منطق پنل ادمین: جمع ردیف‌های پرداخت → paid_amount_toman و paid_at روی قسط.
 */
final class LoanInstallmentPaidAmountSyncer
{
    public function syncFromPaymentRows(CustomerLoanInstallment $installment, bool $allowReward = true): void
    {
        DB::transaction(function () use ($installment, $allowReward): void {
            $locked = CustomerLoanInstallment::query()->whereKey($installment->id)->lockForUpdate()->firstOrFail();
            $this->sync($locked);
            app(InstallmentWalletRewardService::class)->reconcile($locked, $allowReward);
            $installment->refresh();
        });
    }

    private function sync(CustomerLoanInstallment $installment): void
    {
        $sum = (int) CustomerLoanInstallmentPayment::query()
            ->where('customer_loan_installment_id', (int) $installment->id)
            ->sum('amount_toman');
        $installment->paid_amount_toman = $sum;
        $maxDep = CustomerLoanInstallmentPayment::query()
            ->where('customer_loan_installment_id', (int) $installment->id)
            ->max('deposited_at');
        if ($sum > 0 && $maxDep !== null) {
            $installment->paid_at = Carbon::parse((string) $maxDep)->startOfDay()->format('Y-m-d');
        } else {
            $installment->paid_at = null;
        }

        $installment->save();
    }
}
