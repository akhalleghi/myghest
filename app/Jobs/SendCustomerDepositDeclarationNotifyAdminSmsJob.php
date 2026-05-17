<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerDepositDeclarationNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendCustomerDepositDeclarationNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $declarationId,
    ) {}

    public function handle(CustomerDepositDeclarationNotifyAdminSmsService $service): void
    {
        try {
            $service->notifyAdminsOnDeclaration($this->declarationId);
        } catch (\Throwable $e) {
            Log::warning('customer_deposit_declaration_notify_admin_job_failed', [
                'declaration_id' => $this->declarationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
