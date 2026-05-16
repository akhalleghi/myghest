<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SupportTicketRepliedForCustomer extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
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
        return [
            'kind' => 'support_ticket_replied',
            'title' => 'پاسخ پشتیبانی',
            'body' => 'شما از طرف پشتیبانی پاسخ جدیدی برای تیکت «'.$this->subject.'» دریافت کرده‌اید.',
            'url_name' => 'user.tickets.index',
            'url_query' => ['tab' => 'received'],
            'icon' => 'fa-solid fa-reply',
            'ticket_id' => $this->ticketId,
            'subject' => $this->subject,
        ];
    }

    public static function build(SupportTicket $ticket): self
    {
        return new self(
            ticketId: (int) $ticket->id,
            subject: (string) $ticket->subject,
        );
    }
}
