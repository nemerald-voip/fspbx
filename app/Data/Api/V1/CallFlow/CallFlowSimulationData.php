<?php

namespace App\Data\Api\V1\CallFlow;

use Spatie\LaravelData\Data;

class CallFlowSimulationData extends Data
{
    /**
     * @param array<int, string> $warnings
     */
    public function __construct(
        public string $object,
        public string $url,
        public string $domain_uuid,
        public ?string $domain_name,
        public string $phone_number,
        public string $evaluated_at,
        public ?string $timezone,
        public CallFlowNodeData $tree,
        public array $warnings,
    ) {}
}
