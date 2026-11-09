<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\InstallmentWalletRewardSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendInstallmentWalletRewardSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public readonly int $walletTransactionId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(InstallmentWalletRewardSmsService $service): void
    {
        $service->send($this->walletTransactionId);
    }
}
