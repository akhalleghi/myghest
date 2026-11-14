<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Wallet\CustomerWalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminInstallmentWalletPaymentService
{
    public function __construct(
        private readonly CustomerWalletService $wallets,
        private readonly LoanFileFinanceCalculator $finance,
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
        private readonly CustomerTransactionLedgerService $ledger,
    ) {}

    /** @return array{payment: CustomerLoanInstallmentPayment, replay: bool} */
    public function pay(Customer $customer, int $fileId, int $installmentId, int $amount, Carbon $date, ?Carbon $referenceDate, ?string $note, int $adminId, string $uuid): array
    {
        if (! Str::isUuid($uuid) || $amount < 1) {
            throw ValidationException::withMessages(['request_uuid' => 'درخواست پرداخت معتبر نیست؛ فرم را دوباره باز کنید.']);
        }
        $uuid = Str::lower($uuid);
        $fingerprint = hash('sha256', json_encode([$fileId, $installmentId, $amount, $date->toDateString(), $referenceDate?->toDateString(), $note, $adminId], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($customer, $fileId, $installmentId, $amount, $date, $referenceDate, $note, $adminId, $uuid, $fingerprint): array {
            $file = CustomerLoanFile::where('customer_id', $customer->id)->whereKey($fileId)->lockForUpdate()->firstOrFail();
            $installment = CustomerLoanInstallment::where('customer_loan_file_id', $file->id)->whereKey($installmentId)->lockForUpdate()->firstOrFail();
            $this->wallets->ensureWallet($customer);
            CustomerWallet::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
            $existing = CustomerWalletTransaction::where('customer_id', $customer->id)->where('request_uuid', $uuid)->first();
            if ($existing) {
                if (($existing->meta['purpose'] ?? '') !== 'admin_installment_wallet_pay' || ($existing->meta['fingerprint'] ?? '') !== $fingerprint) {
                    throw ValidationException::withMessages(['request_uuid' => 'این شناسه قبلاً برای درخواست دیگری استفاده شده است. فرم را دوباره باز کنید.']);
                }
                $payment = $installment->payments()->whereKey($existing->meta['payment_id'] ?? 0)->first();
                if (! $payment) {
                    throw ValidationException::withMessages(['request_uuid' => 'سند پرداخت قبلی نیاز به بررسی دارد؛ برداشت جدید انجام نشد.']);
                }

                return ['payment' => $payment->load('recordedByAdmin'), 'replay' => true];
            }
            if (! $date->isSameDay(Carbon::today())) {
                throw ValidationException::withMessages(['deposited_jdate' => 'کسر از کیف پول در تاریخ روز انجام می‌شود.']);
            }
            if ($file->is_settled || $file->revoked_at !== null) {
                throw ValidationException::withMessages(['amount_toman' => 'پرونده تسویه یا فسخ شده است.']);
            }
            $ceiling = $this->finance->installmentPaymentAllowanceToman($file, $installment, $date);
            if ($amount < 1 || $amount > $ceiling) {
                throw ValidationException::withMessages(['amount_toman' => 'سقف قابل پرداخت این قسط با احتساب دیرکرد و زودکرد: '.number_format($ceiling).' تومان.']);
            }
            try {
                [, $transaction] = $this->wallets->adjust($customer, CustomerWalletTransaction::DIRECTION_WITHDRAW, $amount,
                    'پرداخت قسط '.$installment->sequence.' — پرونده '.$file->loan_code, $adminId, request()->ip(), request()->userAgent(), $uuid,
                    ['purpose' => 'admin_installment_wallet_pay', 'fingerprint' => $fingerprint, 'loan_file_id' => $file->id, 'installment_id' => $installment->id]);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['amount_toman' => $e->getMessage()]);
            }
            $payment = $installment->payments()->create([
                'payment_method' => CustomerLoanInstallmentPayment::METHOD_WALLET, 'amount_toman' => $amount,
                'reference_due_date' => $referenceDate?->toDateString(), 'deposited_at' => $date->toDateString(),
                'note' => trim(($note ?? '').' — پرداخت از کیف پول؛ تراکنش کیف '.$transaction->id), 'recorded_by_admin_id' => $adminId,
            ]);
            $transaction->meta = array_merge($transaction->meta, ['payment_id' => $payment->id]);
            $transaction->save();
            $this->syncer->syncFromPaymentRows($installment->fresh());
            $file->load('loanType');
            $this->ledger->syncFromWalletInstallmentPayment($transaction, $installment, $file, $amount);

            return ['payment' => $payment->fresh(['recordedByAdmin']), 'replay' => false];
        }, 3);
    }
}
