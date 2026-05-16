<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketNewForCustomer;
use App\Notifications\SupportTicketRepliedForAdmins;
use App\Notifications\SupportTicketRepliedForCustomer;
use App\Notifications\SupportTicketSubmittedForAdmins;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class SupportTicketNotifier
{
    public function customerSubmittedTicket(SupportTicket $ticket, Customer $customer): void
    {
        $this->notifyAdmins(SupportTicketSubmittedForAdmins::build($ticket, $customer));
    }

    public function customerReplied(SupportTicket $ticket, Customer $customer): void
    {
        $this->notifyAdmins(SupportTicketRepliedForAdmins::build($ticket, $customer));
    }

    public function adminOpenedTicketForCustomer(SupportTicket $ticket, Customer $customer): void
    {
        $this->notifyCustomer($customer, SupportTicketNewForCustomer::build($ticket));
    }

    public function adminReplied(SupportTicket $ticket, Customer $customer): void
    {
        $this->notifyCustomer($customer, SupportTicketRepliedForCustomer::build($ticket));
    }

    public function notifyAdmins(Notification $notification): void
    {
        try {
            $admins = Admin::query()->where('is_active', true)->get();
            if ($admins->isEmpty()) {
                return;
            }
            NotificationFacade::send($admins, $notification);
        } catch (\Throwable $e) {
            Log::warning('support ticket admin notification failed', [
                'type' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyCustomer(Customer $customer, Notification $notification): void
    {
        try {
            $customer->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('support ticket customer notification failed', [
                'customer_id' => $customer->id,
                'type' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
