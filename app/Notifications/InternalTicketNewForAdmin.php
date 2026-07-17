<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Admin;
use App\Models\InternalTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class InternalTicketNewForAdmin extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $subject,
        public readonly string $senderName,
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
            'kind' => 'internal_ticket_new',
            'title' => 'تیکت داخلی جدید',
            'body' => $name.' تیکت داخلی جدیدی با موضوع «'.$this->subject.'» برای شما ارسال کرده است.',
            'url_name' => 'admin.internal-tickets.index',
            'url_query' => ['tab' => 'received'],
            'icon' => 'fa-solid fa-comments',
            'ticket_id' => $this->ticketId,
            'subject' => $this->subject,
            'sender_name' => $name,
        ];
    }

    public static function build(InternalTicket $ticket, Admin $sender): self
    {
        return new self(
            ticketId: (int) $ticket->id,
            subject: (string) $ticket->subject,
            senderName: $sender->fullName(),
        );
    }
}
