<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceProfileData extends Data
{
    public function __construct(
        public string $device_profile_uuid,
        public ?string $device_profile_name = null,
    ) {}
}
