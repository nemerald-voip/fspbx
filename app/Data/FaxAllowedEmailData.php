<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class FaxAllowedEmailData extends Data
{
    public function __construct(
        public string $uuid,
        public string $fax_uuid,
        public string $email,
    ) {}
}
