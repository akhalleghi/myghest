<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerTransaction;
use App\Models\CustomerWalletOnlinePaymentIntent;
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
}
