<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CloudProvisioningData extends Data
{
    public function __construct(
        public string $uuid,
        public ?string $device_uuid,
        public ?string $last_action,
        public ?string $status,
        public ?string $error,
    ) {}
}
