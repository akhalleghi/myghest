<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ثبت پرداخت‌های قسطی برای بخش ماندهٔ تعهد هنگام تسویهٔ کلی آنلاین (بدون جریمهٔ نامی در اقساط).
 */
final class LoanFullSettlementOnlinePrincipalAllocator
{
    public function __construct(
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
    ) {}

    /**
     * @throws \RuntimeException
     */
    public function allocatePrincipalAcrossInstallments(
        CustomerLoanFile $file,
        int $principalToman,
        string $bankRefTrim,
        string $installmentPaymentMethod = CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE,
        ?int $recordedByAdminId = null,
        ?string $depositedAtDate = null,
    ): void {
        if ($principalToman < 0) {
            throw new \RuntimeException('مبلغ اصلی نامعتبر است.');
        }
        if ($principalToman === 0) {
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
            'installments' => static function ($q): void {
                $q->orderBy('sequence');
            },
        ]);

        $pool = $principalToman;
        /** @var Collection<int, CustomerLoanInstallment> $ordered */
        $ordered = $file->installments->sortBy('sequence')->values();

        foreach ($ordered as $inst) {
            if ($pool <= 0) {
                break;
            }
            $slotRem = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
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
