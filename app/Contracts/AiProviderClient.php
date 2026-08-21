<?php

namespace App\Contracts;

use App\Models\AiAgent;
use App\Models\AiProviderIntegration;

interface AiProviderClient
{
    public function provider(): string;

    public function listAgents(AiProviderIntegration $integration): array;

    public function test(AiProviderIntegration $integration): void;

    public function provision(AiAgent $agent): string;

    public function synchronize(AiAgent $agent, ?bool $enabled = null): void;

    public function refresh(AiAgent $agent): string;

    public function delete(AiAgent $agent): void;

    public function synchronizeTools(
        string $providerAgentId,
        array $managedTools,
        ?int $draftAgentVersion,
        callable $draftCreated,
    ): array;
}
