<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceData extends Data
{
    public function __construct(
        public string $device_uuid,
        public ?string $device_profile_uuid,
        public string $device_address,
        public string $device_template,
        public ?string $device_address_formatted = null, // Optional if you use accessors
        public ?DeviceProfileData $profile = null, 
    ) {}
}
