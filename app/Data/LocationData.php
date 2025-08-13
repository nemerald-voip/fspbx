<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class LocationData extends Data
{
    public function __construct(
        public string $location_uuid,
        public ?string $name = null,
    ) {}
}
