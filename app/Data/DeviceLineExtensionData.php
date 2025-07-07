<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceLineExtensionData extends Data
{
    public function __construct(
        public ?string $extension_uuid,
        public ?string $extension,
        public ?string $effective_caller_id_name,
        public ?string $name_formatted = null,
        public ?bool $suspended = null,
        public ?string $email = null,
    ) {}
}
