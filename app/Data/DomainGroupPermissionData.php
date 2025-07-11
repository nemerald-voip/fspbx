<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DomainGroupPermissionData extends Data {
    public function __construct(
        public string $domain_group_uuid
    ) {}
}