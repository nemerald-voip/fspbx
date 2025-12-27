<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ExtensionListResponseData extends Data
{
    /**
     * @param array<int, ExtensionData> $data
     */
    public function __construct(
        /** Always "list" */
        public string $object,

        /** Path to this list endpoint */
        public string $url,

        /** Whether there are more results after this page */
        public bool $has_more,

        /** @var array<int, ExtensionData> */
        public array $data,
    ) {}
}
