<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DomainData extends Data
{
    public function __construct(
        public ?string $domain_uuid = null,
        public ?string $domain_name = null,
        public ?bool $domain_enabled = true,
        public ?string $domain_description = null,
    ) {}
}
