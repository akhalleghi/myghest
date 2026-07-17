<?php

declare(strict_types=1);

namespace App\Services\InternalTickets;

use App\Models\Admin;
use App\Models\InternalTicket;
use App\Models\InternalTicketAttachment;
use App\Models\InternalTicketRecipient;
use Illuminate\Validation\ValidationException;

final class InternalTicketAccess
{
    public function adminCanAccess(Admin $admin, InternalTicket $ticket): bool
    {
        if ((int) $ticket->created_by_admin_id === (int) $admin->id) {
            return true;
        }

        return InternalTicketRecipient::query()
            ->where('internal_ticket_id', (int) $ticket->id)
            ->where('admin_id', (int) $admin->id)
            ->exists();
    }

    public function assertAdminCanAccess(Admin $admin, InternalTicket $ticket): void
    {
        if (! $this->adminCanAccess($admin, $ticket)) {
            throw ValidationException::withMessages([
                'ticket' => ['دسترسی به این تیکت مجاز نیست.'],
            ]);
        }
    }

    public function adminCanAccessAttachment(Admin $admin, InternalTicketAttachment $attachment): bool
    {
        $attachment->loadMissing('message.ticket');
        $ticket = $attachment->message?->ticket;

        return $ticket !== null && $this->adminCanAccess($admin, $ticket);
    }

    public function assertAdminCanAccessAttachment(Admin $admin, InternalTicketAttachment $attachment): void
    {
        if (! $this->adminCanAccessAttachment($admin, $attachment)) {
            abort(404);
        }
    }
}
