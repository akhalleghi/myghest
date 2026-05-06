<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

use App\Services\Sms\SmsPanelConnectionResult;

interface SmsPanelGateway
{
    public function providerKey(): string;

    public function displayName(): string;

    /**
     * @param array<string, mixed> $config
     */
    public function testConnection(string $username, string $password, array $config = []): SmsPanelConnectionResult;

    /**
     * @param array<string, mixed> $config
     */
    public function sendTestMessage(
        string $username,
        string $password,
        string $recipient,
        string $message,
        array $config = [],
    ): SmsPanelConnectionResult;
}
