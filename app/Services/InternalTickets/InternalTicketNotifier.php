<?php

declare(strict_types=1);

namespace App\Services\InternalTickets;

use App\Models\Admin;
use App\Models\InternalTicket;
use App\Notifications\InternalTicketNewForAdmin;
use App\Notifications\InternalTicketRepliedForAdmin;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class InternalTicketNotifier
{
    public function ticketOpenedForAdmin(InternalTicket $ticket, Admin $recipient, Admin $sender): void
    {
        $this->notifyAdmin($recipient, InternalTicketNewForAdmin::build($ticket, $sender));
    }

    public function repliedForAdmin(InternalTicket $ticket, Admin $recipient, Admin $sender, string $tab): void
    {
        $this->notifyAdmin($recipient, InternalTicketRepliedForAdmin::build($ticket, $sender, $tab));
    }

    public function notifyAdmin(Admin $admin, Notification $notification): void
    {
        try {
            if (! $admin->is_active) {
                return;
            }
            $admin->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('internal ticket admin notification failed', [
                'admin_id' => $admin->id,
                'type' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
