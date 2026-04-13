<?php

namespace App\Services\Messaging\Data;

use App\Services\Messaging\Data\MessagingEventData;

class DeliveryStatusEventData extends MessagingEventData
{
    public function __construct(
        public string $provider,
        public string $referenceId,
        public string $status,
        public ?string $description = null,
        public ?string $providerEvent = null,
    ) {}
}