<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DomainData extends Data
{
    public function __construct(
        public string $domain_uuid,
        public ?string $domain_name,
        public ?string $domain_description = null,
    ) {}
}
