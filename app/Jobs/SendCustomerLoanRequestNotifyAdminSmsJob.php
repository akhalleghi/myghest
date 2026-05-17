<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerLoanRequestNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendCustomerLoanRequestNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $loanRequestId,
    ) {}

    public function handle(CustomerLoanRequestNotifyAdminSmsService $service): void
    {
        try {
            $service->notifyAdminsOnRequest($this->loanRequestId);
        } catch (\Throwable $e) {
            Log::warning('customer_loan_request_notify_admin_job_failed', [
                'loan_request_id' => $this->loanRequestId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
