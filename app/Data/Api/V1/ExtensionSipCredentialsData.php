<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ExtensionSipCredentialsData extends Data
{
    public function __construct(
        public string $extension_uuid,
        public string $object,
        public string $domain_uuid,
        public string $extension,
        public ?string $password,
        public ?string $domain,
    ) {}
}
