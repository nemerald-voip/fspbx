<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class DomainListResponseData extends Data
{
    /**
     * @param array<int, DomainData> $data
     */
    public function __construct(
        /** Always "list" */
        public string $object,

        /** Path to this list endpoint */
        public string $url,

        /** Whether there are more results after this page */
        public bool $has_more,

        /** @var array<int, DomainData> */
        public array $data,
    ) {}
}

