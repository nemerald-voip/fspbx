<?php

namespace App\Services\CallFlow;

use DateTimeImmutable;

/**
 * Traversal state shared across the recursive walk: depth/cycle guards,
 * node-id sequence, and accumulated warnings.
 */
class CallFlowContext
{
    public int $depth = 0;
    public int $nodeCounter = 0;

    /** @var array<string, bool> */
    public array $visited = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public function __construct(
        public string $domainUuid,
        public ?string $domainName,
        public DateTimeImmutable $at,
        public string $timezone,
        public int $maxDepth = 20,
    ) {}

    public function nextNodeId(): string
    {
        return 'n' . (++$this->nodeCounter);
    }

    public function hasVisited(string $type, ?string $id): bool
    {
        if ($id === null || $id === '') {
            return false;
        }
        return isset($this->visited[$type . ':' . $id]);
    }

    public function markVisited(string $type, ?string $id): void
    {
        if ($id !== null && $id !== '') {
            $this->visited[$type . ':' . $id] = true;
        }
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
