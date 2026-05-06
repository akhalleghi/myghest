<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsPanelConnectionResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?string $code = null,
    ) {
    }
}
