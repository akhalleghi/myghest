<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerWalletTransaction;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InstallmentWalletRewardService
{
    public function settings(Customer $customer): array
    {
        return ['enabled' => (bool) ($customer->installment_reward_enabled ?? false),
            'percent' => (float) ($customer->installment_reward_percent ?? 3)];
    }

    public function saveSettings(Customer $customer, bool $enabled, float $percent): array
    {
        if (! is_finite($percent) || $percent < 0.01 || $percent > 100) {
            throw ValidationException::withMessages(['percent' => 'درصد پاداش باید بین ۰٫۰۱ و ۱۰۰ باشد.']);
        }
        if (! Schema::hasTable('installment_wallet_rewards') || ! Schema::hasColumn('customers', 'installment_reward_enabled')) {
            throw ValidationException::withMessages(['reward' => 'ابتدا migration پاداش کیف پول اجرا شود.']);
        }

        return DB::transaction(function () use ($customer, $enabled, $percent): array {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($enabled && ! $locked->installment_reward_enabled) {
                $locked->installment_reward_after_payment_id = (int) CustomerLoanInstallmentPayment::query()->max('id');
            }
            $locked->installment_reward_enabled = $enabled;
            $locked->installment_reward_percent = $percent;
            $locked->save();

            return $this->settings($locked);
        });
    }

    /** Nested in the receipt transaction so failure also rolls back its mutation. */
    public function reconcile(CustomerLoanInstallment $installment, bool $allowNew = true, bool $forceReverse = false): void
    {
        DB::transaction(fn () => $this->reconcileLocked($installment, $allowNew, $forceReverse));
    }

    private function reconcileLocked(CustomerLoanInstallment $installment, bool $allowNew, bool $forceReverse): void
    {
        if (! Schema::hasTable('installment_wallet_rewards')) {
            return;
        }
        $file = CustomerLoanFile::query()->whereKey($installment->customer_loan_file_id)->lockForUpdate()->first();
        if ($file === null) {
            return;
        }
        $customer = Customer::query()->whereKey($file->customer_id)->lockForUpdate()->firstOrFail();
        $award = DB::table('installment_wallet_rewards')->where('installment_id', $installment->id)->lockForUpdate()->first();
        if ($award === null && (! $allowNew || ! $customer->installment_reward_enabled || $forceReverse)) {
            return;
        }
        $afterId = (int) ($award->activation_payment_id ?? $customer->installment_reward_after_payment_id);
        if ($award !== null && (int) $award->credited_amount_toman === 0) {
            $afterId = max($afterId, (int) $customer->installment_reward_after_payment_id);
        }
        $receipts = $installment->payments()->get();
        $hasNewReceipt = $receipts->contains(fn ($receipt): bool => (int) $receipt->id > $afterId
            && $receipt->payment_method !== CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED);
        $last = $receipts->max('deposited_at');
        $due = Carbon::parse($installment->due_date)->startOfDay();
        $eligible = ! $forceReverse && $file->revoked_at === null && $hasNewReceipt && $last !== null
            && Carbon::parse($last)->startOfDay()->lte($due)
            && Carbon::parse($last)->startOfDay()->lte(Carbon::today());
        if ($eligible) {
            // Explicit settlement flags must not manufacture reward eligibility.
            $file->is_settled = false;
            $finance = app(LoanFileFinanceCalculator::class);
            $snapshot = $finance->loanInstallmentFinancialSnapshot($file, $finance->totalRepayableToman($file), Carbon::today()->min($due));
            $eligible = ! $snapshot['requires_schedule_review']
                && (int) ($snapshot['installment_payables'][(int) $installment->id]['remaining_toman'] ?? 1) === 0
                && (int) $receipts->sum('amount_toman') > 0;
        }
        $percent = (float) ($award->percent ?? $customer->installment_reward_percent);
        $target = $eligible ? max(0, (int) round((int) $installment->amount_toman * $percent / 100)) : 0;
        $credited = (int) ($award->credited_amount_toman ?? 0);
        if ($credited === 0 && (! $allowNew || ! $customer->installment_reward_enabled)) {
            $target = 0;
        }
        if ($target === $credited) {
            return;
        }
        try {
            app(CustomerWalletService::class)->adjust($customer,
                $target > $credited ? CustomerWalletTransaction::DIRECTION_DEPOSIT : CustomerWalletTransaction::DIRECTION_WITHDRAW,
                abs($target - $credited), ($target > $credited ? 'پاداش پرداخت به‌موقع قسط ' : 'برگشت پاداش قسط ').$installment->sequence.' — '.$file->loan_code,
                null, null, null, (string) Str::uuid(),
                ['channel' => 'system', 'purpose' => 'installment_reward', 'installment_id' => (int) $installment->id,
                    'loan_file_id' => (int) $file->id, 'percent' => $percent], true);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['reward' => 'اصلاح پرداخت انجام نشد؛ برای برگشت پاداش، کیف پول باید باز و دارای موجودی کافی باشد. '.$exception->getMessage()]);
        }
        DB::table('installment_wallet_rewards')->updateOrInsert(['installment_id' => $installment->id], [
            'customer_id' => $customer->id, 'percent' => $percent, 'credited_amount_toman' => $target,
            'activation_payment_id' => $afterId, 'created_at' => $award->created_at ?? now(), 'updated_at' => now(),
        ]);
    }

    public function reverseLoan(CustomerLoanFile $file): void
    {
        foreach ($file->installments()->get() as $installment) {
            $this->reconcile($installment, false, true);
        }
    }

    public function reconcileLoan(CustomerLoanFile $file): void
    {
        foreach ($file->installments()->get() as $installment) {
            $this->reconcile($installment, false);
        }
    }
}
