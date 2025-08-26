<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ProvisioningTemplateData extends Data
{
    public function __construct(
        public string  $template_uuid,
        public ?string  $vendor = null,
        public ?string  $name= null,
        public ?string  $type= null,          // default|custom
        public ?string $version= null,       // SemVer for defaults
        public ?int     $revision= null,      // customs >= 1, defaults 0
        public ?string $domain_uuid = null,   
    ) {}
}
