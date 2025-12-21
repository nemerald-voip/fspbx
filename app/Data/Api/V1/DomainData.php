<?php 

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class DomainData extends Data
{
    public function __construct(
        /** UUID of the domain */
        public string $domain_uuid,

        /** Always "domain" */
        public string $object,

        /** Domain name  */
        public string $domain_name,

        /** Whether the domain is enabled */
        public bool $domain_enabled,

        /** Domain description */
        public ?string $domain_description,
    ) {}
}

