<?php

namespace App\Services\Messaging\Outbound;

use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use RuntimeException;

class OutboundProviderFactory
{
    public function make(string $carrier): OutboundProviderInterface
    {
        $map = config('messaging.outbound_providers', []);

        messaging_webhook_debug('OutboundProviderFactory make()', [
            'carrier' => $carrier,
            'available_providers' => array_keys($map),
        ]);

        $class = $map[$carrier] ?? null;

        if (!$class) {
            throw new RuntimeException("Unsupported carrier: {$carrier}");
        }

        return app($class);
    }
}
