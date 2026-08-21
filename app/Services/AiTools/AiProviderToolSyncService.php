<?php

namespace App\Services\AiTools;

use App\Models\AiAgent;
use App\Models\AiProviderToolSync;
use App\Services\AiProviderIntegrationService;
use App\Services\AiProviderRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AiProviderToolSyncService
{
    public function __construct(
        private readonly AiProviderToolCatalog $catalog,
        private readonly AiProviderRegistry $providers,
        private readonly AiProviderIntegrationService $integrations,
    ) {
    }

    public function ready(): bool
    {
        return Schema::hasTable('ai_agents') && Schema::hasTable('ai_provider_tool_syncs');
    }

    public function targets(?string $onlyProviderAgentId = null): Collection
    {
        if (! $this->ready()) {
            return collect();
        }

        return AiAgent::query()
            ->select(['provider', 'inbound_agent_id'])
            ->whereNotNull('inbound_agent_id')
            ->when($onlyProviderAgentId, fn ($query) => $query->where('inbound_agent_id', $onlyProviderAgentId))
            ->distinct()
            ->get()
            ->map(fn (AiAgent $agent) => [
                'provider' => $agent->provider,
                'provider_agent_id' => $agent->inbound_agent_id,
            ]);
    }

    public function shouldSync(string $provider, string $providerAgentId, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        $sync = $this->find($provider, $providerAgentId);

        return ! $sync
            || ! in_array($sync->status, ['synced', 'needs_configuration'], true)
            || $sync->catalog_fingerprint !== $this->catalog->fingerprint($provider);
    }

    public function markPending(string $provider, string $providerAgentId): AiProviderToolSync
    {
        $sync = $this->find($provider, $providerAgentId) ?? new AiProviderToolSync([
            'provider' => $provider,
            'provider_agent_id' => $providerAgentId,
        ]);

        $sync->forceFill([
            'status' => 'pending',
            'last_error' => null,
        ])->save();

        return $sync;
    }

    public function synchronize(string $provider, string $providerAgentId, bool $force = false): AiProviderToolSync
    {
        $sync = $this->find($provider, $providerAgentId) ?? new AiProviderToolSync([
            'provider' => $provider,
            'provider_agent_id' => $providerAgentId,
        ]);
        $fingerprint = $this->catalog->fingerprint($provider);

        if (! $force
            && $sync->exists
            && in_array($sync->status, ['synced', 'needs_configuration'], true)
            && $sync->catalog_fingerprint === $fingerprint) {
            return $sync;
        }

        $sync->forceFill([
            'status' => 'syncing',
            'last_error' => null,
            'last_attempt_at' => now(),
        ])->save();

        try {
            $integration = $this->integrations->integration($provider);
            if (! $integration->enabled || ! $integration->hasApiKey()) {
                throw new \RuntimeException('Complete and enable the Retell integration before synchronizing tools.');
            }

            $result = $this->providers->client($provider)->synchronizeTools(
                $providerAgentId,
                $this->catalog->definitions($provider),
                $sync->draft_agent_version,
                function (int $version) use ($sync): void {
                    $sync->forceFill(['draft_agent_version' => $version])->save();
                },
            );

            $sync->forceFill([
                'response_engine_type' => $result['response_engine_type'] ?? null,
                'response_engine_id' => $result['response_engine_id'] ?? null,
                'response_engine_version' => $result['response_engine_version'] ?? null,
                'catalog_fingerprint' => $fingerprint,
                'status' => ($result['configuration_required'] ?? false) ? 'needs_configuration' : 'synced',
                'draft_agent_version' => null,
                'published_agent_version' => $result['published_agent_version'] ?? null,
                'last_error' => null,
                'last_synced_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $sync->forceFill([
                'status' => 'failed',
                'last_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();

            throw $exception;
        }

        return $sync;
    }

    public function summary(): array
    {
        if (! Schema::hasTable('ai_agents')) {
            return $this->emptySummary();
        }

        $targets = AiAgent::query()
            ->select(['provider', 'inbound_agent_id'])
            ->whereNotNull('inbound_agent_id')
            ->distinct()
            ->get();

        if ($targets->isEmpty()) {
            return $this->emptySummary();
        }

        if (! Schema::hasTable('ai_provider_tool_syncs')) {
            return $this->summaryPayload($targets->count(), $targets->count(), 0, 0, 0, 0);
        }

        $syncs = AiProviderToolSync::query()
            ->whereIn('provider', $targets->pluck('provider')->unique())
            ->get()
            ->keyBy(fn (AiProviderToolSync $sync) => $sync->provider . "\0" . $sync->provider_agent_id);

        $pending = 0;
        $failed = 0;
        $syncing = 0;
        $configurationRequired = 0;
        $current = 0;

        foreach ($targets as $target) {
            $sync = $syncs->get($target->provider . "\0" . $target->inbound_agent_id);
            $fingerprint = $this->catalog->fingerprint($target->provider);

            if ($sync?->status === 'failed') {
                $failed++;
            } elseif (in_array($sync?->status, ['pending', 'syncing'], true)) {
                $syncing++;
            } elseif ($sync?->status === 'needs_configuration' && $sync->catalog_fingerprint === $fingerprint) {
                $configurationRequired++;
            } elseif ($sync?->status === 'synced' && $sync->catalog_fingerprint === $fingerprint) {
                $current++;
            } else {
                $pending++;
            }
        }

        return $this->summaryPayload(
            $targets->count(),
            $pending,
            $failed,
            $syncing,
            $configurationRequired,
            $current,
        );
    }

    private function find(string $provider, string $providerAgentId): ?AiProviderToolSync
    {
        return AiProviderToolSync::query()
            ->where('provider', $provider)
            ->where('provider_agent_id', $providerAgentId)
            ->first();
    }

    private function emptySummary(): array
    {
        return $this->summaryPayload(0, 0, 0, 0, 0, 0);
    }

    private function summaryPayload(
        int $total,
        int $pending,
        int $failed,
        int $syncing,
        int $configurationRequired,
        int $current,
    ): array
    {
        return [
            'total' => $total,
            'pending' => $pending,
            'failed' => $failed,
            'syncing' => $syncing,
            'configuration_required' => $configurationRequired,
            'current' => $current,
            'catalog_revision' => AiProviderToolCatalog::REVISION,
        ];
    }
}
