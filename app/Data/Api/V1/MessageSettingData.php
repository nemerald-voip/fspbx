<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class MessageSettingData extends Data
{
    public function __construct(
        public string $sms_destination_uuid,
        public string $object,
        public string $domain_uuid,
        public ?string $destination,
        public ?string $carrier,
        /** @var string[] */
        public array $allowed_extension_uuids,
        public ?string $email,
        public ?string $description,
        public bool $enabled,
    ) {}
}
