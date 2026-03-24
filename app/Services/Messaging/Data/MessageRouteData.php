<?php

namespace App\Services\Messaging\Data;

use Spatie\LaravelData\Data;

class MessageRouteData extends Data
{
    public function __construct(
        public string $domainUuid,
        public string $destination,
        public ?string $extensionUuid = null,
        public ?string $extension = null,
        public bool $hasMobileApp = false,
        public ?string $email = null,
        public ?string $orgId = null,
    ) {}
}