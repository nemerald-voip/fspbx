<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceData extends Data
{
    public function __construct(
        public string $device_uuid,
        public ?string $device_profile_uuid = null,
        public ?string $device_address = null,
        public ?string $serial_number = null,
        public ?string $device_template = null,
        public ?string $device_template_uuid = null,
        public ?string $device_address_formatted = null,
        public ?string $device_vendor = null,
        public ?string $domain_uuid = null,
        public ?string $device_description = null,
        public ?string $device_provisioned_method = null,
        public ?string $device_provisioned_date = null,
        public ?string $device_provisioned_date_formatted = null,
        /** @var DeviceLineData[]|null */
        public ?array $lines = null,
        /** @var DeviceKeyData[]|null */
        public ?array $keys = null,
        public ?DeviceProfileData $profile = null,
        /** @var DeviceSettingData[]|null */
        public ?array $settings = null,
        /** @var CloudProvisioningData|null */
        public ?CloudProvisioningData $cloud_provisioning = null,
        /** @var DomainData|null */
        public ?DomainData $domain = null,
        /** @var ProvisioningTemplateData|null */
        public ?ProvisioningTemplateData $template = null,
    ) {}
}
