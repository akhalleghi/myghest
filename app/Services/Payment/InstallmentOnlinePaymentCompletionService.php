<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class InstallmentOnlinePaymentCompletionService
{
    public function __construct(
        private readonly ZibalIpgClient $zibal,
        private readonly LoanInstallmentPaidAmountSyncer $syncer,
        private readonly CustomerTransactionLedgerService $ledger,
    ) {}

    public function completeZibalReturn(int $trackId, bool $gatewayReportsSuccess): RedirectResponse
    {
        if ($trackId <= 0) {
            return $this->failRedirect('اطلاعات بازگشت از درگاه ناقص است.', null, null);
        }

        $merchantRaw = AppSetting::query()->where('key', 'zibal_merchant')->value('value');
        $merchant = is_string($merchantRaw) ? trim($merchantRaw) : '';
        if ($merchant === '') {
            return $this->failRedirect('درگاه پرداخت در سامانه پیکربندی نشده است.', $trackId, null);
        }

        if (! $gatewayReportsSuccess) {
            $this->markIntentFailedByTrackId($trackId, 'کاربر از ادامهٔ پرداخت انصراف داد یا تراکنش ناموفق بود.');

            return $this->failRedirect('پرداخت تکمیل نشد یا توسط شما لغو شد.', $trackId, null);
        }

        try {
            return DB::transaction(function () use ($trackId, $merchant): RedirectResponse {
                /** @var CustomerLoanInstallmentOnlinePaymentIntent|null $intent */
                $intent = CustomerLoanInstallmentOnlinePaymentIntent::query()
                    ->where('track_id', $trackId)
                    ->lockForUpdate()
                    ->first();

                if ($intent === null) {
                    return $this->failRedirect('شناسهٔ تراکنش شناخته نشد.', $trackId, null);
                }

                if ($intent->status === CustomerLoanInstallmentOnlinePaymentIntent::STATUS_COMPLETED) {
                    $bank = $intent->zibal_ref_number !== null && trim((string) $intent->zibal_ref_number) !== ''
                        ? trim((string) $intent->zibal_ref_number)
                        : null;
                    $this->ledger->syncFromInstallmentIntent($intent);

                    return $this->okRedirect('پرداخت قبلاً با موفقیت ثبت شده است.', $trackId, $bank);
                }

                $installment = $intent->installment()->lockForUpdate()->first();
                if ($installment === null) {
                    $intent->update([
                        'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                        'failure_reason' => 'قسط حذف شده است.',
                    ]);
                    $this->ledger->syncFromInstallmentIntent($intent->fresh());

                    return $this->failRedirect('قسط مرتبط با این پرداخت دیگر وجود ندارد.', $trackId, null);
                }

                $verify = $this->zibal->verify($merchant, $trackId);
                if (! $verify['ok']) {
                    $intent->update([
                        'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                        'failure_reason' => $verify['message'],
                    ]);
                    $this->ledger->syncFromInstallmentIntent($intent->fresh());

                    return $this->failRedirect('تأیید پرداخت توسط درگاه انجام نشد: '.$verify['message'], $trackId, null);
                }

                $paidRial = (int) $verify['amount_rial'];
                if ($paidRial !== (int) $intent->expected_amount_rial) {
                    $intent->update([
                        'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                        'failure_reason' => 'مبلغ تأییدشده با مبلغ درخواست هم‌خوانی ندارد.',
                    ]);
                    $this->ledger->syncFromInstallmentIntent($intent->fresh());

                    return $this->failRedirect('مبلغ تأییدشده با سفارش هم‌خوانی ندارد؛ با پشتیبانی تماس بگیرید.', $trackId, null);
                }

                $paidToman = intdiv($paidRial, 10);
                if ($paidToman < 1 || $paidToman !== (int) $intent->expected_amount_toman) {
                    $intent->update([
                        'status' => CustomerLoanInstallmentOnlinePaymentIntent::STATUS_FAILED,
                        'failure_reason' => 'تبدیل مبلغ نامعتبر است.',
                    ]);
                    $this->ledger->syncFromInstallmentIntent($intent->fresh());

                    return $this->failRedirect('خطا در پردازش مبلغ پرداخت.', $trackId, null);
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

                return $this->okRedirect(
                    'پرداخت آنلاین با موفقیت ثبت شد.',
                    $trackId,
                    $ref !== '' ? $ref : null
                );
            });
        } catch (\Throwable) {
            return $this->failRedirect(
                'خطای داخلی هنگام ثبت پرداخت؛ لطفاً بعداً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',
                $trackId,
                null
            );
        }
    }

    private function markIntentFailedByTrackId(int $trackId, string $reason): void
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

    /**
     * @return array{success: bool, message: string, track_id: ?string, bank_ref: ?string}
     */
    private function payResultPayload(bool $success, string $message, ?int $trackId, ?string $bankRef): array
    {
        $trackStr = $trackId !== null && $trackId > 0 ? (string) $trackId : null;
        $bankTrim = $bankRef !== null && trim($bankRef) !== '' ? trim($bankRef) : null;

        return [
            'success' => $success,
            'message' => $message,
            'track_id' => $trackStr,
            'bank_ref' => $bankTrim,
        ];
    }

    private function failRedirect(string $message, ?int $trackId, ?string $bankRef): RedirectResponse
    {
        return redirect()
            ->route('user.loans.index')
            ->with('portal_pay_result', $this->payResultPayload(false, $message, $trackId, $bankRef));
    }

    private function okRedirect(string $message, int $trackId, ?string $bankRef): RedirectResponse
    {
        return redirect()
            ->route('user.loans.index')
            ->with('portal_pay_result', $this->payResultPayload(true, $message, $trackId, $bankRef));
    }
}
