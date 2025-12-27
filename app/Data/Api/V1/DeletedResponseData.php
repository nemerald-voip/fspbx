<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class DeletedResponseData extends Data
{
    public function __construct(
        public string $uuid,      
        public string $object,  // "domain"
        public bool $deleted,
    ) {}
}