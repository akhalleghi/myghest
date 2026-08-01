<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\LoanFullSettlementOnlinePrincipalAllocator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Sms\PortalAdminSmsDispatcher;
use App\Services\Wallet\CustomerWalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * پرداخت اقساط و تسویهٔ کلی از کیف پول؛ هم‌ارز امن با مسیر درگاه از نظر قفل ردیف و اعتبارسنجی مبلغ.
 * Dedup: مقدار ذخیره‌شده در `customer_wallet_transactions.request_uuid` همان UUID درخواست کلاینت است
 * (ستون uuid در DB حداکثر ۳۶ کاراکتر است؛ از پیشوند رشته‌ای استفاده نکنید).
 */
final class PortalLoanWalletPaymentService
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly InstallmentOnlinePaymentResolver $installmentResolver,
        private readonly CustomerWalletService $walletService,
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
        private readonly CustomerTransactionLedgerService $ledger,
        private readonly CustomerLoanPortalPresenter $portalPresenter,
        private readonly LoanFullSettlementOnlinePrincipalAllocator $fullSettlementAllocator,
    ) {}

    /**
     * @return array{ok: bool, message: string, amount_toman?: int, shortfall_toman?: int, needs_topup?: bool, replay?: bool}
     */
    public function payInstallmentFromWallet(Customer $customer, int $installmentId, string $idempotencyKey, ?int $requestedAmountToman = null): array
    {
        $key = $this->normalizeIdempotencyKey($idempotencyKey);
        if ($key === null) {
            return ['ok' => false, 'message' => 'شناسهٔ یکتای درخواست نامعتبر است؛ صفحه را تازه کنید و دوباره تلاش کنید.'];
        }

        $requestUuid = Str::lower($key);

        try {
            return DB::transaction(function () use ($customer, $installmentId, $requestUuid, $requestedAmountToman): array {
                $existing = CustomerWalletTransaction::query()
                    ->where('customer_id', (int) $customer->id)
                    ->where('request_uuid', $requestUuid)
                    ->first();
                if ($existing !== null) {
                    return $this->replayInstallmentWalletResponse($existing);
                }

                $resolved = $this->installmentResolver->resolveWalletPaymentForCustomer($customer, $installmentId);
                if (! ($resolved['ok'] ?? false)) {
                    return ['ok' => false, 'message' => (string) ($resolved['message'] ?? 'امکان پرداخت وجود ندارد.')];
                }

                /** @var CustomerLoanInstallment $installment */
                $installment = $resolved['installment'];
                /** @var CustomerLoanFile $file */
                $file = $resolved['file'];

                $installment = CustomerLoanInstallment::query()
                    ->whereKey((int) $installment->id)
                    ->lockForUpdate()
                    ->first();
                if ($installment === null) {
                    return ['ok' => false, 'message' => 'قسط یافت نشد.'];
                }

                $file = CustomerLoanFile::query()
                    ->whereKey((int) $file->id)
                    ->where('customer_id', (int) $customer->id)
                    ->lockForUpdate()
                    ->first();
                if ($file === null) {
                    return ['ok' => false, 'message' => 'پروندهٔ وام یافت نشد.'];
                }

                $resolved2 = $this->installmentResolver->resolveWalletPaymentForCustomer($customer, (int) $installment->id);
                if (! ($resolved2['ok'] ?? false)) {
                    return ['ok' => false, 'message' => (string) ($resolved2['message'] ?? 'وضعیت قسط تغییر کرده است.')];
                }

                $ceilingToman = (int) $resolved2['ceiling_toman'];
                if ($ceilingToman < 1) {
                    return ['ok' => false, 'message' => 'طبق ماندهٔ وام، مبلغ دیگری قابل پرداخت نیست.'];
                }

                $balance = $this->lockedWalletBalanceToman($customer);
                if ($balance < 1) {
                    return [
                        'ok' => false,
                        'message' => 'موجودی کیف پول کافی نیست.',
                        'needs_topup' => true,
                    ];
                }

                $amountToman = $requestedAmountToman ?? min($balance, $ceilingToman);
                if ($amountToman < 1) {
                    return ['ok' => false, 'message' => 'مبلغ پرداخت نامعتبر است.'];
                }
                if ($amountToman > $ceilingToman) {
                    return [
                        'ok' => false,
                        'message' => 'مبلغ درخواستی از ماندهٔ قابل پرداخت وام بیشتر است.',
                    ];
                }
                if ($amountToman > $balance) {
                    return [
                        'ok' => false,
                        'message' => 'موجودی کیف پول برای این پرداخت کافی نیست.',
                        'amount_toman' => $amountToman,
                        'shortfall_toman' => $amountToman - $balance,
                        'needs_topup' => true,
                    ];
                }

                $metaBase = [
                    'purpose' => 'installment_wallet_pay',
                    'installment_id' => (int) $installment->id,
                    'loan_file_id' => (int) $file->id,
                ];

                $desc = 'پرداخت قسط '.(string) $installment->sequence.' — پرونده '.(string) $file->loan_code;
                try {
                    [, $wtx, $dedup] = $this->walletService->adjust(
                        $customer,
                        CustomerWalletTransaction::DIRECTION_WITHDRAW,
                        $amountToman,
                        $desc,
                        null,
                        (string) (request()->ip() ?? ''),
                        (string) (request()->userAgent() ?? ''),
                        $requestUuid,
                        $metaBase
                    );
                } catch (\RuntimeException $e) {
                    $msg = $e->getMessage();

                    return str_contains($msg, 'کافی نیست')
                        ? [
                            'ok' => false,
                            'message' => 'موجودی کیف پول برای این پرداخت کافی نیست.',
                            'amount_toman' => $amountToman,
                            'shortfall_toman' => max(0, $amountToman - $this->lockedWalletBalanceToman($customer)),
                            'needs_topup' => true,
                        ]
                        : ['ok' => false, 'message' => $msg];
                }

                if ($dedup) {
                    return $this->replayInstallmentWalletResponse($wtx);
                }

                $note = 'پرداخت از کیف پول — تراکنش کیف '.$wtx->id;
                $payment = CustomerLoanInstallmentPayment::query()->create([
                    'customer_loan_installment_id' => (int) $installment->id,
                    'payment_method' => CustomerLoanInstallmentPayment::METHOD_WALLET,
                    'amount_toman' => $amountToman,
                    'reference_due_date' => null,
                    'deposited_at' => Carbon::now()->startOfDay()->format('Y-m-d'),
                    'note' => $note,
                    'recorded_by_admin_id' => null,
                ]);

                $installment->refresh();
                $this->syncer->syncFromPaymentRows($installment);

                PortalAdminSmsDispatcher::afterInstallmentPayment($payment);

                $file->loadMissing('loanType');

                $meta = array_merge($metaBase, ['installment_payment_trail' => $note]);
                $wtx->meta = $meta;
                $wtx->save();

                $this->ledger->syncFromWalletInstallmentPayment($wtx, $installment, $file, $amountToman);

                return [
                    'ok' => true,
                    'message' => 'پرداخت قسط از کیف پول با موفقیت ثبت شد.',
                    'amount_toman' => $amountToman,
                    'track_id' => (string) (int) $wtx->id,
                    'bank_ref' => 'wtx-'.(string) (int) $wtx->id,
                ];
            });
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'خطای غیرمنتظره هنگام پرداخت؛ لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'];
        }
    }

    /**
     * @return array{ok: bool, message: string, amount_toman?: int, shortfall_toman?: int, needs_topup?: bool, replay?: bool}
     */
    public function payFullSettlementFromWallet(Customer $customer, int $loanFileId, string $idempotencyKey): array
    {
        $key = $this->normalizeIdempotencyKey($idempotencyKey);
        if ($key === null) {
            return ['ok' => false, 'message' => 'شناسهٔ یکتای درخواست نامعتبر است؛ صفحه را تازه کنید و دوباره تلاش کنید.'];
        }

        $requestUuid = Str::lower($key);

        try {
            return DB::transaction(function () use ($customer, $loanFileId, $requestUuid): array {
                $existing = CustomerWalletTransaction::query()
                    ->where('customer_id', (int) $customer->id)
                    ->where('request_uuid', $requestUuid)
                    ->first();
                if ($existing !== null) {
                    return $this->replayFullSettlementWalletResponse($existing);
                }

                $file = CustomerLoanFile::query()
                    ->where('customer_id', (int) $customer->id)
                    ->whereKey($loanFileId)
                    ->lockForUpdate()
                    ->first();

                if ($file === null) {
                    return ['ok' => false, 'message' => 'پروندهٔ وام یافت نشد.'];
                }

                $quote = $this->portalPresenter->fullSettlementOnlinePaymentQuote($file);
                if ($quote === null) {
                    return ['ok' => false, 'message' => 'تسویهٔ کلی برای این پرونده در دسترس نیست.'];
                }

                $amountToman = (int) $quote['amount_toman'];
                $principalToman = (int) $quote['principal_toman'];
                if ($amountToman < 1) {
                    return ['ok' => false, 'message' => 'مبلغ تسویه نامعتبر است.'];
                }

                $balance = $this->lockedWalletBalanceToman($customer);
                if ($balance < $amountToman) {
                    return [
                        'ok' => false,
                        'message' => 'موجودی کیف پول برای تسویهٔ کافی نیست.',
                        'amount_toman' => $amountToman,
                        'shortfall_toman' => $amountToman - $balance,
                        'needs_topup' => true,
                    ];
                }

                $metaBase = [
                    'purpose' => 'loan_full_settlement_wallet',
                    'loan_file_id' => (int) $file->id,
                    'principal_toman' => $principalToman,
                    'late_fee_toman' => (int) $quote['late_fee_toman'],
                ];

                $desc = 'تسویهٔ کلی بدهی — پرونده '.(string) $file->loan_code;
                try {
                    [, $wtx, $dedup] = $this->walletService->adjust(
                        $customer,
                        CustomerWalletTransaction::DIRECTION_WITHDRAW,
                        $amountToman,
                        $desc,
                        null,
                        (string) (request()->ip() ?? ''),
                        (string) (request()->userAgent() ?? ''),
                        $requestUuid,
                        $metaBase
                    );
                } catch (\RuntimeException $e) {
                    $msg = $e->getMessage();

                    return str_contains($msg, 'کافی نیست')
                        ? [
                            'ok' => false,
                            'message' => 'موجودی کیف پول برای تسویهٔ کافی نیست.',
                            'amount_toman' => $amountToman,
                            'shortfall_toman' => max(0, $amountToman - $this->lockedWalletBalanceToman($customer)),
                            'needs_topup' => true,
                        ]
                        : ['ok' => false, 'message' => $msg];
                }

                if ($dedup) {
                    return $this->replayFullSettlementWalletResponse($wtx);
                }

                $quote2 = $this->portalPresenter->fullSettlementOnlinePaymentQuote($file);
                if ($quote2 === null
                    || (int) $quote2['amount_toman'] !== $amountToman
                    || (int) $quote2['principal_toman'] !== $principalToman) {
                    throw new \RuntimeException('CONCURRENT_QUOTE_MISMATCH');
                }

                $ref = 'wtx-'.(int) $wtx->id;
                try {
                    $this->fullSettlementAllocator->allocatePrincipalAcrossInstallments(
                        $file,
                        $principalToman,
                        $ref,
                        CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET,
                    );
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('ALLOC_FAIL:'.$e->getMessage(), 0, $e);
                }

                $file->refresh();
                $file->is_settled = true;
                $file->settled_at = Carbon::now()->startOfDay();
                $file->save();

                $meta = array_merge($metaBase, ['wallet_tx_id' => (int) $wtx->id]);
                $wtx->meta = $meta;
                $wtx->save();

                $this->ledger->syncFromWalletFullSettlementPayment($wtx, $file, $quote, $amountToman);

                PortalAdminSmsDispatcher::afterFullSettlement(
                    (int) $customer->id,
                    (int) $file->id,
                    $amountToman,
                    CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET,
                );

                return [
                    'ok' => true,
                    'message' => 'تسویهٔ کلی بدهی از کیف پول با موفقیت انجام شد و پرونده تسویه‌شده ثبت گردید.',
                    'amount_toman' => $amountToman,
                ];
            });
        } catch (Throwable $e) {
            if ($e->getMessage() === 'CONCURRENT_QUOTE_MISMATCH') {
                return ['ok' => false, 'message' => 'وضعیت وام هنگام پرداخت تغییر کرد؛ لطفاً صفحه را تازه کنید.'];
            }
            if (str_starts_with($e->getMessage(), 'ALLOC_FAIL:')) {
                return ['ok' => false, 'message' => 'ثبت تسویه با جدول اقساط هم‌خوان نشد؛ با پشتیبانی تماس بگیرید.'];
            }

            return ['ok' => false, 'message' => 'خطای غیرمنتظره هنگام تسویه؛ لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'];
        }
    }

    /**
     * @return array{ok: bool, message: string, amount_toman?: int, shortfall_toman?: int, needs_topup?: bool, replay?: bool}
     */
    public function payAllFullSettlementsFromWallet(Customer $customer, string $idempotencyKey): array
    {
        $key = $this->normalizeIdempotencyKey($idempotencyKey);
        if ($key === null) {
            return ['ok' => false, 'message' => 'شناسهٔ یکتای درخواست نامعتبر است؛ صفحه را تازه کنید و دوباره تلاش کنید.'];
        }

        $requestUuid = Str::lower($key);

        try {
            return DB::transaction(function () use ($customer, $requestUuid): array {
                $existing = CustomerWalletTransaction::query()
                    ->where('customer_id', (int) $customer->id)
                    ->where('request_uuid', $requestUuid)
                    ->first();
                if ($existing !== null) {
                    return $this->replayFullSettlementWalletResponse($existing);
                }

                $quote = $this->portalPresenter->fullSettlementQuoteForAllOpenFiles($customer);
                if ($quote === null) {
                    return ['ok' => false, 'message' => 'تسویهٔ کلی برای پرونده‌های باز در دسترس نیست.'];
                }

                $amountToman = (int) $quote['amount_toman'];
                $principalToman = (int) $quote['principal_toman'];
                $lateFeeToman = (int) $quote['late_fee_toman'];
                if ($amountToman < 1) {
                    return ['ok' => false, 'message' => 'مبلغ تسویه نامعتبر است.'];
                }

                $fileIds = array_map(
                    static fn (array $item): int => (int) $item['loan_file_id'],
                    $quote['files']
                );

                $balance = $this->lockedWalletBalanceToman($customer);
                if ($balance < $amountToman) {
                    return [
                        'ok' => false,
                        'message' => 'موجودی کیف پول برای تسویهٔ کلی کافی نیست.',
                        'amount_toman' => $amountToman,
                        'shortfall_toman' => $amountToman - $balance,
                        'needs_topup' => true,
                    ];
                }

                $metaBase = [
                    'purpose' => 'loan_full_settlement_wallet_all',
                    'file_ids' => $fileIds,
                    'principal_toman' => $principalToman,
                    'late_fee_toman' => $lateFeeToman,
                    'total_amount_toman' => $amountToman,
                    'files_count' => (int) $quote['files_count'],
                ];

                $desc = 'تسویهٔ کلی همهٔ پرونده‌های باز — '.(int) $quote['files_count'].' پرونده';
                try {
                    [, $wtx, $dedup] = $this->walletService->adjust(
                        $customer,
                        CustomerWalletTransaction::DIRECTION_WITHDRAW,
                        $amountToman,
                        $desc,
                        null,
                        (string) (request()->ip() ?? ''),
                        (string) (request()->userAgent() ?? ''),
                        $requestUuid,
                        $metaBase
                    );
                } catch (\RuntimeException $e) {
                    $msg = $e->getMessage();

                    return str_contains($msg, 'کافی نیست')
                        ? [
                            'ok' => false,
                            'message' => 'موجودی کیف پول برای تسویهٔ کلی کافی نیست.',
                            'amount_toman' => $amountToman,
                            'shortfall_toman' => max(0, $amountToman - $this->lockedWalletBalanceToman($customer)),
                            'needs_topup' => true,
                        ]
                        : ['ok' => false, 'message' => $msg];
                }

                if ($dedup) {
                    return $this->replayFullSettlementWalletResponse($wtx);
                }

                $ref = 'wtx-'.(int) $wtx->id;

                foreach ($quote['files'] as $item) {
                    $fileId = (int) $item['loan_file_id'];
                    $expectedPrincipal = (int) $item['principal_toman'];
                    $expectedLateFee = (int) $item['late_fee_toman'];
                    $expectedAmount = (int) $item['amount_toman'];

                    $file = CustomerLoanFile::query()
                        ->where('customer_id', (int) $customer->id)
                        ->whereKey($fileId)
                        ->lockForUpdate()
                        ->first();

                    if ($file === null) {
                        throw new \RuntimeException('CONCURRENT_QUOTE_MISMATCH');
                    }

                    $quote2 = $this->portalPresenter->fullSettlementOnlinePaymentQuote($file);
                    if ($quote2 === null
                        || (int) $quote2['amount_toman'] !== $expectedAmount
                        || (int) $quote2['principal_toman'] !== $expectedPrincipal
                        || (int) $quote2['late_fee_toman'] !== $expectedLateFee) {
                        throw new \RuntimeException('CONCURRENT_QUOTE_MISMATCH');
                    }

                    try {
                        $this->fullSettlementAllocator->allocatePrincipalAcrossInstallments(
                            $file,
                            $expectedPrincipal,
                            $ref,
                            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET,
                        );
                    } catch (\RuntimeException $e) {
                        throw new \RuntimeException('ALLOC_FAIL:'.$e->getMessage(), 0, $e);
                    }

                    $file->refresh();
                    $file->is_settled = true;
                    $file->settled_at = Carbon::now()->startOfDay();
                    $file->save();

                    $this->ledger->syncFromWalletFullSettlementBatchFilePayment($wtx, $file, $quote2, $expectedAmount);

                    PortalAdminSmsDispatcher::afterFullSettlement(
                        (int) $customer->id,
                        (int) $file->id,
                        $expectedAmount,
                        CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET,
                    );
                }

                $meta = array_merge($metaBase, ['wallet_tx_id' => (int) $wtx->id]);
                $wtx->meta = $meta;
                $wtx->save();

                return [
                    'ok' => true,
                    'message' => 'تسویهٔ کلی همهٔ پرونده‌های باز از کیف پول با موفقیت انجام شد.',
                    'amount_toman' => $amountToman,
                ];
            });
        } catch (Throwable $e) {
            if ($e->getMessage() === 'CONCURRENT_QUOTE_MISMATCH') {
                return ['ok' => false, 'message' => 'وضعیت وام هنگام پرداخت تغییر کرد؛ لطفاً صفحه را تازه کنید.'];
            }
            if (str_starts_with($e->getMessage(), 'ALLOC_FAIL:')) {
                return ['ok' => false, 'message' => 'ثبت تسویه با جدول اقساط هم‌خوان نیست؛ با پشتیبانی تماس بگیرید.'];
            }

            return ['ok' => false, 'message' => 'خطای غیرمنتظره هنگام تسویه؛ لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'];
        }
    }

    private function lockedWalletBalanceToman(Customer $customer): int
    {
        $this->walletService->ensureWallet($customer);
        $row = CustomerWallet::query()
            ->where('customer_id', (int) $customer->id)
            ->lockForUpdate()
            ->first();

        return (int) ($row?->balance_toman ?? 0);
    }

    /**
     * @return array{ok: bool, message: string, amount_toman?: int, replay?: bool}
     */
    private function replayInstallmentWalletResponse(CustomerWalletTransaction $tx): array
    {
        $meta = $tx->meta ?? [];
        if (($meta['purpose'] ?? '') !== 'installment_wallet_pay') {
            return ['ok' => false, 'message' => 'این شناسهٔ یکتا برای عملیات دیگری ثبت شده است.'];
        }

        return [
            'ok' => true,
            'message' => 'این پرداخت قبلاً با موفقیت ثبت شده است.',
            'amount_toman' => (int) $tx->amount_toman,
            'track_id' => (string) (int) $tx->id,
            'bank_ref' => 'wtx-'.(string) (int) $tx->id,
            'replay' => true,
        ];
    }

    /**
     * @return array{ok: bool, message: string, amount_toman?: int, replay?: bool}
     */
    private function replayFullSettlementWalletResponse(CustomerWalletTransaction $tx): array
    {
        $meta = $tx->meta ?? [];
        $purpose = (string) ($meta['purpose'] ?? '');
        if (! in_array($purpose, ['loan_full_settlement_wallet', 'loan_full_settlement_wallet_all'], true)) {
            return ['ok' => false, 'message' => 'این شناسهٔ یکتا برای عملیات دیگری ثبت شده است.'];
        }

        return [
            'ok' => true,
            'message' => $purpose === 'loan_full_settlement_wallet_all'
                ? 'تسویهٔ کلی همهٔ پرونده‌ها از کیف پول قبلاً برای این درخواست ثبت شده است.'
                : 'تسویهٔ کلی از کیف پول قبلاً برای این درخواست ثبت شده است.',
            'amount_toman' => (int) $tx->amount_toman,
            'replay' => true,
        ];
    }

    private function normalizeIdempotencyKey(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '' || ! preg_match(self::UUID_V4, $t)) {
            return null;
        }

        return Str::lower($t);
    }
}
