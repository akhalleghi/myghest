<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Concerns\SendsAdminRecipientSms;
use Hekmatinasser\Jalali\Jalali;

final class CustomerDepositDeclarationNotifyAdminSmsService
{
    use SendsAdminRecipientSms;

    public const TYPE = 'customer-deposit-declaration-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnDeclaration(int $declarationId): void
    {
        $declaration = CustomerDepositDeclaration::query()
            ->with(['customer', 'loanFile', 'installment'])
            ->find($declarationId);

        if ($declaration === null || ! $declaration->isPending()) {
            return;
        }

        $customer = $declaration->customer;
        $file = $declaration->loanFile;
        $installment = $declaration->installment;
        if ($customer === null || $file === null || $installment === null) {
            return;
        }

        $settings = $this->smsSettings->customerDepositDeclarationNotifyAdminSettings();
        $message = $this->renderMessage($settings['message_template'], $customer, $file, $installment, $declaration);
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
                'declaration_id' => $declarationId,
                'customer_id' => (int) $customer->id,
            ],
            'customer_deposit_declaration_notify_admin_sms',
        );
    }

    public function renderMessage(
        string $template,
        Customer $customer,
        CustomerLoanFile $file,
        CustomerLoanInstallment $installment,
        CustomerDepositDeclaration $declaration,
    ): string {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));
        $loanCode = trim((string) ($file->loan_code ?? ''));
        $seq = max(1, (int) $installment->sequence);
        $methodLabels = CustomerDepositDeclaration::userPaymentMethodLabelsFa();
        $method = $methodLabels[$declaration->user_payment_method] ?? (string) $declaration->user_payment_method;

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_username}' => $username,
            '{installment_number}' => Jalali::enToFaNumbers((string) $seq),
            '{installment_sequence}' => Jalali::enToFaNumbers((string) $seq),
            '{deposit_amount}' => $this->smsFormatToman((int) $declaration->amount_toman),
            '{deposit_amount_toman}' => $this->smsFormatToman((int) $declaration->amount_toman),
            '{loan_number}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{loan_code}' => $loanCode !== '' ? Jalali::enToFaNumbers($loanCode) : '—',
            '{payment_method}' => $method,
            '{declaration_id}' => Jalali::enToFaNumbers((string) $declaration->id),
            '{app_name}' => $this->smsAppDisplayName(),
        ];

        return $this->smsNormalizeText(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} اعلام واریزی قسط شماره {installment_number} به مبلغ {deposit_amount} تومان از وام {loan_number} را ثبت کرد.';
    }
}
