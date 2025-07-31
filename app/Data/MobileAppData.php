<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class MobileAppData extends Data
{
    public function __construct(
        public string $mobile_app_user_uuid,
        public string $extension_uuid,
        public ?string $org_id,
        public ?string $conn_id,
        public ?string $user_id,
        public ?string $status,
        public ?bool $exclude_from_stale_report,
    ) {}
}