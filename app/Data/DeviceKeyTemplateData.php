<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceKeyTemplateData extends Data
{
    public function __construct(
        public ?string $device_key_template_uuid = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $enabled = null,
        /** @var DeviceKeyTemplateKeyData[]|null */
        public ?array $keys = null,
    ) {}
}
