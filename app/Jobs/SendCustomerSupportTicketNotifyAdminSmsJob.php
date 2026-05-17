<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerSupportTicketNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendCustomerSupportTicketNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
    ) {}

    public function handle(CustomerSupportTicketNotifyAdminSmsService $service): void
    {
        try {
            $service->notifyAdminsOnTicket($this->ticketId);
        } catch (\Throwable $e) {
            Log::warning('customer_support_ticket_notify_admin_job_failed', [
                'ticket_id' => $this->ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
