<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class DeviceData extends Data
{
    public function __construct(
        public string $device_uuid,
        public string $object,
        public string $domain_uuid,

        public ?string $device_profile_uuid = null,
        public ?string $device_address = null,
        public ?string $device_label = null,
        public ?string $device_template = null,
        public ?string $device_template_uuid = null,
        public ?string $device_description = null,
        public ?string $device_provisioned_date = null,
        public ?string $device_provisioned_ip = null,
        public ?string $device_provisioned_agent = null,
    ) {}
}
