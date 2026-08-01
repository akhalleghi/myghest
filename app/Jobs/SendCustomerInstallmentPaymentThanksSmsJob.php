<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\CustomerInstallmentPaymentThanksSmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * پیامک تشکر پرداخت قسط به مشتری — پس از پاسخ HTTP.
 */
final class SendCustomerInstallmentPaymentThanksSmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $paymentId,
    ) {}

    public function handle(CustomerInstallmentPaymentThanksSmsService $thanksService): void
    {
        try {
            $thanksService->sendThanksOnPortalPayment($this->paymentId);
        } catch (\Throwable $e) {
            Log::warning('customer_installment_payment_thanks_job_failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
