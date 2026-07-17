<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\IranMobile;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\Log;

final class CustomerInstallmentPaymentNotifyAdminSmsService
{
    public const TYPE = 'customer-installment-payment-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnPortalPayment(int $paymentId): void
    {
        $payment = CustomerLoanInstallmentPayment::query()
            ->with(['installment.loanFile.customer'])
            ->find($paymentId);

        if ($payment === null || ! $this->isPortalInstallmentPayment($payment)) {
            return;
        }

        $settings = $this->smsSettings->customerInstallmentPaymentNotifyAdminSettings();
        if (! $this->smsSettings->isSettingEnabled($settings['enabled'])) {
            return;
        }

        $recipientIds = array_values(array_filter(
            $settings['recipient_ids'],
            static fn (int $id): bool => $id > 0
        ));
        if ($recipientIds === []) {
            return;
        }

        $template = trim($settings['message_template']);
        if ($template === '') {
            return;
        }

        /** @var CustomerLoanInstallment|null $installment */
        $installment = $payment->installment;
        /** @var CustomerLoanFile|null $file */
        $file = $installment?->loanFile;
        /** @var Customer|null $customer */
        $customer = $file?->customer;

        if ($installment === null || $file === null || $customer === null) {
            Log::warning('customer_installment_payment_notify_admin_missing_relations', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        $message = $this->renderMessage(
            $template,
            $customer,
            $installment,
            $file,
            (int) $payment->amount_toman,
            (string) $payment->payment_method,
        );

        $recipients = Admin::query()
            ->whereIn('id', $recipientIds)
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->get(['id', 'first_name', 'last_name', 'name', 'username', 'mobile']);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $mobile = IranMobile::normalize((string) ($recipient->mobile ?? ''));
            if ($mobile === null) {
                Log::warning('customer_installment_payment_notify_admin_skipped_invalid_mobile', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'payment_id' => $paymentId,
                ]);

                continue;
            }

            try {
                $result = $this->rawSms->send($mobile, $message, self::TYPE, [
                    'payment_id' => $paymentId,
                    'customer_id' => (int) $customer->id,
                    'notify_admin_id' => (int) $recipient->id,
                    'installment_id' => (int) $installment->id,
                    'loan_file_id' => (int) $file->id,
                ]);
                if (! ($result['ok'] ?? false)) {
                    Log::warning('customer_installment_payment_notify_admin_sms_undelivered', [
                        'recipient_admin_id' => (int) $recipient->id,
                        'payment_id' => $paymentId,
                        'message' => $result['message'] ?? '',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('customer_installment_payment_notify_admin_sms_failed', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(350_000);
        }
    }

    public function isPortalInstallmentPayment(CustomerLoanInstallmentPayment $payment): bool
    {
        if ($payment->recorded_by_admin_id !== null) {
            return false;
        }

        return in_array($payment->payment_method, [
            CustomerLoanInstallmentPayment::METHOD_ONLINE,
            CustomerLoanInstallmentPayment::METHOD_WALLET,
        ], true);
    }

    public function renderMessage(
        string $template,
        Customer $customer,
        CustomerLoanInstallment $installment,
        CustomerLoanFile $file,
        int $amountToman,
        string $paymentMethod = '',
    ): string {
        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));
        $loanCode = trim((string) ($file->loan_code ?? ''));
        $seq = max(1, (int) $installment->sequence);
        $amountFormatted = Jalali::enToFaNumbers(number_format(max(0, $amountToman), 0, '.', ','));
        $paymentMethodFa = $this->paymentMethodLabelFa($paymentMethod);

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_first_name}' => trim((string) ($customer->first_name ?? '')),
            '{customer_last_name}' => trim((string) ($customer->last_name ?? '')),
            '{customer_username}' => $username,
            '{installment_number}' => Jalali::enToFaNumbers((string) $seq),
            '{installment_sequence}' => Jalali::enToFaNumbers((string) $seq),
            '{installment_amount}' => $amountFormatted,
            '{installment_amount_toman}' => $amountFormatted,
            '{loan_number}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{loan_code}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{payment_method}' => $paymentMethodFa,
            '{app_name}' => $this->appDisplayName(),
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $template);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        // قالب‌های ذخیره‌شدهٔ قدیمی بدون توکن نحوهٔ پرداخت را هم مشخص نگه می‌داریم.
        if ($paymentMethodFa !== '' && $paymentMethodFa !== '—' && ! str_contains($template, '{payment_method}')) {
            $text = rtrim($text, " .\u{200c}").' از طریق '.$paymentMethodFa.'.';
        }

        return $text;
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} قسط شماره {installment_number} به مبلغ {installment_amount} از وام شماره {loan_number} را از طریق {payment_method} پرداخت نمود.';
    }

    private function paymentMethodLabelFa(string $method): string
    {
        return match ($method) {
            CustomerLoanInstallmentPayment::METHOD_ONLINE => 'درگاه بانکی',
            CustomerLoanInstallmentPayment::METHOD_WALLET => 'کیف پول',
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE => 'تسویه کلی (درگاه)',
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET => 'تسویه کلی (کیف پول)',
            CustomerLoanInstallmentPayment::METHOD_CASH => 'نقدی (فروشگاه)',
            CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER => 'واریز بانکی',
            CustomerLoanInstallmentPayment::METHOD_CARD_TERMINAL => 'کارتخوان',
            default => CustomerLoanInstallmentPayment::methodLabels()[$method] ?? ($method !== '' ? $method : '—'),
        };
    }

    private function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : 'سامانه';
    }
}
