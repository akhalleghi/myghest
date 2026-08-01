<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanGuarantee;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Support\IranMobile;
use Illuminate\Support\Facades\Log;

/**
 * ارسال خودکار پیامک تشکر از پرداخت قسط به مشتری پس از پرداخت از پنل کاربر.
 */
final class CustomerInstallmentPaymentThanksSmsService
{
    public const TYPE = 'installment-thanks';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly SmsTemplateRenderer $templateRenderer,
        private readonly RawSmsDispatcher $rawSms,
        private readonly LoanFileFinanceCalculator $financeCalculator,
        private readonly LoanInstallmentPaidAmountSyncer $paidAmountSyncer,
        private readonly CustomerInstallmentPaymentNotifyAdminSmsService $portalPaymentGuard,
    ) {}

    public function sendThanksOnPortalPayment(int $paymentId): void
    {
        $payment = CustomerLoanInstallmentPayment::query()
            ->with(['installment.loanFile.customer', 'installment.loanFile.loanType', 'installment.loanFile.installments'])
            ->find($paymentId);

        if ($payment === null || ! $this->portalPaymentGuard->isPortalInstallmentPayment($payment)) {
            return;
        }

        /** @var CustomerLoanInstallment|null $installment */
        $installment = $payment->installment;
        /** @var CustomerLoanFile|null $loanFile */
        $loanFile = $installment?->loanFile;
        /** @var Customer|null $customer */
        $customer = $loanFile?->customer;

        if ($installment === null || $loanFile === null || $customer === null) {
            Log::warning('customer_installment_payment_thanks_missing_relations', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        if (! $customer->receivesOutboundSms()) {
            return;
        }

        $mobile = IranMobile::normalize((string) ($customer->mobile ?? ''));
        if ($mobile === null) {
            Log::warning('customer_installment_payment_thanks_invalid_mobile', [
                'payment_id' => $paymentId,
                'customer_id' => (int) $customer->id,
            ]);

            return;
        }

        $template = $this->resolveThanksTemplate();
        if ($template === null) {
            Log::warning('customer_installment_payment_thanks_missing_template', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        // اطمینان از هم‌خوانی مبلغ پرداخت‌شدهٔ قسط قبل از ساخت متغیرهای قالب
        $this->paidAmountSyncer->syncFromPaymentRows($installment);
        $installment->refresh();
        $loanFile->refresh();
        $loanFile->load(['loanType', 'installments']);
        $customer->unsetRelation('loanFiles');

        $body = trim($this->templateRenderer->render(
            (string) $template->body,
            $this->buildTemplateVars($customer, $loanFile, $installment, (int) $payment->amount_toman)
        ));
        if ($body === '') {
            return;
        }

        try {
            $result = $this->rawSms->send($mobile, $body, self::TYPE, [
                'payment_id' => $paymentId,
                'customer_id' => (int) $customer->id,
                'installment_id' => (int) $installment->id,
                'loan_file_id' => (int) $loanFile->id,
                'template_id' => (int) $template->id,
                'automated' => true,
                'portal' => true,
            ]);
            if (! ($result['ok'] ?? false)) {
                Log::warning('customer_installment_payment_thanks_undelivered', [
                    'payment_id' => $paymentId,
                    'customer_id' => (int) $customer->id,
                    'message' => $result['message'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('customer_installment_payment_thanks_failed', [
                'payment_id' => $paymentId,
                'customer_id' => (int) $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveThanksTemplate(): ?SmsTemplate
    {
        $scenarioIds = $this->smsSettings->scenarioTemplateIds();
        $configuredId = $this->parsePositiveInt($scenarioIds['tpl_installment_thanks_id'] ?? '');
        if ($configuredId !== null) {
            $configured = SmsTemplate::query()->find($configuredId);
            if ($configured !== null) {
                return $configured;
            }
        }

        return SmsTemplate::query()
            ->where('template_key', 'default_installment_payment_thanks')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function buildTemplateVars(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
        int $paidAmountToman,
    ): array {
        $remainingLoan = $loanFile->is_settled
            ? 0
            : $this->financeCalculator->installmentPaymentCeilingToman($loanFile);

        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'installment_number' => (string) $installment->sequence,
            'paid_amount' => $this->formatToman($paidAmountToman),
            'remaining_loan' => $this->formatToman($remainingLoan),
            'purchase_credit' => $this->formatToman($this->purchaseCreditAvailableToman($customer)),
        ];
    }

    /**
     * اعتبار خرید باقیمانده = سقف مؤثر (دستی یا ۷۰٪ مبلغ ضمانت چک/سفته) منهای مانده اقساط.
     */
    private function purchaseCreditAvailableToman(Customer $customer): int
    {
        $customer->loadMissing(['loanFiles.loanType', 'loanFiles.installments']);

        $used = 0;
        foreach ($customer->loanFiles as $file) {
            if ($file->revoked_at !== null || $file->is_settled) {
                continue;
            }
            $remaining = $this->financeCalculator->installmentPaymentCeilingToman($file);
            if ($remaining > 0) {
                $used += $remaining;
            }
        }

        $ceilingStored = max(0, (int) ($customer->purchase_credit_ceiling_toman ?? 0));
        $chequeTotal = 0;
        $guarantees = CustomerLoanGuarantee::query()
            ->where('customer_id', (int) $customer->id)
            ->whereIn('type', [
                CustomerLoanGuarantee::TYPE_CHEQUE,
                CustomerLoanGuarantee::TYPE_OTHER,
            ])
            ->get(['id', 'type', 'meta']);

        foreach ($guarantees as $guarantee) {
            if ($guarantee->isMarkedReturned()) {
                continue;
            }
            $meta = is_array($guarantee->meta) ? $guarantee->meta : [];
            $amt = max(0, (int) ($meta['amount_toman'] ?? 0));
            if ($amt > 0) {
                $chequeTotal += $amt;
            }
        }

        $suggested = (int) floor($chequeTotal * 0.7);
        $ceilingEffective = ($ceilingStored < 1 && $suggested > 0) ? $suggested : $ceilingStored;

        return max(0, $ceilingEffective - $used);
    }

    private function parsePositiveInt(null|string|int $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    private function formatToman(int $amount): string
    {
        return number_format(max(0, $amount), 0, '.', ',').' تومان';
    }

    private function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : (string) config('app.name');
    }
}
