<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Collection;

/**
 * دادهٔ نمایشی امن برای کارت‌های وام داشبورد کاربر (هم‌سو با منطق نمایش پنل ادمین).
 */
final class CustomerLoanPortalPresenter
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $finance,
        private readonly LoanInstallmentScheduleService $schedule,
    ) {}

    /**
     * @return array{loan_count: int, loans: list<array<string, mixed>>}
     */
    public function forDashboard(Customer $customer): array
    {
        $files = CustomerLoanFile::query()
            ->where('customer_id', $customer->id)
            ->with([
                'loanType',
                'installments' => static function ($q): void {
                    $q->orderBy('sequence');
                },
                'installments.payments',
            ])
            ->orderByRaw('CASE WHEN revoked_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->get();

        $loans = [];
        foreach ($files as $file) {
            $loans[] = $this->mapLoanForPortal($file);
        }

        return [
            'loan_count' => count($loans),
            'loans' => $loans,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanForPortal(CustomerLoanFile $file): array
    {
        $this->schedule->ensureSchedule($file);
        $file->unsetRelation('installments');
        $file->loadMissing([
            'loanType',
            'installments' => static function ($q): void {
                $q->orderBy('sequence');
            },
            'installments.payments',
        ]);
        $isRevoked = $file->revoked_at !== null;

        $totalRepayable = $this->finance->totalRepayableToman($file);
        $snap = $this->finance->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $scheduleRemaining = (int) $snap['schedule_remaining_toman'];

        $signedRemaining = $file->is_settled ? 0 : ($scheduleRemaining - $discount);
        $isCreditor = ! $isRevoked && $signedRemaining < 0;
        $settledForUi = ! $isRevoked && ! $isCreditor && ($file->is_settled || $signedRemaining <= 0);
        $contractLocked = $isRevoked || $settledForUi || $isCreditor;

        $remainingAmount = $isCreditor ? 0 : max(0, $signedRemaining);
        $creditorOverpayToman = $isCreditor ? (int) abs($signedRemaining) : 0;

        $instColl = $file->installments->sortBy('sequence')->values();
        $installmentPayCeilingToman = $this->finance->installmentPaymentCeilingToman($file);
        $unpaidCount = (int) $instColl->filter(static function (CustomerLoanInstallment $i): bool {
            return (int) $i->paid_amount_toman < (int) $i->amount_toman;
        })->count();

        $lateCoef = (float) ($file->loanType?->daily_late_coefficient ?? 0);

        $ribbon = null;
        $ribbonIcon = '';
        if ($isRevoked) {
            $ribbon = 'revoked';
            $ribbonIcon = 'fa-solid fa-ban';
        } elseif ($isCreditor) {
            $ribbon = 'creditor';
            $ribbonIcon = 'fa-solid fa-scale-balanced';
        } elseif ($settledForUi) {
            $ribbon = 'settled';
            $ribbonIcon = 'fa-solid fa-circle-check';
        }

        $totalPaid = (int) $snap['total_paid_toman'];
        $progressPct = $totalRepayable > 0
            ? (int) min(100, max(0, (int) round(100 * $totalPaid / $totalRepayable)))
            : 0;

        $installmentsOut = [];
        $ordered = $instColl->values();
        foreach ($ordered as $idx => $inst) {
            $priorNominalSlotUnpaid = false;
            for ($j = 0; $j < $idx; $j++) {
                $prev = $ordered[$j];
                if ((int) $prev->amount_toman > 0 && (int) $prev->paid_amount_toman < (int) $prev->amount_toman) {
                    $priorNominalSlotUnpaid = true;
                    break;
                }
            }
            $installmentsOut[] = $this->mapInstallmentForPortal(
                $inst,
                $lateCoef,
                $contractLocked,
                $isRevoked,
                $priorNominalSlotUnpaid,
                $installmentPayCeilingToman
            );
        }

        $hasEarlyRepayment = $this->loanHasEarlyFullyPaidInstallment($instColl);

        $showSettleButton = ! $isRevoked
            && ! $file->is_settled
            && ! $isCreditor
            && $signedRemaining > 0;

        $settleDisabledReasonFa = '';
        if ($isRevoked) {
            $settleDisabledReasonFa = 'به‌دلیل فسخ قرارداد، تسویهٔ آنلاین از اینجا در دسترس نیست.';
        } elseif ($settledForUi) {
            $settleDisabledReasonFa = 'این پرونده از نظر تعهدی تسویه محسوب می‌شود.';
        } elseif ($isCreditor) {
            $settleDisabledReasonFa = 'وضعیت بستانکار است و ماندهٔ بدهکار برای تسویه وجود ندارد.';
        } elseif ($signedRemaining <= 0) {
            $settleDisabledReasonFa = 'ماندهٔ قابل تسویه وجود ندارد.';
        }

        $subRaw = $file->sub_file_number;
        $subMissing = $subRaw === null || $subRaw === ''
            || (is_numeric($subRaw) && (int) $subRaw === 0);
        $subFileDisplayFa = $subMissing ? 'ندارد' : Jalali::enToFaNumbers((string) $subRaw);

        $lateFeeToman = (int) $snap['late_fee_so_far_toman'];
        $earlyLateMoneyLineFa = $this->formatMoneyFa($lateFeeToman);
        $earlyLateDetailFa = $lateFeeToman > 0
            ? 'برآورد جریمهٔ دیرکرد تا امروز'
            : ($hasEarlyRepayment ? 'بدون برآورد دیرکرد؛ زودکرد در سوابق پرداخت ثبت شده' : 'بدون برآورد دیرکرد');

        $remainingStatusLineFa = $this->formatRemainingStatusLineFa(
            $isRevoked,
            $isCreditor,
            $settledForUi,
            $remainingAmount,
            $creditorOverpayToman
        );

        $fullSettlementOnlineToman = 0;
        $fullSettlementOnlineFa = '';
        if ($showSettleButton) {
            $fullSettlementOnlineToman = $remainingAmount + (int) $snap['late_fee_so_far_toman'];
            $fullSettlementOnlineFa = $this->formatMoneyFa($fullSettlementOnlineToman);
        }

        return [
            'id' => (int) $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_code_fa' => Jalali::enToFaNumbers((string) $file->loan_code),
            'loan_title' => (string) ($file->loanType?->title ?? 'وام'),
            'loan_start_jalali' => $this->toJalaliFa($file->loan_start_date),
            'amount_toman' => (int) $file->amount_toman,
            'amount_fa' => $this->formatMoneyFa((int) $file->amount_toman),
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'installment_amount_fa' => $this->formatMoneyFa((int) $file->installment_amount_toman),
            'installments_total' => (int) $file->installments_count,
            'installments_total_fa' => Jalali::enToFaNumbers((string) (int) $file->installments_count),
            'paid_installments_count' => (int) $snap['paid_installments_count'],
            'paid_installments_count_fa' => Jalali::enToFaNumbers((string) (int) $snap['paid_installments_count']),
            'paid_installments_slot_count' => (int) $snap['paid_installments_slot_count'],
            'paid_installments_slot_count_fa' => Jalali::enToFaNumbers((string) (int) $snap['paid_installments_slot_count']),
            'paid_amount_toman' => $totalPaid,
            'paid_amount_fa' => $this->formatMoneyFa($totalPaid),
            'remaining_amount_toman' => $remainingAmount,
            'remaining_amount_fa' => $this->formatMoneyFa($remainingAmount),
            'creditor_overpay_toman' => $creditorOverpayToman,
            'creditor_overpay_fa' => $creditorOverpayToman > 0 ? $this->formatMoneyFa($creditorOverpayToman) : '',
            'remaining_signed_toman' => $signedRemaining,
            'remaining_installments_count' => $unpaidCount,
            'remaining_installments_count_fa' => Jalali::enToFaNumbers((string) $unpaidCount),
            'late_fee_estimate_toman' => (int) $snap['late_fee_so_far_toman'],
            'late_fee_estimate_fa' => $this->formatMoneyFa((int) $snap['late_fee_so_far_toman']),
            'early_late_money_line_fa' => $earlyLateMoneyLineFa,
            'early_late_detail_fa' => $earlyLateDetailFa,
            'has_early_repayment' => $hasEarlyRepayment,
            'sub_file_display_fa' => $subFileDisplayFa,
            'down_payment_toman' => (int) $file->down_payment_toman,
            'down_payment_fa' => $this->formatMoneyFa((int) $file->down_payment_toman),
            'discount_toman' => $discount,
            'discount_fa' => $this->formatMoneyFa($discount),
            'remaining_status_line_fa' => $remainingStatusLineFa,
            'settled_yes_no_fa' => $settledForUi ? 'بله' : 'خیر',
            'settle_disabled_reason_fa' => $settleDisabledReasonFa,
            'is_settled' => (bool) $file->is_settled,
            'is_revoked' => $isRevoked,
            'is_creditor' => $isCreditor,
            'settled_for_ui' => $settledForUi,
            'contract_locked' => $contractLocked,
            'ribbon' => $ribbon,
            'ribbon_icon' => $ribbonIcon,
            'settled_at_jalali' => $file->settled_at !== null ? $this->toJalaliFa($file->settled_at) : null,
            'revoked_at_jalali' => $file->revoked_at !== null ? $this->toJalaliFa($file->revoked_at) : null,
            'total_repayable_toman' => $totalRepayable,
            'total_repayable_fa' => $this->formatMoneyFa($totalRepayable),
            'progress_percent' => $progressPct,
            'show_settle_button' => $showSettleButton,
            'full_settlement_online_toman' => $fullSettlementOnlineToman,
            'full_settlement_online_fa' => $fullSettlementOnlineFa,
            'installments' => $installmentsOut,
        ];
    }

    /**
     * مبالغ تسویهٔ کلی آنلاین (ماندهٔ تعهد + برآورد جریمهٔ دیرکرد) در صورت امکان پرداخت.
     *
     * @return array{principal_toman: int, late_fee_toman: int, amount_toman: int}|null
     */
    public function fullSettlementOnlinePaymentQuote(CustomerLoanFile $file): ?array
    {
        $this->schedule->ensureSchedule($file);
        $file->unsetRelation('installments');
        $file->loadMissing([
            'loanType',
            'installments' => static function ($q): void {
                $q->orderBy('sequence');
            },
            'installments.payments',
        ]);

        $isRevoked = $file->revoked_at !== null;

        $totalRepayable = $this->finance->totalRepayableToman($file);
        $snap = $this->finance->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $discount = (int) ($file->discount_amount_toman ?? 0);
        $scheduleRemaining = (int) $snap['schedule_remaining_toman'];

        $signedRemaining = $file->is_settled ? 0 : ($scheduleRemaining - $discount);
        $isCreditor = ! $isRevoked && $signedRemaining < 0;

        $remainingAmount = $isCreditor ? 0 : max(0, $signedRemaining);

        $showSettleButton = ! $isRevoked
            && ! $file->is_settled
            && ! $isCreditor
            && $signedRemaining > 0;

        if (! $showSettleButton) {
            return null;
        }

        $lateFeeToman = (int) $snap['late_fee_so_far_toman'];
        $amountToman = $remainingAmount + $lateFeeToman;

        return [
            'principal_toman' => $remainingAmount,
            'late_fee_toman' => $lateFeeToman,
            'amount_toman' => $amountToman,
        ];
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    private function loanHasEarlyFullyPaidInstallment(Collection $installments): bool
    {
        foreach ($installments as $inst) {
            $amount = (int) $inst->amount_toman;
            $paid = (int) $inst->paid_amount_toman;
            if ($amount <= 0 || $paid < $amount) {
                continue;
            }
            if ($inst->paid_at === null) {
                continue;
            }
            $due = Carbon::parse($inst->due_date)->startOfDay();
            $paidAt = Carbon::parse($inst->paid_at)->startOfDay();
            if ($paidAt->lt($due)) {
                return true;
            }
        }

        return false;
    }

    private function formatRemainingStatusLineFa(
        bool $isRevoked,
        bool $isCreditor,
        bool $settledForUi,
        int $remainingAmount,
        int $creditorOverpayToman
    ): string {
        if ($isRevoked) {
            return 'فسخ پرونده — بدون تعهد فعال';
        }
        if ($isCreditor) {
            return $this->formatMoneyFa($creditorOverpayToman).' بستانکار';
        }
        if ($settledForUi) {
            return '۰ تومان — تسویه';
        }

        return $this->formatMoneyFa($remainingAmount).' بدهکار';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInstallmentForPortal(
        CustomerLoanInstallment $inst,
        float $lateCoef,
        bool $contractLocked,
        bool $isRevoked,
        bool $priorNominalSlotUnpaid,
        int $installmentPayCeilingToman
    ): array {
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $now = Carbon::now()->startOfDay();
        $amount = (int) $inst->amount_toman;
        $paid = (int) $inst->paid_amount_toman;
        $paidAt = $inst->paid_at !== null ? Carbon::parse($inst->paid_at)->startOfDay() : null;
        $slotRemaining = max(0, $amount - $paid);
        $slotFullyPaid = $amount > 0 && $paid >= $amount;
        $partialPaid = $paid > 0 && ! $slotFullyPaid;

        $actionsEnabled = ! $isRevoked && ! $contractLocked && ! $slotFullyPaid && $amount > 0;
        $onlinePayableToman = (! $slotFullyPaid && $amount > 0)
            ? min($slotRemaining, max(0, $installmentPayCeilingToman))
            : 0;
        $onlinePayEligible = $actionsEnabled && ! $priorNominalSlotUnpaid && $onlinePayableToman > 0;
        $onlinePayPriorSequenceBlock = $actionsEnabled && $priorNominalSlotUnpaid;

        $depositCarbon = $this->resolveLatestDepositCarbon($inst);
        $depositJalali = $depositCarbon !== null ? $this->toJalaliFa($depositCarbon) : '—';

        $statusLine = '';
        $statusNote = '';
        $statusIcon = 'fa-regular fa-clock';
        $statusTone = 'pending';

        if ($slotFullyPaid && $paidAt !== null) {
            $statusTone = 'ok';
            $statusIcon = 'fa-solid fa-circle-check';
            if ($paidAt->lt($due)) {
                $days = (int) $paidAt->diffInDays($due);
                $statusLine = 'تسویهٔ قسط';
                $statusNote = 'زودکرد: '.Jalali::enToFaNumbers((string) $days).' روز';
            } elseif ($paidAt->gt($due)) {
                $days = (int) $due->diffInDays($paidAt);
                $pen = $this->finance->estimatePenaltyAtDateToman($inst, $lateCoef, $paidAt);
                $statusLine = 'تسویهٔ قسط';
                $statusNote = 'دیرکرد: '.Jalali::enToFaNumbers((string) $days).' روز — برآورد جریمه تا تاریخ پرداخت: '.$this->formatMoneyFa($pen);
            } else {
                $statusLine = 'تسویهٔ قسط';
                $statusNote = 'پرداخت در روز سررسید';
            }
        } elseif ($slotFullyPaid) {
            $statusLine = 'تسویهٔ قسط';
            $statusNote = '—';
            $statusTone = 'ok';
            $statusIcon = 'fa-solid fa-circle-check';
        } elseif ($now->gt($due)) {
            $pen = $this->finance->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);
            $days = (int) $due->diffInDays($now);
            $statusLine = 'معوق';
            $statusNote = Jalali::enToFaNumbers((string) $days).' روز از سررسید — برآورد جریمه: '.$this->formatMoneyFa($pen);
            $statusTone = 'danger';
            $statusIcon = 'fa-solid fa-triangle-exclamation';
        } else {
            $statusLine = 'سررسید نشده';
            $statusNote = '—';
            $statusTone = 'pending';
            $statusIcon = 'fa-regular fa-clock';
        }

        if ($partialPaid && ! $slotFullyPaid) {
            $statusIcon = 'fa-solid fa-scale-balanced';
            if ($statusTone === 'pending') {
                $statusTone = 'partial';
            }
            if ($statusNote === '—') {
                $statusNote = 'پرداخت جزئی؛ ماندهٔ نامی این قسط '.$this->formatMoneyFa($slotRemaining);
            } else {
                $statusNote .= ' — ماندهٔ نامی: '.$this->formatMoneyFa($slotRemaining);
            }
        }

        return [
            'id' => (int) $inst->id,
            'sequence' => (int) $inst->sequence,
            'sequence_fa' => Jalali::enToFaNumbers((string) (int) $inst->sequence),
            'amount_toman' => $amount,
            'amount_fa' => $this->formatMoneyFa($amount),
            'due_jalali' => $this->toJalaliFa($due),
            'paid_amount_toman' => $paid,
            'paid_fa' => $paid > 0 ? $this->formatMoneyFa($paid) : '—',
            'slot_remaining_toman' => $slotRemaining,
            'slot_remaining_fa' => $this->formatMoneyFa($slotRemaining),
            'online_payable_toman' => $onlinePayableToman,
            'online_payable_fa' => $onlinePayableToman > 0 ? $this->formatMoneyFa($onlinePayableToman) : '—',
            'online_pay_eligible' => $onlinePayEligible,
            'online_pay_prior_sequence_block' => $onlinePayPriorSequenceBlock,
            'deposit_jalali' => $depositJalali,
            'early_late_cell_fa' => $this->formatInstallmentEarlyLateCellFa(
                $inst,
                $lateCoef,
                $isRevoked,
                $due,
                $now,
                $amount,
                $paid,
                $slotFullyPaid,
                $paidAt
            ),
            'status_line' => $statusLine,
            'status_note' => $statusNote,
            'status_icon' => $statusIcon,
            'status_tone' => $statusTone,
            'slot_fully_paid' => $slotFullyPaid,
            'actions_enabled' => $actionsEnabled,
        ];
    }

    private function formatInstallmentEarlyLateCellFa(
        CustomerLoanInstallment $inst,
        float $lateCoef,
        bool $isRevoked,
        Carbon $due,
        Carbon $now,
        int $amount,
        int $paid,
        bool $slotFullyPaid,
        ?Carbon $paidAt
    ): string {
        if ($isRevoked) {
            return '—';
        }
        if ($slotFullyPaid && $paidAt !== null) {
            if ($paidAt->lt($due)) {
                $days = (int) $paidAt->diffInDays($due);

                return 'زودکرد: '.Jalali::enToFaNumbers((string) $days).' روز';
            }
            if ($paidAt->gt($due)) {
                $days = (int) $due->diffInDays($paidAt);
                $pen = $this->finance->estimatePenaltyAtDateToman($inst, $lateCoef, $paidAt);

                return 'دیرکرد: '.Jalali::enToFaNumbers((string) $days).' روز — '.$this->formatMoneyFa($pen);
            }

            return 'به‌موقع';
        }
        if ($slotFullyPaid) {
            return 'تسویهٔ قسط';
        }
        if ($now->gt($due)) {
            $days = (int) $due->diffInDays($now);
            $pen = $this->finance->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);

            return 'دیرکرد: '.Jalali::enToFaNumbers((string) $days).' روز — '.$this->formatMoneyFa($pen);
        }

        return '—';
    }

    private function resolveLatestDepositCarbon(CustomerLoanInstallment $inst): ?Carbon
    {
        /** @var Collection<int, CustomerLoanInstallmentPayment> $payments */
        $payments = $inst->payments;
        if ($payments->isNotEmpty()) {
            $max = null;
            foreach ($payments as $p) {
                $d = Carbon::parse($p->deposited_at)->startOfDay();
                if ($max === null || $d->gt($max)) {
                    $max = $d;
                }
            }

            return $max;
        }
        if ($inst->paid_at !== null) {
            return Carbon::parse($inst->paid_at)->startOfDay();
        }

        return null;
    }

    private function toJalaliFa(mixed $date): string
    {
        if ($date === null) {
            return '—';
        }
        $c = $date instanceof Carbon ? $date : Carbon::parse($date)->startOfDay();

        return Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d'));
    }

    private function formatMoneyFa(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $toman), 0, '.', ',')).' تومان';
    }
}
