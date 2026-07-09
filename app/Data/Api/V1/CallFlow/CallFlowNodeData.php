<?php

namespace App\Data\Api\V1\CallFlow;

use Spatie\LaravelData\Data;

class CallFlowNodeData extends Data
{
    /**
     * @param array<int, CallFlowBranchData> $branches
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $node_id,
        public string $type,
        public string $label,
        public ?string $resource_uuid,
        public ?string $extension,
        public ?array $metadata,
        public array $branches,
    ) {}
}
