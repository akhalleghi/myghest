<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ثبت کل وجه تسویه، شامل اصل اقساط و دیرکرد، در ریز واریزی‌های همان اقساط.
 */
final class LoanFullSettlementOnlinePrincipalAllocator
{
    public function __construct(
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
    ) {}

    /**
     * @throws \RuntimeException
     */
    public function allocateDebtAcrossInstallments(
        CustomerLoanFile $file,
        int $amountToman,
        string $bankRefTrim,
        string $installmentPaymentMethod = CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE,
        ?int $recordedByAdminId = null,
        ?string $depositedAtDate = null,
    ): void {
        if ($amountToman < 0) {
            throw new \RuntimeException('مبلغ تسویه نامعتبر است.');
        }
        if ($amountToman === 0) {
            return;
        }

        $note = match ($installmentPaymentMethod) {
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET => 'تسویهٔ یکجای بدهی از کیف پول'.($bankRefTrim !== '' ? ' — مرجع: '.$bankRefTrim : ''),
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE => 'تسویهٔ یکجای بدهی از درگاه'.($bankRefTrim !== '' ? ' — مرجع: '.$bankRefTrim : ''),
            default => 'تسویهٔ یکجای بدهی'.($bankRefTrim !== '' ? ' — '.$bankRefTrim : ''),
        };
        $depositedAt = $depositedAtDate !== null && trim($depositedAtDate) !== ''
            ? trim($depositedAtDate)
            : Carbon::now()->startOfDay()->format('Y-m-d');

        $file->loadMissing([
            'loanType',
            'installments.payments',
            'installments' => static function ($q): void {
                $q->orderBy('sequence');
            },
        ]);

        $pool = $amountToman;
        /** @var Collection<int, CustomerLoanInstallment> $ordered */
        $ordered = $file->installments->sortBy('sequence')->values();

        foreach ($ordered as $inst) {
            $inst->setRelation('loanFile', $file);
            if ($pool <= 0) {
                break;
            }
            $slotRem = $inst->remainingDebtToman();
            if ($slotRem <= 0) {
                continue;
            }
            $pay = min($slotRem, $pool);
            if ($pay <= 0) {
                continue;
            }
            CustomerLoanInstallmentPayment::query()->create([
                'customer_loan_installment_id' => (int) $inst->id,
                'payment_method' => $installmentPaymentMethod,
                'amount_toman' => $pay,
                'reference_due_date' => null,
                'deposited_at' => $depositedAt,
                'note' => $note,
                'recorded_by_admin_id' => $recordedByAdminId,
            ]);
            $inst->refresh();
            $this->syncer->syncFromPaymentRows($inst);
            $pool -= $pay;
        }

        if ($pool !== 0) {
            throw new \RuntimeException('تخصیص مبلغ تسویه با ماندهٔ اقساط هم‌خوان نیست؛ با پشتیبانی تماس بگیرید.');
        }
    }
}
