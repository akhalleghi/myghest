<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class InstallmentSmsReminderRunResult
{
    public function __construct(
        public readonly string $status,
        public readonly int $preDueSent = 0,
        public readonly int $dueDaySent = 0,
        public readonly int $overdueSent = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
    ) {}

    public static function skipped(string $status): self
    {
        return new self($status);
    }

    public function totalSent(): int
    {
        return $this->preDueSent + $this->dueDaySent + $this->overdueSent;
    }
}
