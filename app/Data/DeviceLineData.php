<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceLineData extends Data
{
    public function __construct(
        public ?string $device_line_uuid,
        public ?string $line_number,
        public ?string $auth_id,
        public ?DeviceLineExtensionData $extension = null
    ) {}
}
