<?php

namespace App\Services\Messaging\Delivery;

use App\Models\Messages;
use App\Services\Messaging\RingotelSyncDispatcher;

/** Compatibility for inbound delivery jobs already queued before the update. */
class RingotelInboundDeliveryService
{
    public function __construct(protected RingotelSyncDispatcher $dispatcher) {}

    public function deliver(string $messageUuid, string $orgId, string $extension): bool
    {
        $message = Messages::find($messageUuid);
        if (!$message || $message->direction !== 'in') {
            return false;
        }
        $this->dispatcher->dispatch($message);
        return true;
    }
}
