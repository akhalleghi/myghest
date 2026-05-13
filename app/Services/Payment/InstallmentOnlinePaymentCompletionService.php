<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletOnlinePaymentIntent;
use App\Models\CustomerWalletTransaction;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\LoanFullSettlementOnlinePrincipalAllocator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Wallet\CustomerWalletService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class InstallmentOnlinePaymentCompletionService
{
    /** مسیرهای مجاز برای بازگشت پس از درگاه (جلوگیری از بازگشت به نام‌مسیرهای ناشناس). */
    private const ALLOWED_PORTAL_PAY_RETURN_ROUTES = [
        'user.loans.index',
        'user.dashboard',
    ];

    public function __construct(
        private readonly ZibalIpgClient $zibal,
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
        private readonly CustomerTransactionLedgerService $ledger,
        private readonly CustomerWalletService $walletService,
        private readonly CustomerLoanPortalPresenter $portalPresenter,
        private readonly LoanFullSettlementOnlinePrincipalAllocator $fullSettlementAllocator,
        private readonly InstallmentOnlinePaymentResolver $installmentResolver,
    ) {}

    public function completeZibalReturn(int $trackId, bool $gatewayReportsSuccess): RedirectResponse
    {
        if ($trackId <= 0) {
            return $this->redirectPortalPay($this->payResultPayload(false, 'اطلاعات بازگشت از درگاه ناقص است.', null, null));
        }

        $merchantRaw = AppSetting::query()->where('key', 'zibal_merchant')->value('value');
        $merchant = is_string($merchantRaw) ? trim($merchantRaw) : '';
        if ($merchant === '') {
            return $this->redirectPortalPay($this->payResultPayload(false, 'درگاه پرداخت در سامانه پیکربندی نشده است.', $trackId, null));
        }

        if (! $gatewayReportsSuccess) {
            $this->markInstallmentIntentsFailedByTrackId($trackId, 'کاربر از ادامهٔ پرداخت انصراف داد یا تراکنش ناموفق بود.');
            $this->markWalletIntentsFailedByTrackId($trackId, 'کاربر از ادامهٔ پرداخت انصراف داد یا تراکنش ناموفق بود.');
            $this->markFullSettlementIntentsFailedByTrackId($trackId, 'کاربر از ادامهٔ پرداخت انصراف داد یا تراکنش ناموفق بود.');

            return $this->redirectPortalPay($this->payResultPayload(false, 'پرداخت تکمیل نشد یا توسط شما لغو شد.', $trackId, null));
        }

        try {
            return DB::transaction(function () use ($trackId, $merchant): RedirectResponse {
                /** @var CustomerLoanInstallmentOnlinePaymentIntent|null $installmentIntent */
                $installmentIntent = CustomerLoanInstallmentOnlinePaymentIntent::query()
                    ->where('track_id', $trackId)
                    ->lockForUpdate()
                    ->first();

                if ($installmentIntent !== null) {
                    return $this->finalizeInstallmentIntent($installmentIntent, $merchant, $trackId);
                }

                /** @var CustomerWalletOnlinePaymentIntent|null $walletIntent */
                $walletIntent = CustomerWalletOnlinePaymentIntent::query()
                    ->where('track_id', $trackId)
                    ->lockForUpdate()
                    ->first();

                if ($walletIntent !== null) {
                    return $this->finalizeWalletTopupIntent($walletIntent, $merchant, $trackId);
                }

                /** @var CustomerLoanFullSettlementOnlinePaymentIntent|null $fullSettlementIntent */
                $fullSettlementIntent = CustomerLoanFullSettlementOnlinePaymentIntent::query()
                    ->where('track_id', $trackId)
                    ->lockForUpdate()
                    ->first();

                if ($fullSettlementIntent !== null) {
                    return $this->finalizeLoanFullSettlementIntent($fullSettlementIntent, $merchant, $trackId);
                }

                return $this->redirectPortalPay($this->payResultPayload(false, 'شناسهٔ تراکنش شناخته نشد.', $trackId, null));
            });
        } catch (\Throwable) {
            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'خطای داخلی هنگام ثبت پرداخت؛ لطفاً بعداً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',
                $trackId,
                null
            ));
        }
    }

    private function finalizeInstallmentIntent(
        CustomerLoanInstallmentOnlinePaymentIntent $intent,
        string $merchant,
        int $trackId
    ): RedirectResponse {
        if ($intent->status === CustomerLoanInstallmentOnlinePaymentIntent::STATUS_COMPLETED) {
            $bank = $intent->zibal_ref_number !== null && trim((string) $intent->zibal_ref_number) !== ''
                ? trim((string) $intent->zibal_ref_number)
                : null;
            $this->ledger->syncFromInstallmentIntent($intent);

            return $this->redirectPortalPay($this->payResultPayload(
                true,
                'پرداخت قبلاً با موفقیت ثبت شده است.',
                $trackId,
                $bank,
                (int) $intent->expected_amount_toman
            ));
        }

        $installment = $intent->installment()->lockForUpdate()->first();
        if ($installment === null) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'قسط حذف شده است.',
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'قسط مرتبط با این پرداخت دیگر وجود ندارد.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $verify = $this->zibal->verify($merchant, $trackId);
        if (! $verify['ok']) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $verify['message'],
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'تأیید پرداخت توسط درگاه انجام نشد: '.$verify['message'],
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidRial = (int) $verify['amount_rial'];
        if ($paidRial !== (int) $intent->expected_amount_rial) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'مبلغ تأییدشده با مبلغ درخواست هم‌خوانی ندارد.',
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'مبلغ تأییدشده با سفارش هم‌خوانی ندارد؛ با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidToman = intdiv($paidRial, 10);
        if ($paidToman < 1 || $paidToman !== (int) $intent->expected_amount_toman) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'تبدیل مبلغ نامعتبر است.',
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'خطا در پردازش مبلغ پرداخت.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $customer = Customer::query()->whereKey((int) $intent->customer_id)->first();
        if ($customer === null) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'حساب مشتری برای این پرداخت یافت نشد.',
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'حساب کاربری برای این پرداخت یافت نشد.',
                $trackId,
                null,
                $paidToman
            ));
        }

        $resolved = $this->installmentResolver->resolveForCustomer($customer, (int) $installment->id);
        if (! ($resolved['ok'] ?? false) || (int) ($resolved['amount_toman'] ?? 0) !== $paidToman) {
            $intent->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'وضعیت قسط پس از شروع پرداخت تغییر کرده است.',
            ]);
            $this->ledger->syncFromInstallmentIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'وضعیت قسط پس از شروع پرداخت تغییر کرده است. در صورت کسر مبلغ از حساب، با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                $paidToman
            ));
        }

        $ref = trim((string) $verify['ref_number']);
        $note = 'پرداخت آنلاین (زیبال)'.($ref !== '' ? ' — مرجع: '.$ref : '');

        CustomerLoanInstallmentPayment::query()->create([
            'customer_loan_installment_id' => (int) $installment->id,
            'payment_method' => CustomerLoanInstallmentPayment::METHOD_ONLINE,
            'amount_toman' => $paidToman,
            'reference_due_date' => null,
            'deposited_at' => Carbon::now()->startOfDay()->format('Y-m-d'),
            'note' => $note,
            'recorded_by_admin_id' => null,
        ]);

        $installment->refresh();
        $this->syncer->syncFromPaymentRows($installment);

        $intent->update([
            'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_COMPLETED,
            'zibal_ref_number' => $ref !== '' ? mb_substr($ref, 0, 64) : null,
            'failure_reason' => null,
        ]);
        $this->ledger->syncFromInstallmentIntent($intent->fresh());

        return $this->redirectPortalPay($this->payResultPayload(
            true,
            'پرداخت آنلاین با موفقیت ثبت شد.',
            $trackId,
            $ref !== '' ? $ref : null,
            $paidToman
        ));
    }

    private function finalizeWalletTopupIntent(
        CustomerWalletOnlinePaymentIntent $intent,
        string $merchant,
        int $trackId
    ): RedirectResponse {
        if ($intent->status === CustomerWalletOnlinePaymentIntent::STATUS_COMPLETED) {
            $bank = $intent->zibal_ref_number !== null && trim((string) $intent->zibal_ref_number) !== ''
                ? trim((string) $intent->zibal_ref_number)
                : null;
            $this->ledger->syncFromWalletTopupIntent($intent);

            return $this->redirectPortalPay($this->payResultPayload(
                true,
                'شارژ کیف پول قبلاً با موفقیت ثبت شده است.',
                $trackId,
                $bank,
                (int) $intent->expected_amount_toman
            ));
        }

        $customer = Customer::query()->whereKey((int) $intent->customer_id)->lockForUpdate()->first();
        if ($customer === null) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'حساب مشتری یافت نشد.',
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'حساب کاربری برای این پرداخت یافت نشد.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $wallet = $this->walletService->ensureWallet($customer);
        $wallet = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->first();
        if ($wallet === null || $wallet->is_locked) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'کیف پول قفل است یا در دسترس نیست.',
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'کیف پول شما قفل است؛ امکان شارژ آنلاین وجود ندارد. با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $verify = $this->zibal->verify($merchant, $trackId);
        if (! $verify['ok']) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $verify['message'],
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'تأیید پرداخت توسط درگاه انجام نشد: '.$verify['message'],
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidRial = (int) $verify['amount_rial'];
        if ($paidRial !== (int) $intent->expected_amount_rial) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'مبلغ تأییدشده با مبلغ درخواست هم‌خوانی ندارد.',
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'مبلغ تأییدشده با سفارش هم‌خوانی ندارد؛ با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidToman = intdiv($paidRial, 10);
        if ($paidToman < 1 || $paidToman !== (int) $intent->expected_amount_toman) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'تبدیل مبلغ نامعتبر است.',
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'خطا در پردازش مبلغ پرداخت.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $ref = trim((string) $verify['ref_number']);
        $note = 'شارژ آنلاین کیف پول (زیبال)'.($ref !== '' ? ' — مرجع: '.$ref : '');
        $requestUuid = 'wallet-topup-intent-'.(int) $intent->id;
        $meta = [
            'wallet_topup_intent_id' => (int) $intent->id,
            'track_id' => $trackId,
        ];

        try {
            $this->walletService->adjust(
                $customer,
                CustomerWalletTransaction::DIRECTION_DEPOSIT,
                $paidToman,
                $note,
                null,
                (string) (request()->ip() ?? ''),
                (string) (request()->userAgent() ?? ''),
                $requestUuid,
                $meta
            );
        } catch (\RuntimeException $e) {
            $intent->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            $this->ledger->syncFromWalletTopupIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(false, $e->getMessage(), $trackId, null, $paidToman));
        }

        $intent->update([
            'status' => CustomerWalletOnlinePaymentIntent::STATUS_COMPLETED,
            'zibal_ref_number' => $ref !== '' ? mb_substr($ref, 0, 64) : null,
            'failure_reason' => null,
        ]);
        $this->ledger->syncFromWalletTopupIntent($intent->fresh());

        return $this->redirectPortalPay($this->payResultPayload(
            true,
            'شارژ کیف پول با موفقیت انجام شد.',
            $trackId,
            $ref !== '' ? $ref : null,
            $paidToman
        ));
    }

    private function finalizeLoanFullSettlementIntent(
        CustomerLoanFullSettlementOnlinePaymentIntent $intent,
        string $merchant,
        int $trackId
    ): RedirectResponse {
        if ($intent->status === CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_COMPLETED) {
            $bank = $intent->zibal_ref_number !== null && trim((string) $intent->zibal_ref_number) !== ''
                ? trim((string) $intent->zibal_ref_number)
                : null;
            $this->ledger->syncFromFullSettlementIntent($intent);

            return $this->redirectPortalPay($this->payResultPayload(
                true,
                'تسویهٔ کلی بدهی قبلاً با موفقیت ثبت شده است.',
                $trackId,
                $bank,
                (int) $intent->expected_amount_toman
            ));
        }

        $file = CustomerLoanFile::query()
            ->whereKey((int) $intent->customer_loan_file_id)
            ->where('customer_id', (int) $intent->customer_id)
            ->lockForUpdate()
            ->first();

        if ($file === null) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'پروندهٔ وام یافت نشد.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'پروندهٔ وام برای این پرداخت یافت نشد.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        if ($file->revoked_at !== null) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'قرارداد فسخ شده است.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'این پرونده فسخ شده است؛ امکان تسویهٔ آنلاین وجود ندارد.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        if ($file->is_settled) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'پرونده قبلاً تسویه شده است.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'این پرونده قبلاً تسویه شده است.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $verify = $this->zibal->verify($merchant, $trackId);
        if (! $verify['ok']) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => $verify['message'],
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'تأیید پرداخت توسط درگاه انجام نشد: '.$verify['message'],
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidRial = (int) $verify['amount_rial'];
        if ($paidRial !== (int) $intent->expected_amount_rial) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'مبلغ تأییدشده با مبلغ درخواست هم‌خوانی ندارد.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'مبلغ تأییدشده با سفارش هم‌خوانی ندارد؛ با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $paidToman = intdiv($paidRial, 10);
        if ($paidToman < 1 || $paidToman !== (int) $intent->expected_amount_toman) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'تبدیل مبلغ نامعتبر است.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'خطا در پردازش مبلغ پرداخت.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $quote = $this->portalPresenter->fullSettlementOnlinePaymentQuote($file);
        if ($quote === null
            || (int) $quote['amount_toman'] !== (int) $intent->expected_amount_toman
            || (int) $quote['principal_toman'] !== (int) $intent->principal_component_toman
            || (int) $quote['late_fee_toman'] !== (int) $intent->late_fee_component_toman) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => 'وضعیت پرونده پس از شروع پرداخت تغییر کرده است.',
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                'وضعیت وام پس از شروع پرداخت تغییر کرده است. در صورت کسر مبلغ از حساب، با پشتیبانی تماس بگیرید.',
                $trackId,
                null,
                (int) $intent->expected_amount_toman
            ));
        }

        $ref = trim((string) $verify['ref_number']);

        try {
            $this->fullSettlementAllocator->allocatePrincipalAcrossInstallments(
                $file,
                (int) $quote['principal_toman'],
                $ref
            );
        } catch (\RuntimeException $e) {
            $intent->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            $this->ledger->syncFromFullSettlementIntent($intent->fresh());

            return $this->redirectPortalPay($this->payResultPayload(
                false,
                $e->getMessage(),
                $trackId,
                null,
                $paidToman
            ));
        }

        $file->refresh();
        $file->is_settled = true;
        $file->settled_at = Carbon::now()->startOfDay();
        $file->save();

        $intent->update([
            'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_COMPLETED,
            'zibal_ref_number' => $ref !== '' ? mb_substr($ref, 0, 64) : null,
            'failure_reason' => null,
        ]);
        $this->ledger->syncFromFullSettlementIntent($intent->fresh());

        return $this->redirectPortalPay($this->payResultPayload(
            true,
            'تسویهٔ کلی بدهی با موفقیت انجام شد و پرونده تسویه‌شده ثبت گردید.',
            $trackId,
            $ref !== '' ? $ref : null,
            $paidToman
        ));
    }

    private function markInstallmentIntentsFailedByTrackId(int $trackId, string $reason): void
    {
        $ids = CustomerLoanInstallmentOnlinePaymentIntent::query()
            ->where('track_id', $trackId)
            ->where('status', '!=', CustomerLoanInstallmentOnlinePaymentIntent::STATUS_COMPLETED)
            ->pluck('id');

        CustomerLoanInstallmentOnlinePaymentIntent::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => mb_substr($reason, 0, 2000),
            ]);

        foreach ($ids as $id) {
            $intent = CustomerLoanInstallmentOnlinePaymentIntent::query()->find($id);
            if ($intent !== null) {
                $this->ledger->syncFromInstallmentIntent($intent);
            }
        }
    }

    private function markWalletIntentsFailedByTrackId(int $trackId, string $reason): void
    {
        $ids = CustomerWalletOnlinePaymentIntent::query()
            ->where('track_id', $trackId)
            ->where('status', '!=', CustomerWalletOnlinePaymentIntent::STATUS_COMPLETED)
            ->pluck('id');

        CustomerWalletOnlinePaymentIntent::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => CustomerWalletOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => mb_substr($reason, 0, 2000),
            ]);

        foreach ($ids as $id) {
            $intent = CustomerWalletOnlinePaymentIntent::query()->find($id);
            if ($intent !== null) {
                $this->ledger->syncFromWalletTopupIntent($intent);
            }
        }
    }

    private function markFullSettlementIntentsFailedByTrackId(int $trackId, string $reason): void
    {
        $ids = CustomerLoanFullSettlementOnlinePaymentIntent::query()
            ->where('track_id', $trackId)
            ->where('status', '!=', CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_COMPLETED)
            ->pluck('id');

        CustomerLoanFullSettlementOnlinePaymentIntent::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => CustomerLoanFullSettlementOnlinePaymentIntent::STATUS_FAILED,
                'failure_reason' => mb_substr($reason, 0, 2000),
            ]);

        foreach ($ids as $id) {
            $intent = CustomerLoanFullSettlementOnlinePaymentIntent::query()->find($id);
            if ($intent !== null) {
                $this->ledger->syncFromFullSettlementIntent($intent);
            }
        }
    }

    /**
     * @return array{success: bool, message: string, track_id: ?string, bank_ref: ?string, amount_toman?: int}
     */
    private function payResultPayload(bool $success, string $message, ?int $trackId, ?string $bankRef, ?int $amountToman = null): array
    {
        $trackStr = $trackId !== null && $trackId > 0 ? (string) $trackId : null;
        $bankTrim = $bankRef !== null && trim($bankRef) !== '' ? trim($bankRef) : null;

        $out = [
            'success' => $success,
            'message' => $message,
            'track_id' => $trackStr,
            'bank_ref' => $bankTrim,
        ];
        if ($amountToman !== null && $amountToman > 0) {
            $out['amount_toman'] = $amountToman;
        }

        return $out;
    }

    /**
     * @param  array{success: bool, message: string, track_id: ?string, bank_ref: ?string, amount_toman?: int}  $payload
     */
    private function redirectPortalPay(array $payload): RedirectResponse
    {
        $routeName = session()->pull('portal_pay_return_route');
        if (! is_string($routeName) || ! in_array($routeName, self::ALLOWED_PORTAL_PAY_RETURN_ROUTES, true)) {
            $routeName = 'user.loans.index';
        }

        return redirect()->route($routeName)->with('portal_pay_result', $payload);
    }
}
