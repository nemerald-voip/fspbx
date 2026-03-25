<?php

namespace App\Services\Messaging\Outbound\Data;

use Spatie\LaravelData\Data;

class CreateOutboundMessageData extends Data
{
    public function __construct(
        public string $domainUuid,
        public ?string $extensionUuid,
        public string $source,
        public string $destination,
        public string $message = '',
        public string $origin = 'portal',
        public string $carrier,
        public ?string $providerReferenceId = null,
        public array $media = [],
        public array $mediaFiles = [],
        public array $mediaRemoteUrls = [],
        public array $meta = [],
    ) {}
}