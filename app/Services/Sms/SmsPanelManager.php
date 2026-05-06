<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsPanelGateway;
use InvalidArgumentException;

final class SmsPanelManager
{
    /**
     * @var array<string, SmsPanelGateway>
     */
    private array $gatewaysByProvider = [];

    /**
     * @param iterable<SmsPanelGateway> $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gatewaysByProvider[$gateway->providerKey()] = $gateway;
        }
    }

    /**
     * @return array<string, string>
     */
    public function providerOptions(): array
    {
        $out = [];
        foreach ($this->gatewaysByProvider as $provider => $gateway) {
            $out[$provider] = $gateway->displayName();
        }

        return $out;
    }

    public function gateway(string $provider): SmsPanelGateway
    {
        if (! isset($this->gatewaysByProvider[$provider])) {
            throw new InvalidArgumentException('SMS panel provider is not supported: '.$provider);
        }

        return $this->gatewaysByProvider[$provider];
    }
}
