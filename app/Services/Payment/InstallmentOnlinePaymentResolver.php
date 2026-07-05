<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Services\Loans\LoanFileFinanceCalculator;

/**
 * مجوز و مبلغ پرداخت آنلاین/کیف‌پول قسط؛ هم‌سو با منطق نمایش پنل کاربر و سقف ثبت پرداخت ادمین.
 */
final class InstallmentOnlinePaymentResolver
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $finance,
    ) {}

    /**
     * @return array{ok: true, installment: CustomerLoanInstallment, file: CustomerLoanFile, amount_toman: int, ceiling_toman: int, slot_remaining_toman: int}|array{ok: false, message: string}
     */
    public function resolveForCustomer(Customer $customer, int $installmentId): array
    {
        $ctx = $this->resolvePaymentContext($customer, $installmentId);
        if (! ($ctx['ok'] ?? false)) {
            return $ctx;
        }

        $ceiling = (int) $ctx['ceiling_toman'];
        $slotRemaining = (int) $ctx['slot_remaining_toman'];
        $amountToPay = min($slotRemaining, $ceiling);

        if ($amountToPay < 1) {
            return ['ok' => false, 'message' => 'مبلغی برای پرداخت آنلاین باقی نمانده است.'];
        }

        return [
            'ok' => true,
            'installment' => $ctx['installment'],
            'file' => $ctx['file'],
            'amount_toman' => $amountToPay,
            'ceiling_toman' => $ceiling,
            'slot_remaining_toman' => $slotRemaining,
        ];
    }

    /**
     * مجوز پرداخت از کیف پول (کامل یا جزئی)؛ سقف مانند ثبت پرداخت ادمین است نه فقط ماندهٔ نامی قسط.
     *
     * @return array{
     *     ok: true,
     *     installment: CustomerLoanInstallment,
     *     file: CustomerLoanFile,
     *     ceiling_toman: int,
     *     slot_remaining_toman: int,
     *     nominal_amount_toman: int,
     *     paid_amount_toman: int
     * }|array{ok: false, message: string}
     */
    public function resolveWalletPaymentForCustomer(Customer $customer, int $installmentId): array
    {
        $ctx = $this->resolvePaymentContext($customer, $installmentId, 'wallet');
        if (! ($ctx['ok'] ?? false)) {
            return $ctx;
        }

        $ceiling = (int) $ctx['ceiling_toman'];
        if ($ceiling < 1) {
            return ['ok' => false, 'message' => 'طبق ماندهٔ وام، مبلغ دیگری قابل پرداخت نیست.'];
        }

        return [
            'ok' => true,
            'installment' => $ctx['installment'],
            'file' => $ctx['file'],
            'ceiling_toman' => $ceiling,
            'slot_remaining_toman' => (int) $ctx['slot_remaining_toman'],
            'nominal_amount_toman' => (int) $ctx['nominal_amount_toman'],
            'paid_amount_toman' => (int) $ctx['paid_amount_toman'],
        ];
    }

    /**
     * @return array{
     *     ok: true,
     *     installment: CustomerLoanInstallment,
     *     file: CustomerLoanFile,
     *     ceiling_toman: int,
     *     slot_remaining_toman: int,
     *     nominal_amount_toman: int,
     *     paid_amount_toman: int
     * }|array{ok: false, message: string}
     */
    private function resolvePaymentContext(Customer $customer, int $installmentId, string $channel = 'online'): array
    {
        $installment = CustomerLoanInstallment::query()
            ->whereKey($installmentId)
            ->whereHas('loanFile', static function ($q) use ($customer): void {
                $q->where('customer_id', (int) $customer->id);
            })
            ->with(['loanFile.loanType'])
            ->first();

        if ($installment === null) {
            return ['ok' => false, 'message' => 'قسط یافت نشد یا متعلق به شما نیست.'];
        }

        $file = $installment->loanFile;
        if ($file === null) {
            return ['ok' => false, 'message' => 'پروندهٔ وام یافت نشد.'];
        }

        $isRevoked = $file->revoked_at !== null;
        if ($isRevoked) {
            return ['ok' => false, 'message' => 'به‌دلیل فسخ قرارداد، پرداخت ممکن نیست.'];
        }

        $file->loadMissing('installments');

        $totalRepayable = $this->finance->totalRepayableToman($file);
        $snap = $this->finance->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $scheduleRemaining = (int) $snap['schedule_remaining_toman'];
        $signedRemaining = $file->is_settled ? 0 : ($scheduleRemaining - $discount);
        $isCreditor = ! $isRevoked && $signedRemaining < 0;
        $settledForUi = ! $isRevoked && ! $isCreditor && ($file->is_settled || $signedRemaining <= 0);
        $contractLocked = $isRevoked || $settledForUi || $isCreditor;

        $amount = (int) $installment->amount_toman;
        $paid = (int) $installment->paid_amount_toman;
        $slotFullyPaid = $amount > 0 && $paid >= $amount;
        $actionsEnabled = ! $contractLocked && ! $slotFullyPaid && $amount > 0;

        if ($actionsEnabled) {
            $currentSeq = (int) $installment->sequence;
            $priorUnpaid = $file->installments->contains(static function (CustomerLoanInstallment $i) use ($currentSeq): bool {
                if ((int) $i->sequence >= $currentSeq) {
                    return false;
                }

                return (int) $i->amount_toman > 0 && (int) $i->paid_amount_toman < (int) $i->amount_toman;
            });
            if ($priorUnpaid) {
                return ['ok' => false, 'message' => 'ابتدا قسط‌های قبلی را پرداخت نمایید.'];
            }
        }

        if (! $actionsEnabled) {
            if ($slotFullyPaid) {
                return ['ok' => false, 'message' => 'این قسط از نظر نامی تسویه شده است.'];
            }
            if ($contractLocked) {
                return ['ok' => false, 'message' => 'پرونده از نظر تعهد تسویه است یا اقدامی لازم نیست.'];
            }

            return [
                'ok' => false,
                'message' => $channel === 'wallet'
                    ? 'پرداخت از کیف پول برای این قسط در دسترس نیست.'
                    : 'پرداخت آنلاین برای این قسط در دسترس نیست.',
            ];
        }

        $ceiling = $this->finance->installmentPaymentCeilingToman($file);
        $slotRemaining = max(0, $amount - $paid);

        return [
            'ok' => true,
            'installment' => $installment,
            'file' => $file,
            'ceiling_toman' => $ceiling,
            'slot_remaining_toman' => $slotRemaining,
            'nominal_amount_toman' => $amount,
            'paid_amount_toman' => $paid,
        ];
    }
}
