<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SupportTicketRepliedForAdmins extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $customerName,
        public readonly string $subject,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $name = trim($this->customerName) !== '' ? trim($this->customerName) : 'کاربر';

        return [
            'kind' => 'support_ticket_replied',
            'title' => 'پاسخ جدید به تیکت',
            'body' => 'شما از طرف '.$name.' پاسخ جدیدی برای تیکت «'.$this->subject.'» دریافت کرده‌اید.',
            'url_name' => 'admin.tickets.index',
            'url_query' => ['tab' => 'received'],
            'icon' => 'fa-solid fa-reply',
            'ticket_id' => $this->ticketId,
            'customer_name' => $name,
            'subject' => $this->subject,
        ];
    }

    public static function build(SupportTicket $ticket, Customer $customer): self
    {
        return new self(
            ticketId: (int) $ticket->id,
            customerName: $customer->fullName(),
            subject: (string) $ticket->subject,
        );
    }
}
