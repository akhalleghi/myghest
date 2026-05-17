<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Sms\CustomerLoginNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * اعلان ورود مشتری به ادمین‌ها — پس از پاسخ HTTP تا ورود مشتری کند نشود.
 */
final class SendCustomerLoginNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $customerId,
    ) {}

    public function handle(CustomerLoginNotifyAdminSmsService $notifyService): void
    {
        $customer = Customer::query()->find($this->customerId);
        if ($customer === null) {
            return;
        }

        try {
            $notifyService->notifyAdminsOnLogin($customer);
        } catch (\Throwable $e) {
            Log::warning('customer_login_notify_admin_job_failed', [
                'customer_id' => $this->customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
