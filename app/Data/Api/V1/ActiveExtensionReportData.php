<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ActiveExtensionReportData extends Data
{
    public function __construct(
        public string $domain_uuid,
        public string $object,
        public string $domain_name,
        public ?string $domain_description,
        public int $total_extensions,
        public int $suspended_extensions,
        public int $active_extensions,
        public int $active_mobile_apps,
    ) {}
}
