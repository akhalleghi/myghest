<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsPanelCreditResult
{
    public function __construct(
        public bool $ok,
        public ?int $credit,
        public string $message,
    ) {
    }
}
