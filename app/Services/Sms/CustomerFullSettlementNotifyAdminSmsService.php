<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Concerns\SendsAdminRecipientSms;
use Hekmatinasser\Jalali\Jalali;

final class CustomerFullSettlementNotifyAdminSmsService
{
    use SendsAdminRecipientSms;

    public const TYPE = 'customer-full-settlement-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnSettlement(
        int $customerId,
        int $loanFileId,
        int $amountToman,
        ?string $paymentMethod = null,
    ): void {
        $file = CustomerLoanFile::query()
            ->with('customer')
            ->find($loanFileId);
        $customer = $file?->customer;
        if ($file === null || $customer === null || (int) $customer->id !== $customerId) {
            return;
        }

        $settings = $this->smsSettings->customerFullSettlementNotifyAdminSettings();
        $message = $this->renderMessage(
            trim($settings['message_template']),
            $customer,
            $file,
            $amountToman,
            $paymentMethod,
        );
        if ($message === '') {
            return;
        }

        $this->sendAdminRecipientSms(
            $this->smsSettings,
            $this->rawSms,
            $settings,
            $message,
            self::TYPE,
            [
                'customer_id' => $customerId,
                'loan_file_id' => $loanFileId,
                'amount_toman' => $amountToman,
                'payment_method' => $paymentMethod,
            ],
            'customer_full_settlement_notify_admin_sms',
        );
    }

    public function renderMessage(
        string $template,
        Customer $customer,
        CustomerLoanFile $file,
        int $amountToman,
        ?string $paymentMethod = null,
    ): string {
        if ($template === '') {
            return '';
        }

        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));
        $loanCode = trim((string) ($file->loan_code ?? ''));
        $paymentMethodFa = $this->paymentMethodLabelFa((string) ($paymentMethod ?? ''));

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_username}' => $username,
            '{loan_number}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{loan_code}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{settlement_amount}' => $this->smsFormatToman($amountToman),
            '{settlement_amount_toman}' => $this->smsFormatToman($amountToman),
            '{payment_method}' => $paymentMethodFa,
            '{app_name}' => $this->smsAppDisplayName(),
        ];

        $text = $this->smsNormalizeText(str_replace(array_keys($replacements), array_values($replacements), $template));

        if ($paymentMethodFa !== '' && $paymentMethodFa !== '—' && ! str_contains($template, '{payment_method}')) {
            $text = rtrim($text, " .\u{200c}").' از طریق '.$paymentMethodFa.'.';
        }

        return $text;
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} تسویهٔ یکجای وام شماره {loan_number} به مبلغ {settlement_amount} تومان را از طریق {payment_method} پرداخت نمود.';
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
}
