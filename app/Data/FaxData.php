<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class FaxData extends Data
{
    public function __construct(
        public string $fax_uuid,
        public string $domain_uuid,
        public ?string $fax_email,
        public ?string $fax_name,
        public ?string $fax_extension,
        public ?string $fax_destination_number,
        public ?string $fax_caller_id_number,
        public ?string $fax_caller_id_number_formatted,
        public ?string $fax_description,
    ) {}
}
