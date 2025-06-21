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
        public ?string $device_address_formatted = null, 
        public ?string $device_vendor = null, 
        public ?string $domain_uuid = null, 
        /** @var DeviceLineData[]|null */
        public ?array $lines = null,
        public ?DeviceProfileData $profile = null, 
    ) {}
}
