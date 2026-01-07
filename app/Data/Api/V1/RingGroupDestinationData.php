<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class RingGroupDestinationData extends Data
{
    public function __construct(
        public string $ring_group_destination_uuid,
        public string $ring_group_uuid,

        public string $destination_number,
        public ?bool $destination_enabled = null,

        public ?int $destination_delay = null,
        public ?int $destination_timeout = null,
        public ?bool $destination_prompt = null,

    ) {}
}
