<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerInstallmentPaymentNotifyAdminSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * اعلان پرداخت قسط مشتری به ادمین — پس از پاسخ HTTP.
 */
final class SendCustomerInstallmentPaymentNotifyAdminSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $paymentId,
    ) {}

    public function handle(CustomerInstallmentPaymentNotifyAdminSmsService $notifyService): void
    {
        try {
            $notifyService->notifyAdminsOnPortalPayment($this->paymentId);
        } catch (\Throwable $e) {
            Log::warning('customer_installment_payment_notify_admin_job_failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
