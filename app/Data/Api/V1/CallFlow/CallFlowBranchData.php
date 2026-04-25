<?php

namespace App\Data\Api\V1\CallFlow;

use Spatie\LaravelData\Data;

class CallFlowBranchData extends Data
{
    public function __construct(
        public string $condition,
        public ?string $label,
        public bool $active,
        public CallFlowNodeData $child,
    ) {}
}
