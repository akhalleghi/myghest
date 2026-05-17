<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Concerns\SendsAdminRecipientSms;
use Hekmatinasser\Jalali\Jalali;

final class CustomerLoanRequestNotifyAdminSmsService
{
    use SendsAdminRecipientSms;

    public const TYPE = 'customer-loan-request-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnRequest(int $loanRequestId): void
    {
        $request = CustomerLoanRequest::query()
            ->with(['customer', 'loanType'])
            ->find($loanRequestId);

        if ($request === null) {
            return;
        }

        $customer = $request->customer;
        if ($customer === null) {
            return;
        }

        $settings = $this->smsSettings->customerLoanRequestNotifyAdminSettings();
        $message = $this->renderMessage(trim($settings['message_template']), $customer, $request);
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
                'loan_request_id' => $loanRequestId,
                'customer_id' => (int) $customer->id,
            ],
            'customer_loan_request_notify_admin_sms',
        );
    }

    public function renderMessage(string $template, Customer $customer, CustomerLoanRequest $request): string
    {
        if ($template === '') {
            return '';
        }

        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));
        $loanTypeTitle = trim((string) ($request->loanType?->title ?? ''));

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_username}' => $username,
            '{request_amount}' => $this->smsFormatToman((int) $request->amount_toman),
            '{request_amount_toman}' => $this->smsFormatToman((int) $request->amount_toman),
            '{loan_type}' => $loanTypeTitle !== '' ? $loanTypeTitle : '—',
            '{request_id}' => Jalali::enToFaNumbers((string) $request->id),
            '{app_name}' => $this->smsAppDisplayName(),
        ];

        return $this->smsNormalizeText(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} درخواست وام «{loan_type}» به مبلغ {request_amount} تومان ثبت کرد.';
    }
}
