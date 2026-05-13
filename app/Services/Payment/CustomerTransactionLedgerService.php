<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerTransaction;
use App\Models\CustomerWalletOnlinePaymentIntent;
use App\Models\CustomerWalletTransaction;
use Hekmatinasser\Jalali\Jalali;

/**
 * همگام‌سازی دفتر تراکنش مشتری با پرداخت آنلاین قسط و شارژ کیف پول.
 */
final class CustomerTransactionLedgerService
{
    public function syncFromInstallmentIntent(CustomerLoanInstallmentOnlinePaymentIntent $intent): void
    {
        $intent->loadMissing(['installment.loanFile.loanType']);

        $installment = $intent->installment;
        $file = $installment?->loanFile;
        $loanTitle = (string) ($file?->loanType?->title ?? 'وام');
        $loanCode = (string) ($file?->loan_code ?? '');
        $seq = $installment !== null ? (int) $installment->sequence : 0;

        $loanCodeFa = $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—';
        $seqFa = $installment !== null ? Jalali::enToFaNumbers((string) max(1, $seq)) : '—';

        $detailParts = array_filter([
            $loanTitle !== '' && $loanTitle !== 'وام' ? 'نوع وام: '.$loanTitle : null,
            $loanCode !== '' ? 'کد پرونده: '.$loanCodeFa : null,
            $installment !== null ? 'قسط: '.$seqFa : null,
        ]);
        $detail = $detailParts !== [] ? implode(' — ', $detailParts) : null;

        $meta = [
            'loan_file_id' => $file !== null ? (int) $file->id : null,
            'installment_id' => $installment !== null ? (int) $installment->id : null,
            'loan_code' => $loanCode !== '' ? $loanCode : null,
        ];

        $row = CustomerTransaction::query()->firstOrNew([
            'source_type' => CustomerLoanInstallmentOnlinePaymentIntent::class,
            'source_id' => (int) $intent->id,
        ]);

        $row->customer_id = (int) $intent->customer_id;
        $row->kind = CustomerTransaction::KIND_INSTALLMENT_ONLINE_PAYMENT;
        $row->status = (string) $intent->status;
        $row->amount_toman = (int) $intent->expected_amount_toman;
        $row->amount_rial = (int) $intent->expected_amount_rial;
        $row->gateway_key = $intent->gateway_key !== null && trim((string) $intent->gateway_key) !== ''
            ? trim((string) $intent->gateway_key)
            : null;
        $row->track_id = $intent->track_id !== null ? (int) $intent->track_id : null;
        $ref = $intent->zibal_ref_number;
        $row->bank_reference = $ref !== null && trim((string) $ref) !== '' ? trim((string) $ref) : null;
        $row->title = 'پرداخت آنلاین قسط';
        $row->detail = $detail;
        $row->meta = $meta;
        $fail = $intent->failure_reason;
        $row->failure_reason = $fail !== null && trim((string) $fail) !== '' ? (string) $fail : null;

        $row->save();
    }

    public function syncFromWalletTopupIntent(CustomerWalletOnlinePaymentIntent $intent): void
    {
        $intent->loadMissing(['customer']);

        $detail = 'شارژ آنلاین کیف پول';
        $meta = [
            'intent_id' => (int) $intent->id,
        ];

        $row = CustomerTransaction::query()->firstOrNew([
            'source_type' => CustomerWalletOnlinePaymentIntent::class,
            'source_id' => (int) $intent->id,
        ]);

        $row->customer_id = (int) $intent->customer_id;
        $row->kind = CustomerTransaction::KIND_WALLET_TOPUP;
        $row->status = (string) $intent->status;
        $row->amount_toman = (int) $intent->expected_amount_toman;
        $row->amount_rial = (int) $intent->expected_amount_rial;
        $row->gateway_key = $intent->gateway_key !== null && trim((string) $intent->gateway_key) !== ''
            ? trim((string) $intent->gateway_key)
            : null;
        $row->track_id = $intent->track_id !== null ? (int) $intent->track_id : null;
        $ref = $intent->zibal_ref_number;
        $row->bank_reference = $ref !== null && trim((string) $ref) !== '' ? trim((string) $ref) : null;
        $row->title = 'شارژ کیف پول (درگاه)';
        $row->detail = $detail;
        $row->meta = $meta;
        $fail = $intent->failure_reason;
        $row->failure_reason = $fail !== null && trim((string) $fail) !== '' ? (string) $fail : null;

        $row->save();
    }

    public function syncFromFullSettlementIntent(CustomerLoanFullSettlementOnlinePaymentIntent $intent): void
    {
        $intent->loadMissing(['loanFile.loanType']);

        $file = $intent->loanFile;
        $loanTitle = (string) ($file?->loanType?->title ?? 'وام');
        $loanCode = (string) ($file?->loan_code ?? '');
        $loanCodeFa = $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—';

        $detailParts = array_filter([
            $loanTitle !== '' && $loanTitle !== 'وام' ? 'نوع وام: '.$loanTitle : null,
            $loanCode !== '' ? 'کد پرونده: '.$loanCodeFa : null,
            'تسویهٔ کلی بدهی (درگاه)',
        ]);
        $detail = $detailParts !== [] ? implode(' — ', $detailParts) : null;

        $meta = [
            'loan_file_id' => $file !== null ? (int) $file->id : null,
            'loan_code' => $loanCode !== '' ? $loanCode : null,
            'principal_component_toman' => (int) $intent->principal_component_toman,
            'late_fee_component_toman' => (int) $intent->late_fee_component_toman,
        ];

        $row = CustomerTransaction::query()->firstOrNew([
            'source_type' => CustomerLoanFullSettlementOnlinePaymentIntent::class,
            'source_id' => (int) $intent->id,
        ]);

        $row->customer_id = (int) $intent->customer_id;
        $row->kind = CustomerTransaction::KIND_FULL_SETTLEMENT_ONLINE_PAYMENT;
        $row->status = (string) $intent->status;
        $row->amount_toman = (int) $intent->expected_amount_toman;
        $row->amount_rial = (int) $intent->expected_amount_rial;
        $row->gateway_key = $intent->gateway_key !== null && trim((string) $intent->gateway_key) !== ''
            ? trim((string) $intent->gateway_key)
            : null;
        $row->track_id = $intent->track_id !== null ? (int) $intent->track_id : null;
        $ref = $intent->zibal_ref_number;
        $row->bank_reference = $ref !== null && trim((string) $ref) !== '' ? trim((string) $ref) : null;
        $row->title = 'تسویهٔ کلی بدهی (درگاه)';
        $row->detail = $detail;
        $row->meta = $meta;
        $fail = $intent->failure_reason;
        $row->failure_reason = $fail !== null && trim((string) $fail) !== '' ? (string) $fail : null;

        $row->save();
    }

    public function syncFromWalletInstallmentPayment(
        CustomerWalletTransaction $wtx,
        CustomerLoanInstallment $installment,
        CustomerLoanFile $file,
        int $amountToman,
    ): void {
        $wtx->loadMissing(['customer']);

        $loanTitle = (string) ($file->loanType?->title ?? 'وام');
        $loanCode = (string) ($file->loan_code ?? '');
        $seq = (int) $installment->sequence;

        $loanCodeFa = $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—';
        $seqFa = Jalali::enToFaNumbers((string) max(1, $seq));

        $detailParts = array_filter([
            $loanTitle !== '' && $loanTitle !== 'وام' ? 'نوع وام: '.$loanTitle : null,
            $loanCode !== '' ? 'کد پرونده: '.$loanCodeFa : null,
            'قسط: '.$seqFa,
        ]);
        $detail = $detailParts !== [] ? implode(' — ', $detailParts) : null;

        $meta = [
            'loan_file_id' => (int) $file->id,
            'installment_id' => (int) $installment->id,
            'loan_code' => $loanCode !== '' ? $loanCode : null,
            'wallet_transaction_id' => (int) $wtx->id,
        ];

        $row = CustomerTransaction::query()->firstOrNew([
            'source_type' => CustomerWalletTransaction::class,
            'source_id' => (int) $wtx->id,
        ]);

        $row->customer_id = (int) $wtx->customer_id;
        $row->kind = CustomerTransaction::KIND_INSTALLMENT_WALLET_PAYMENT;
        $row->status = CustomerTransaction::STATUS_COMPLETED;
        $row->amount_toman = $amountToman;
        $row->amount_rial = $amountToman * 10;
        $row->gateway_key = null;
        $row->track_id = null;
        $row->bank_reference = 'wtx-'.(string) $wtx->id;
        $row->title = 'پرداخت قسط (کیف پول)';
        $row->detail = $detail;
        $row->meta = $meta;
        $row->failure_reason = null;

        $row->save();
    }

    /**
     * @param  array{principal_toman: int, late_fee_toman: int, amount_toman: int}  $quote
     */
    public function syncFromWalletFullSettlementPayment(
        CustomerWalletTransaction $wtx,
        CustomerLoanFile $file,
        array $quote,
        int $amountToman,
    ): void {
        $file->loadMissing('loanType');

        $loanTitle = (string) ($file->loanType?->title ?? 'وام');
        $loanCode = (string) ($file->loan_code ?? '');
        $loanCodeFa = $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—';

        $detailParts = array_filter([
            $loanTitle !== '' && $loanTitle !== 'وام' ? 'نوع وام: '.$loanTitle : null,
            $loanCode !== '' ? 'کد پرونده: '.$loanCodeFa : null,
            'تسویهٔ کلی بدهی (کیف پول)',
        ]);
        $detail = $detailParts !== [] ? implode(' — ', $detailParts) : null;

        $meta = [
            'loan_file_id' => (int) $file->id,
            'loan_code' => $loanCode !== '' ? $loanCode : null,
            'principal_component_toman' => (int) $quote['principal_toman'],
            'late_fee_component_toman' => (int) $quote['late_fee_toman'],
            'wallet_transaction_id' => (int) $wtx->id,
        ];

        $row = CustomerTransaction::query()->firstOrNew([
            'source_type' => CustomerWalletTransaction::class,
            'source_id' => (int) $wtx->id,
        ]);

        $row->customer_id = (int) $wtx->customer_id;
        $row->kind = CustomerTransaction::KIND_FULL_SETTLEMENT_WALLET_PAYMENT;
        $row->status = CustomerTransaction::STATUS_COMPLETED;
        $row->amount_toman = $amountToman;
        $row->amount_rial = $amountToman * 10;
        $row->gateway_key = null;
        $row->track_id = null;
        $row->bank_reference = 'wtx-'.(string) $wtx->id;
        $row->title = 'تسویهٔ کلی بدهی (کیف پول)';
        $row->detail = $detail;
        $row->meta = $meta;
        $row->failure_reason = null;

        $row->save();
    }
}
