<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class FaxFileData extends Data
{
    public function __construct(
        public string $fax_file_uuid,
        public ?string $fax_uuid,
        public ?string $domain_uuid,
        public ?string $fax_caller_id_number,
        public ?string $fax_caller_id_number_formatted,
        public ?string $fax_date,
        public ?string $fax_date_formatted,
        public ?FaxData $fax, 
    ) {}
}
