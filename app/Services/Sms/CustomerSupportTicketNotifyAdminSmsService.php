<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Concerns\SendsAdminRecipientSms;

final class CustomerSupportTicketNotifyAdminSmsService
{
    use SendsAdminRecipientSms;

    public const TYPE = 'customer-support-ticket-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnTicket(int $ticketId): void
    {
        $ticket = SupportTicket::query()
            ->with('createdByCustomer')
            ->find($ticketId);

        if ($ticket === null || $ticket->created_by_customer_id === null) {
            return;
        }

        $customer = $ticket->createdByCustomer;
        if ($customer === null) {
            return;
        }

        $settings = $this->smsSettings->customerSupportTicketNotifyAdminSettings();
        $message = $this->renderMessage(trim($settings['message_template']), $customer, $ticket);
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
                'ticket_id' => $ticketId,
                'customer_id' => (int) $customer->id,
            ],
            'customer_support_ticket_notify_admin_sms',
        );
    }

    public function renderMessage(string $template, Customer $customer, SupportTicket $ticket): string
    {
        if ($template === '') {
            return '';
        }

        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));
        $subject = trim((string) ($ticket->subject ?? ''));

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_username}' => $username,
            '{ticket_subject}' => $subject !== '' ? $subject : '—',
            '{ticket_id}' => \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $ticket->id),
            '{app_name}' => $this->smsAppDisplayName(),
        ];

        return $this->smsNormalizeText(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} تیکت با موضوع «{ticket_subject}» در پنل کاربری ثبت کرد.';
    }
}
