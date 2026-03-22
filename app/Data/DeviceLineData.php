<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceLineData extends Data
{
    public function __construct(
        public ?string $device_line_uuid,
        public ?string $line_number,
        public ?string $auth_id,
        public mixed $external_line = null,
        public ?DeviceLineExtensionData $extension = null
    ) {}
}
