<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceKeyData extends Data
{
    public function __construct(
        public ?string $device_key_uuid,
        public ?int $key_index,
        public ?string $key_type,
        public ?string $key_value,
        public ?string $key_label,
    ) {}
}
