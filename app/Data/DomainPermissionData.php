<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DomainPermissionData extends Data {
    public function __construct(
        public string $domain_uuid
    ) {}
}
