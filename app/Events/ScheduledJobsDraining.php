<?php

namespace App\Events;

/** Consumers may defer a planned handoff until external resources are safe. */
class ScheduledJobsDraining
{
    public bool $ready = true;

    public function __construct(public readonly string $nodeId, public readonly int $generation) {}
}
