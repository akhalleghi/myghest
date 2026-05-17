<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerFullSettlementNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendCustomerFullSettlementNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $customerId,
        public readonly int $loanFileId,
        public readonly int $amountToman,
    ) {}

    public function handle(CustomerFullSettlementNotifyAdminSmsService $service): void
    {
        try {
            $service->notifyAdminsOnSettlement($this->customerId, $this->loanFileId, $this->amountToman);
        } catch (\Throwable $e) {
            Log::warning('customer_full_settlement_notify_admin_job_failed', [
                'customer_id' => $this->customerId,
                'loan_file_id' => $this->loanFileId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
