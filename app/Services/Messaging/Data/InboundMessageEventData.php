<?php

namespace App\Services\Messaging\Data;

use App\Services\Messaging\Data\MessagingEventData;

class InboundMessageEventData extends MessagingEventData
{
    public function __construct(
        public string $provider,
        public ?string $providerReferenceId,
        public string $from,
        public array $to,
        public string $text = '',
        public array $mediaUrls = [],
        public ?string $providerEvent = null,
        public array $storedMedia = [],
        public bool $isMms = false,
        // Some carriers deliver one webhook per local number, with the full group in `to`.
        public ?array $localRecipients = null,
    ) {}
}
