<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ExtensionStatisticListResponseData extends Data
{
    /**
     * @param array<int, ExtensionStatisticData> $data
     */
    public function __construct(
        public string $object,
        public string $url,
        public bool $has_more,
        public array $data,
    ) {}
}
