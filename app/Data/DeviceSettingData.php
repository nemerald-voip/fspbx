<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DeviceSettingData extends Data
{
    public function __construct(
        public string  $device_setting_uuid,
        public ?string  $device_setting_subcategory,
        public ?string $device_setting_value,
        public ?string $device_setting_description,
        public ?string $device_setting_enabled, // 'true' | 'false' (text in DB)
    ) {}
}