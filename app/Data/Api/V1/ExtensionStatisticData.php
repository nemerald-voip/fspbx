<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ExtensionStatisticData extends Data
{
    public function __construct(
        public string $extension_uuid,
        public string $object,
        public string $domain_uuid,
        public ?string $extension = null,
        public ?string $extension_label = null,
        public int $call_count = 0,
        public int $inbound = 0,
        public int $outbound = 0,
        public int $missed = 0,
        public int $total_duration_seconds = 0,
        public string $total_duration_formatted = '00:00:00',
        public int $total_talk_time_seconds = 0,
        public string $total_talk_time_formatted = '00:00:00',
        public int $average_duration_seconds = 0,
        public string $average_duration_formatted = '00:00:00',
    ) {}
}
