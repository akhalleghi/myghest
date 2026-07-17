<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Admin;
use App\Models\InternalTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class InternalTicketRepliedForAdmin extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $subject,
        public readonly string $senderName,
        public readonly string $tab,
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
        $name = trim($this->senderName) !== '' ? trim($this->senderName) : 'همکار';

        return [
            'kind' => 'internal_ticket_replied',
            'title' => 'پاسخ تیکت داخلی',
            'body' => $name.' به تیکت داخلی «'.$this->subject.'» پاسخ داده است.',
            'url_name' => 'admin.internal-tickets.index',
            'url_query' => ['tab' => $this->tab],
            'icon' => 'fa-solid fa-reply',
            'ticket_id' => $this->ticketId,
            'subject' => $this->subject,
            'sender_name' => $name,
        ];
    }

    public static function build(InternalTicket $ticket, Admin $sender, string $tab): self
    {
        return new self(
            ticketId: (int) $ticket->id,
            subject: (string) $ticket->subject,
            senderName: $sender->fullName(),
            tab: $tab === 'sent' ? 'sent' : 'received',
        );
    }
}
