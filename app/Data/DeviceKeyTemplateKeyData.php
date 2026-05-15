<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceKeyTemplateKeyData extends Data
{
    public function __construct(
        public ?string $device_key_template_key_uuid = null,
        public ?string $key_area = null,
        public ?int $key_index = null,
        public ?string $key_type = null,
        public ?string $key_value = null,
        public ?string $key_label = null,
    ) {}
}
