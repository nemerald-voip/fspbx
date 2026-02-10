<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

class RingGroupListResponseData extends Data
{
    public function __construct(
        public string $object,
        public string $url,
        public bool $has_more,

        #[DataCollectionOf(RingGroupData::class)]
        public DataCollection $data,
    ) {}
}
