<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketRecipient;
use Illuminate\Validation\ValidationException;

final class SupportTicketAccess
{
    public function customerCanAccess(Customer $customer, SupportTicket $ticket): bool
    {
        if ((int) $ticket->created_by_customer_id === (int) $customer->id) {
            return true;
        }

        return SupportTicketRecipient::query()
            ->where('support_ticket_id', (int) $ticket->id)
            ->where('customer_id', (int) $customer->id)
            ->exists();
    }

    public function assertCustomerCanAccess(Customer $customer, SupportTicket $ticket): void
    {
        if (! $this->customerCanAccess($customer, $ticket)) {
            throw ValidationException::withMessages([
                'ticket' => ['دسترسی به این تیکت مجاز نیست.'],
            ]);
        }
    }

    public function adminCanAccessTicketForCustomer(int $customerId, SupportTicket $ticket): bool
    {
        if ((int) $ticket->created_by_customer_id === $customerId) {
            return true;
        }

        if ($ticket->created_by_admin_id !== null) {
            return SupportTicketRecipient::query()
                ->where('support_ticket_id', (int) $ticket->id)
                ->where('customer_id', $customerId)
                ->exists();
        }

        return false;
    }

    public function assertAdminCanAccessTicketForCustomer(int $customerId, SupportTicket $ticket): void
    {
        if (! $this->adminCanAccessTicketForCustomer($customerId, $ticket)) {
            throw ValidationException::withMessages([
                'ticket' => ['این تیکت به مشتری انتخاب‌شده تعلق ندارد.'],
            ]);
        }
    }

    public function customerCanAccessAttachment(Customer $customer, SupportTicketAttachment $attachment): bool
    {
        $attachment->loadMissing('message.ticket');
        $ticket = $attachment->message?->ticket;

        return $ticket !== null && $this->customerCanAccess($customer, $ticket);
    }

    public function assertCustomerCanAccessAttachment(Customer $customer, SupportTicketAttachment $attachment): void
    {
        if (! $this->customerCanAccessAttachment($customer, $attachment)) {
            abort(404);
        }
    }
}
