<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class FaxAllowedDomainNameData extends Data
{
    public function __construct(
        public string $uuid,
        public string $fax_uuid,
        public string $domain,
    ) {}
}
