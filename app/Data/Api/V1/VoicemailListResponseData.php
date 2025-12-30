<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class VoicemailListResponseData extends Data
{
    /**
     * @param array<int, VoicemailData> $data
     */
    public function __construct(
        public string $object,
        public string $url,
        public bool $has_more,
        /** @var array<int, VoicemailData> */
        public array $data,
    ) {}
}
