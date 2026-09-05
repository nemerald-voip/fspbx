<?php

namespace App\Services\Ha;

use App\Models\DefaultSettings;
use App\Models\ScheduledJobExecution;
use App\Models\ScheduledJobHandoff;
use App\Models\ScheduledJobNode;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generic single-owner coordination for scheduled jobs.
 *
 * Subscription rows only discover possible peer addresses. PostgreSQL's
 * cluster system identifier, verified by the peer API, identifies a node.
 */
class ActiveNodeResolver
{
    public const SETTING_CATEGORY = 'scheduled_jobs';
    public const ACTIVE_NODE_SETTING = 'active_node';
    public const GENERATION_SETTING = 'active_node_generation';
    public const SECRET_SETTING = 'coordination_secret';

    public function __construct(
        private readonly ScheduledJobPeerClient $peerClient,
        private readonly ScheduledJobPeerAuthenticator $authenticator
    ) {}

    /** @return array<string, mixed> */
    public function resolve(): array
    {
        $nodeId = $this->localNodeId();
        // Reuse reads only within this decision. The next claim or guarded
        // write must read again, even when it uses this same resolver instance.
        $settings = $this->ownershipSettings();
        $configured = $this->setting(self::ACTIVE_NODE_SETTING, $settings);
        $generation = max(0, (int) ($this->setting(self::GENERATION_SETTING, $settings) ?? 0));
        $subscriptions = $this->subscriptionDiscovery();

        if ($problem = $this->ownershipProblem($settings)) {
            return $this->result(false, 'active_unknown', 'inconsistent', $problem, $configured, $nodeId, $generation, $subscriptions, ['recognized' => false]);
        }

        if ($nodeId === null) {
            return $this->result(false, 'active_unknown', 'database', __('The local PostgreSQL node identity could not be determined.'), $configured, null, $generation, $subscriptions);
        }

        $nodes = $this->nodes();
        $approvedNodes = $nodes->where('status', 'approved')->values();
        [$effectiveOwner, $legacyMatch] = $this->effectiveOwner($configured, $approvedNodes);
        $clustered = ! empty($subscriptions['endpoints']) || $nodes->isNotEmpty() || filled($configured);

        if ($configured === null) {
            if (! $clustered && ($subscriptions['readable'] ?? false)) {
                return $this->result(true, 'active', 'standalone', __('No database replication is configured, so this server runs all scheduled jobs.'), null, $nodeId, $generation, $subscriptions);
            }

            return $this->result(false, 'active_unknown', 'unconfigured', __('No server has been selected to run coordinated scheduled jobs, so they are being skipped. Approve the servers below and choose an owner.'), null, $nodeId, $generation, $subscriptions);
        }

        if ($effectiveOwner === null) {
            return $this->result(false, 'active_unknown', 'unrecognized', __('The selected server is not one of the approved scheduled-job servers, so coordinated jobs are being skipped.'), $configured, $nodeId, $generation, $subscriptions, ['recognized' => false]);
        }

        $owner = $approvedNodes->firstWhere('system_identifier', $effectiveOwner);
        if (! $owner) {
            return $this->result(false, 'active_unknown', 'retired', __('The selected server has been retired, so coordinated jobs are being skipped. Choose an approved server as the owner.'), $configured, $nodeId, $generation, $subscriptions, ['effective_owner' => $effectiveOwner]);
        }

        if ($legacyMatch) {
            return $this->result(false, 'active_unknown', 'legacy', __('Confirm the existing hostname or IP selection before coordinated jobs can run.'), $configured, $nodeId, $generation, $subscriptions, ['effective_owner' => $effectiveOwner, 'legacy_match' => true]);
        }
        $local = $approvedNodes->firstWhere('system_identifier', $nodeId);
        if (! $local || ! $this->matchesLocalHost($local)) {
            return $this->result(false, 'active_unknown', 'identity_mismatch', __('This server does not match its approved host identity. Keep its workers stopped and verify the node registration.'), $configured, $nodeId, $generation, $subscriptions, ['effective_owner' => $effectiveOwner]);
        }

        $pending = $this->pendingHandoff($effectiveOwner, $generation);
        if ($effectiveOwner === $nodeId && $pending) {
            return $this->result(false, 'draining', 'handoff', __('This server is draining scheduled work before transferring ownership.'), $configured, $nodeId, $generation, $subscriptions, [
                'effective_owner' => $effectiveOwner,
                'handoff' => $this->handoffArray($pending),
                'legacy_match' => $legacyMatch,
            ]);
        }

        if ($effectiveOwner === $nodeId) {
            return $this->result(true, 'active', 'setting', __('This server is the selected owner for coordinated scheduled jobs.'), $configured, $nodeId, $generation, $subscriptions, [
                'effective_owner' => $effectiveOwner,
                'legacy_match' => $legacyMatch,
            ]);
        }

        return $this->result(false, 'standby', 'setting', __('Another server is selected to own coordinated scheduled jobs.'), $configured, $nodeId, $generation, $subscriptions, [
            'effective_owner' => $effectiveOwner,
            'legacy_match' => $legacyMatch,
            'handoff' => $pending ? $this->handoffArray($pending) : null,
        ]);
    }

    public function isActive(): bool
    {
        return (bool) $this->resolve()['active'];
    }

    public function hostname(): string
    {
        return trim((string) (gethostname() ?: php_uname('n'))) ?: 'unknown-host';
    }

    public function localNodeId(): ?string
    {
        // Redis can survive a PostgreSQL replacement on the same host. Read
        // the connected cluster, never a cached identity from its predecessor.
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return null;
            }
            $row = DB::selectOne('select system_identifier::text as system_identifier from pg_control_system()');
            $value = trim((string) ($row->system_identifier ?? ''));

            return preg_match('/^\d+$/', $value) ? $value : null;
        } catch (Throwable $exception) {
            logger('ActiveNodeResolver: unable to read the PostgreSQL system identifier: '.$exception->getMessage());

            return null;
        }
    }

    public function hostFingerprint(): ?string
    {
        $machineId = @file_get_contents('/etc/machine-id');
        if (! is_string($machineId) || ! preg_match('/^[a-f0-9]{32}$/i', trim($machineId))) {
            return null;
        }

        // Application-specific identity, stable across secret rotation. Never
        // transmit the raw machine-id.
        return hash_hmac('sha256', trim($machineId), 'fspbx.scheduled-jobs.host-identity.v1');
    }

    private function matchesLocalHost(ScheduledJobNode $node): bool
    {
        $fingerprint = $this->hostFingerprint();

        return $fingerprint !== null && hash_equals((string) $node->host_fingerprint, $fingerprint);
    }

    public function configuredNode(): ?string
    {
        return $this->setting(self::ACTIVE_NODE_SETTING);
    }

    public function generation(): int
    {
        return max(0, (int) ($this->setting(self::GENERATION_SETTING) ?? 0));
    }

    public function coordinationSecretConfigured(): bool
    {
        return $this->authenticator->secretConfigured();
    }

    public function rotateCoordinationSecret(): void
    {
        DB::transaction(function () {
            $this->lockOwnershipSettings();
            // The first secret is an operator setup step, performed on one node
            // and replicated. Once membership exists, only its authority writes.
            if ($this->nodes()->isNotEmpty()) {
                $this->assertManagementWriter();
            }
            $this->writeSetting(self::SECRET_SETTING, Str::random(64), 'text', 'Shared secret used to authenticate scheduled-job coordination between approved FS PBX nodes.');
        });
    }

    /** @return Collection<int, ScheduledJobNode> */
    public function nodes(): Collection
    {
        return Schema::hasTable('scheduled_job_nodes')
            ? ScheduledJobNode::query()->orderBy('hostname')->get()
            : collect();
    }

    /** @return array<int, array<string, mixed>> */
    public function discover(?string $manualEndpoint = null): array
    {
        $endpoints = collect($this->subscriptionDiscovery()['endpoints'] ?? [])
            ->merge($this->nodes()->pluck('endpoint'))
            ->push($this->localEndpoint());
        if (filled($manualEndpoint)) {
            $endpoints->push($this->normalizeEndpoint($manualEndpoint));
        }

        $registered = $this->nodes()->keyBy('system_identifier');
        $results = [];
        foreach ($endpoints->filter()->unique()->values() as $endpoint) {
            $candidate = Cache::remember('scheduled-jobs:peer-identity:'.sha1($endpoint), 15, function () use ($endpoint) {
                try {
                    return $this->peerClient->identify($endpoint) + ['endpoint' => $endpoint, 'reachable' => true];
                } catch (Throwable $exception) {
                    return ['endpoint' => $endpoint, 'reachable' => false, 'message' => $exception->getMessage()];
                }
            });
            $candidate['registered'] = $registered->get($candidate['system_identifier'] ?? '')?->status;
            $results[] = $candidate;
        }

        $duplicates = collect($results)->where('reachable', true)->filter(fn (array $candidate) => filled($candidate['system_identifier'] ?? null))
            ->groupBy('system_identifier')->filter(fn (Collection $candidates) => $candidates->contains(fn ($candidate) => empty($candidate['host_fingerprint']))
                || $candidates->pluck('host_fingerprint')->unique()->count() > 1)->keys();

        return collect($results)->map(function (array $candidate) use ($duplicates) {
            $candidate['duplicate_identity'] = $duplicates->contains($candidate['system_identifier'] ?? null);
            if ($candidate['duplicate_identity']) {
                $candidate['message'] = __('This PostgreSQL system identifier answered from more than one endpoint. Approval is blocked.');
            }

            return $candidate;
        })->all();
    }

    public function approveNode(string $endpoint, string $expectedNodeId, ?string $userUuid): ScheduledJobNode
    {
        $endpoint = $this->normalizeEndpoint($endpoint);
        $identity = $this->peerClient->identify($endpoint);
        $nodeId = trim((string) ($identity['system_identifier'] ?? ''));
        if (! preg_match('/^\d{1,32}$/', $nodeId) || ! hash_equals($expectedNodeId, $nodeId)
            || ! preg_match('/^[a-f0-9]{64}$/', (string) ($identity['host_fingerprint'] ?? ''))) {
            throw new RuntimeException('The peer identity changed while it was being approved.');
        }
        $candidates = collect($this->discover($endpoint));
        $matchingFingerprints = $candidates
            ->where('reachable', true)
            ->where('system_identifier', $nodeId)
            ->pluck('host_fingerprint')->unique()->values();
        if ($matchingFingerprints->count() > 1) {
            throw new RuntimeException('This PostgreSQL system identifier answered from more than one endpoint. Approval is blocked.');
        }
        $writer = $this->managementWriter($candidates);
        if ($writer !== $this->localNodeId()) {
            throw new RuntimeException('Manage scheduled-job membership on node '.$writer.'.', 409);
        }

        return DB::transaction(function () use ($nodeId, $endpoint, $identity, $userUuid, $writer) {
            $this->lockOwnershipSettings();
            $this->assertManagementWriter($writer);
            $existing = ScheduledJobNode::query()->where('system_identifier', $nodeId)->first();
            if ($existing && (! hash_equals($existing->host_fingerprint, $identity['host_fingerprint']) || $existing->status === 'retired')) {
                throw new RuntimeException('A retired or changed host identity cannot be approved again. Register a replacement PostgreSQL cluster.');
            }
            if (ScheduledJobNode::query()->where('endpoint', $endpoint)->where('system_identifier', '!=', $nodeId)->where('status', 'approved')->exists()) {
                throw new RuntimeException('Retire the previous node before reusing this endpoint.');
            }

            return ScheduledJobNode::query()->updateOrCreate(['system_identifier' => $nodeId], [
                'host_fingerprint' => $identity['host_fingerprint'],
                'registered_on_node_id' => $existing?->registered_on_node_id ?: $writer,
                'hostname' => trim((string) ($identity['hostname'] ?? '')) ?: $endpoint,
                'endpoint' => $endpoint,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userUuid,
                'retired_at' => null,
                'retired_by' => null,
            ]);
        });
    }

    public function retireNode(ScheduledJobNode $node, ?string $userUuid): void
    {
        DB::transaction(function () use ($node, $userUuid) {
            $this->lockOwnershipSettings();
            $this->assertManagementWriter();
            [$owner] = $this->effectiveOwner($this->configuredNode());
            if ($owner === $node->system_identifier) {
                throw new RuntimeException('Transfer scheduled-job ownership before retiring this node.');
            }
            if (ScheduledJobHandoff::query()->where('to_node_id', $node->system_identifier)->whereIn('status', ['requested', 'draining'])->exists()) {
                throw new RuntimeException('This node is the target of a pending transfer. Complete the transfer before retiring it.', 409);
            }
            $node->forceFill(['status' => 'retired', 'retired_at' => now(), 'retired_by' => $userUuid])->save();
        });
    }

    /** @return array<string, mixed> */
    public function requestHandoff(string $targetNodeId, int $expectedGeneration, ?string $userUuid, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey && ($existing = $this->existingHandoff($idempotencyKey, $targetNodeId, $expectedGeneration))) {
            return $existing;
        }
        $target = ScheduledJobNode::query()->where('system_identifier', $targetNodeId)->where('status', 'approved')->firstOrFail();
        $this->assertIdentity($target);
        $decision = $this->resolve();
        if ($decision['source'] === 'inconsistent') {
            throw new RuntimeException($decision['reason'], 409);
        }
        if ($expectedGeneration !== (int) $decision['generation']) {
            throw new RuntimeException('Scheduled-job ownership changed. Refresh the page and try again.', 409);
        }
        if (($decision['effective_owner'] ?? null) === $targetNodeId) {
            if (($decision['legacy_match'] ?? false) === true) {
                return ['status' => 'completed', 'handoff' => $this->normalizeLegacyOwner($targetNodeId, $expectedGeneration, $userUuid)];
            }

            return ['status' => 'unchanged', 'handoff' => null];
        }

        $idempotency = $idempotencyKey ?: (string) Str::uuid();
        if (! Str::isUuid($idempotency)) {
            throw new RuntimeException('The handoff idempotency key is invalid.');
        }
        $payload = [
            'target_node_id' => $targetNodeId,
            'target_endpoint' => $target->endpoint,
            'expected_generation' => $expectedGeneration,
            'requested_by' => $userUuid,
            'idempotency_key' => $idempotency,
        ];
        $ownerId = $decision['effective_owner'] ?? null;
        if ($ownerId === null) {
            if ($this->configuredNode() !== null || $decision['source'] === 'inconsistent') {
                throw new RuntimeException('The existing owner is invalid. Repair the selection; it cannot be treated as a new installation.', 409);
            }
            return ['status' => 'completed', 'handoff' => $this->bootstrapOwner($payload)];
        }
        if ($ownerId === $this->localNodeId()) {
            return $this->prepareHandoff($payload);
        }

        $owner = ScheduledJobNode::query()->where('system_identifier', $ownerId)->where('status', 'approved')->first();
        if (! $owner) {
            throw new RuntimeException('The selected owner is not an approved node. Fence it before using forced takeover.', 409);
        }

        try {
            return $this->peerClient->prepareHandoff($owner->endpoint, $payload, $idempotency);
        } catch (RuntimeException $exception) {
            if ($exception->getCode() >= 400 && $exception->getCode() < 500) {
                throw $exception;
            }
            // The owner may have committed despite a lost response. The status
            // endpoint also accepts the original idempotency UUID.
            try {
                $recovered = $this->peerClient->handoffStatus($owner->endpoint, $idempotency);
                if (($recovered['handoff']['to_node_id'] ?? null) !== $targetNodeId
                    || ($recovered['handoff']['expected_generation'] ?? null) !== $expectedGeneration) {
                    throw new RuntimeException('The recovered response belongs to a different handoff.', 409);
                }

                return $recovered;
            } catch (Throwable) {
                throw $exception;
            }
        }
    }

    /** @return array<string, mixed> */
    public function prepareHandoff(array $payload): array
    {
        $targetNodeId = trim((string) ($payload['target_node_id'] ?? ''));
        $expectedGeneration = (int) ($payload['expected_generation'] ?? -1);
        $idempotency = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($existing = $this->existingHandoff($idempotency, $targetNodeId, $expectedGeneration)) {
            return $existing;
        }
        $localId = $this->localNodeId();
        [$owner] = $this->effectiveOwner($this->configuredNode());
        if ($localId === null || $owner !== $localId || $expectedGeneration !== $this->generation()) {
            throw new RuntimeException('This node is no longer the scheduled-job owner.', 409);
        }
        if (! Str::isUuid($idempotency)) {
            throw new RuntimeException('The handoff idempotency key is invalid.');
        }

        $target = ScheduledJobNode::query()->where('system_identifier', $targetNodeId)->where('status', 'approved')->first();
        if (! $target || $this->normalizeEndpoint((string) ($payload['target_endpoint'] ?? '')) !== $target->endpoint) {
            throw new RuntimeException('The requested target does not match an approved node.');
        }
        $this->assertIdentity($target);

        $handoff = DB::transaction(function () use ($payload, $localId, $targetNodeId, $expectedGeneration, $idempotency) {
            $this->lockOwnershipSettings();
            if ($existing = ScheduledJobHandoff::query()->where('idempotency_key', $idempotency)->first()) {
                $this->existingHandoff($idempotency, $targetNodeId, $expectedGeneration);
                return $existing;
            }
            $this->assertManagementWriter();
            [$lockedOwner] = $this->effectiveOwner($this->configuredNode());
            if ($lockedOwner !== $localId || $this->generation() !== $expectedGeneration) {
                throw new RuntimeException('Scheduled-job ownership changed before the handoff could begin.', 409);
            }
            $this->supersedeObsoleteHandoffs($localId, $expectedGeneration);
            if ($this->pendingHandoff($localId, $expectedGeneration)) {
                throw new RuntimeException('Another scheduled-job handoff is already pending.', 409);
            }

            return ScheduledJobHandoff::query()->create([
                'idempotency_key' => $idempotency,
                'from_node_id' => $localId,
                'to_node_id' => $targetNodeId,
                'expected_generation' => $expectedGeneration,
                'status' => 'draining',
                'forced' => false,
                'requested_by' => $payload['requested_by'] ?? null,
                'requested_at' => now(),
            ]);
        });

        if (in_array($handoff->status, ['requested', 'draining'], true)) {
            $handoff = $this->finalizeHandoff($handoff) ?? $handoff->fresh();
        }

        return ['status' => $handoff->status, 'handoff' => $this->handoffArray($handoff)];
    }

    public function finalizePendingHandoff(): ?ScheduledJobHandoff
    {
        $localId = $this->localNodeId();
        if ($localId === null || ! Schema::hasTable('scheduled_job_handoffs')) {
            return null;
        }
        $decision = $this->resolve();
        if (! $decision['active'] && $decision['status'] !== 'draining') {
            return null;
        }
        // No heartbeat: write only when a claim actually expires.
        if (ScheduledJobExecution::query()->where('node_id', $localId)->where('status', 'running')->where('expires_at', '<=', now())->exists()) {
            DB::transaction(function () use ($localId) {
                $this->lockOwnershipSettings();
                $decision = $this->resolve();
                if ($decision['active'] || $decision['status'] === 'draining') {
                    $this->expireExecutions($localId);
                }
            });
        }
        $handoff = ScheduledJobHandoff::query()->where('from_node_id', $localId)
            ->whereIn('status', ['requested', 'draining'])->orderBy('requested_at')->first();

        return $handoff ? $this->finalizeHandoff($handoff) : null;
    }

    public function forceHandoff(ScheduledJobHandoff $handoff, string $fencedEndpoint, ?string $userUuid): ScheduledJobHandoff
    {
        return $this->forceTakeover($handoff->to_node_id, $handoff->expected_generation, $fencedEndpoint, $userUuid);
    }

    public function forceTakeover(string $targetNodeId, int $expectedGeneration, string $fencedEndpoint, ?string $userUuid): ScheduledJobHandoff
    {
        $target = ScheduledJobNode::query()->where('system_identifier', $targetNodeId)->where('status', 'approved')->first();
        if (! $target) {
            throw new RuntimeException('The takeover target is not an approved node.');
        }
        if ($targetNodeId !== $this->localNodeId() || ! $this->matchesLocalHost($target)) {
            throw new RuntimeException('Open the takeover target directly to record a forced transfer.', 409);
        }
        $this->assertIdentity($target);
        [$ownerId] = $this->effectiveOwner($this->configuredNode());
        if ($ownerId === null || $ownerId === $targetNodeId) {
            throw new RuntimeException('There is no different current owner to fence.');
        }
        $owner = ScheduledJobNode::query()->where('system_identifier', $ownerId)->first();
        if (! $owner || rtrim(strtolower($owner->endpoint), '/') !== rtrim(strtolower($this->normalizeEndpoint($fencedEndpoint)), '/')) {
            throw new RuntimeException('The fenced server address does not match the current owner.');
        }

        return DB::transaction(function () use ($targetNodeId, $expectedGeneration, $fencedEndpoint, $userUuid, $ownerId) {
            $this->lockOwnershipSettings();
            [$lockedOwner] = $this->effectiveOwner($this->configuredNode());
            if ($this->generation() !== $expectedGeneration || $lockedOwner !== $ownerId) {
                throw new RuntimeException('Scheduled-job ownership changed. Refresh the page and try again.', 409);
            }
            // Preserve the original request and its actor. A forced transfer is
            // a separate audited event that supersedes any outstanding drains.
            ScheduledJobHandoff::query()->whereIn('status', ['requested', 'draining'])
                ->update(['status' => 'superseded', 'completed_at' => now(), 'message' => 'Superseded by a fenced takeover.']);
            $handoff = ScheduledJobHandoff::query()->create([
                'idempotency_key' => (string) Str::uuid(),
                'from_node_id' => $ownerId,
                'to_node_id' => $targetNodeId,
                'expected_generation' => $expectedGeneration,
                'status' => 'forced',
                'forced' => true,
                'fenced_endpoint' => $this->normalizeEndpoint($fencedEndpoint),
                'requested_by' => $userUuid,
                'forced_by' => $userUuid,
                'requested_at' => now(),
                'completed_at' => now(),
                'message' => 'The previous owner was confirmed fenced by a superadmin.',
            ]);
            $this->setOwnership($targetNodeId, $expectedGeneration + 1);

            return $handoff;
        }, 3);
    }

    public function claimExecution(string $jobType, string $jobKey, int $ttlSeconds, ?Closure $claim = null): ?ScheduledJobExecution
    {
        if (! Schema::hasTable('scheduled_job_executions')) {
            return null;
        }

        return DB::transaction(function () use ($jobType, $jobKey, $ttlSeconds, $claim) {
            $this->lockOwnershipSettings();
            $decision = $this->resolve();
            if (! $decision['active']) {
                return null;
            }
            $this->expireExecutions($decision['this_node']);
            if (ScheduledJobExecution::query()->where('job_type', $jobType)->where('job_key', $jobKey)
                ->where('status', 'running')->where('expires_at', '>', now())->exists()) {
                return null;
            }
            if ($claim && $claim() === false) {
                return null;
            }

            return ScheduledJobExecution::query()->create([
                'job_type' => $jobType, 'job_key' => $jobKey,
                'node_id' => $decision['this_node'],
                'ownership_generation' => $decision['generation'],
                'status' => 'running', 'started_at' => now(),
                'expires_at' => now()->addSeconds(max(60, $ttlSeconds)),
            ]);
        }, 3);
    }

    public function finishExecution(ScheduledJobExecution $execution, string $status = 'completed', ?string $message = null): void
    {
        try {
            DB::transaction(function () use ($execution, $status, $message) {
                $this->lockOwnershipSettings();
                $this->assertExecution($execution);
                ScheduledJobExecution::query()->whereKey($execution->getKey())->where('status', 'running')
                    ->update(['status' => $status, 'message' => $message, 'finished_at' => now()]);
            });
        } catch (RuntimeException $exception) {
            if ($exception->getCode() !== 409) {
                throw $exception;
            }
            // Revoked workers cannot overwrite expired or completed records.
            return;
        }
        $this->finalizePendingHandoff();
    }

    /**
     * Every coordinated database mutation must pass through this boundary.
     * Network reads belong outside it. This lock is LOCAL, not a distributed
     * mutex: only the selected owner writes, until its handoff replicates.
     */
    public function withExecution(ScheduledJobExecution $execution, Closure $write): mixed
    {
        return DB::transaction(function () use ($execution, $write) {
            $this->lockOwnershipSettings();
            $this->assertExecution($execution);
            $result = $write();
            // Reject a write batch that consumed the remainder of its deadline.
            $this->assertExecution($execution);

            return $result;
        });
    }

    public function assertExecution(ScheduledJobExecution $execution): void
    {
        $current = ScheduledJobExecution::query()->find($execution->getKey());
        $decision = $this->resolve();
        if (! $current || $current->status !== 'running' || $current->expires_at->lte(now())
            || $current->node_id !== $decision['this_node']
            || $current->ownership_generation !== $decision['generation']
            || (! $decision['active'] && $decision['status'] !== 'draining')) {
            throw new RuntimeException('Scheduled-job execution authorization expired or changed. No changes were committed.', 409);
        }
    }

    private function expireExecutions(string $nodeId): void
    {
        $ids = ScheduledJobExecution::query()->where('node_id', $nodeId)->where('status', 'running')
            ->where('expires_at', '<=', now())->pluck('scheduled_job_execution_uuid');
        if ($ids->isEmpty()) {
            return;
        }
        ScheduledJobExecution::query()->whereKey($ids)->update(['status' => 'expired', 'finished_at' => now(), 'message' => 'Execution authorization expired.']);
    }

    /** @return array<string, mixed> */
    public function statusContext(): array
    {
        $decision = $this->resolve();
        $reachability = $this->registeredNodeReachability($decision['this_node']);
        $executions = Schema::hasTable('scheduled_job_executions')
            ? ScheduledJobExecution::query()->where('status', 'running')->where('expires_at', '>', now())
                ->orderBy('started_at')->get()->map(fn (ScheduledJobExecution $execution) => [
                    'id' => $execution->scheduled_job_execution_uuid,
                    'job_type' => $execution->job_type,
                    'job_key' => $execution->job_key,
                    'node_id' => $execution->node_id,
                    'generation' => $execution->ownership_generation,
                    'started_at' => optional($execution->started_at)->toIso8601String(),
                    'expires_at' => optional($execution->expires_at)->toIso8601String(),
                ])->values()->all()
            : [];

        $nodes = $this->nodes()->map(fn (ScheduledJobNode $node) => [
                'id' => $node->system_identifier,
                'registry_uuid' => $node->getKey(),
                'hostname' => $node->hostname,
                'endpoint' => $node->endpoint,
                'status' => $node->status,
                'local' => $node->system_identifier === $decision['this_node'],
                'selected' => $node->system_identifier === ($decision['effective_owner'] ?? null),
                'reachable' => $reachability[$node->system_identifier] ?? null,
            ])->values();
        if ($decision['this_node'] && ! $nodes->contains('id', $decision['this_node'])) {
            $nodes->push([
                'id' => $decision['this_node'], 'hostname' => $this->hostname(),
                'endpoint' => $this->localEndpoint(), 'status' => 'unapproved',
                'local' => true, 'selected' => false, 'reachable' => true,
            ]);
        }

        return $decision + [
            'nodes' => $nodes->values()->all(),
            'secret_configured' => $this->coordinationSecretConfigured(),
            'executions' => $executions,
        ];
    }

    /** @return array<string, bool> */
    private function registeredNodeReachability(?string $localNodeId): array
    {
        return $this->nodes()->mapWithKeys(function (ScheduledJobNode $node) use ($localNodeId) {
            if ($node->status === 'retired') {
                return [$node->system_identifier => false];
            }
            if ($node->system_identifier === $localNodeId) {
                return [$node->system_identifier => $this->matchesLocalHost($node)];
            }
            if (! $this->coordinationSecretConfigured()) {
                return [$node->system_identifier => false];
            }

            $reachable = Cache::remember('scheduled-jobs:node-reachable:'.sha1($node->endpoint), 10, function () use ($node) {
                try {
                    $identity = $this->peerClient->identify($node->endpoint);

                    return $this->identityMatches($node, $identity);
                } catch (Throwable) {
                    return false;
                }
            });

            return [$node->system_identifier => (bool) $reachable];
        })->all();
    }

    protected function subscriptionDiscovery(): array
    {
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return ['readable' => true, 'endpoints' => [], 'subscriptions' => []];
            }
            $rows = DB::select('select subname, subenabled, subconninfo from pg_subscription');
            $subscriptions = [];
            $endpoints = [];
            foreach ($rows as $row) {
                $hosts = $this->hostsFromConninfo((string) ($row->subconninfo ?? ''));
                foreach ($hosts as $host) {
                    $endpoints[] = $this->endpointForHost($host);
                }
                $subscriptions[] = ['name' => (string) ($row->subname ?? ''), 'enabled' => (bool) ($row->subenabled ?? false), 'hosts' => $hosts];
            }

            return ['readable' => true, 'endpoints' => array_values(array_unique($endpoints)), 'subscriptions' => $subscriptions];
        } catch (Throwable $exception) {
            return ['readable' => false, 'endpoints' => [], 'subscriptions' => [], 'error' => $exception->getMessage()];
        }
    }

    /** @return array<int, string> */
    protected function hostsFromConninfo(string $conninfo): array
    {
        $hosts = [];
        if (preg_match_all('/(?:^|\s)(?:host|hostaddr)\s*=\s*(?:\'([^\']+)\'|"([^"]+)"|([^\s]+))/i', $conninfo, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = $match[1] ?: ($match[2] ?: ($match[3] ?? ''));
                foreach (explode(',', $value) as $host) {
                    $host = trim($host, " \t\n\r\0\x0B[]");
                    if ($host !== '') {
                        $hosts[] = $host;
                    }
                }
            }
        }

        if (preg_match('~^postgres(?:ql)?://([^/?#]+)~i', trim($conninfo), $uri)) {
            $authority = preg_replace('/^.*@/', '', $uri[1]);
            foreach (explode(',', (string) $authority) as $host) {
                $host = trim($host);
                if (preg_match('/^\[([^]]+)](?::\d+)?$/', $host, $ipv6)) {
                    $hosts[] = $ipv6[1];
                } else {
                    $hosts[] = preg_replace('/:\d+$/', '', $host);
                }
            }
        }

        return collect($hosts)
            ->map(fn ($host) => trim((string) $host, " \t\n\r\0\x0B[]"))
            ->filter(fn ($host) => $host !== '' && ! str_contains($host, '/')
                && (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^[a-z0-9._-]+$/i', $host)))
            ->unique()->values()->all();
    }

    protected function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if (! str_contains($endpoint, '://')) {
            $endpoint = 'https://'.$endpoint;
        }
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Enter a direct HTTPS node address.');
        }
        $host = strtolower((string) $parts['host']);
        $host = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host;

        return 'https://'.$host.(isset($parts['port']) ? ':'.(int) $parts['port'] : '');
    }

    /** @return Collection<int, ScheduledJobNode> */
    private function approvedNodes(): Collection
    {
        return $this->nodes()->where('status', 'approved')->values();
    }

    private function existingHandoff(string $key, string $target, int $generation): ?array
    {
        if (! Str::isUuid($key)) {
            throw new RuntimeException('The handoff idempotency key is invalid.');
        }
        $handoff = ScheduledJobHandoff::query()->where('idempotency_key', $key)->first();
        if (! $handoff) {
            return null;
        }
        if ($handoff->to_node_id !== $target || $handoff->expected_generation !== $generation) {
            throw new RuntimeException('This idempotency key belongs to a different ownership request.', 409);
        }

        return ['status' => $handoff->status, 'handoff' => $this->handoffArray($handoff)];
    }

    private function supersedeObsoleteHandoffs(string $owner, int $generation): void
    {
        ScheduledJobHandoff::query()->whereIn('status', ['requested', 'draining'])
            ->where(fn ($query) => $query->where('from_node_id', '!=', $owner)->orWhere('expected_generation', '!=', $generation))
            ->update(['status' => 'superseded', 'completed_at' => now(), 'message' => 'Ownership changed before this handoff completed.']);
    }

    public function coordinationSnapshot(): array
    {
        return [
            'owner' => $this->configuredNode(), 'generation' => $this->generation(),
            'problem' => $this->ownershipProblem(),
            'nodes' => $this->nodes()->sortBy('system_identifier')->map(fn ($node) => [
                'uuid' => $node->getKey(), 'id' => $node->system_identifier, 'endpoint' => $node->endpoint,
                'fingerprint' => $node->host_fingerprint, 'status' => $node->status,
                'writer' => $node->registered_on_node_id,
            ])->values()->all(),
            'settings' => DefaultSettings::query()->where('default_setting_category', self::SETTING_CATEGORY)
                ->whereIn('default_setting_subcategory', [self::ACTIVE_NODE_SETTING, self::GENERATION_SETTING])
                ->orderBy('default_setting_subcategory')->get(['default_setting_uuid', 'default_setting_subcategory', 'default_setting_value'])->toArray(),
        ];
    }

    public function verifyRejoin(): void
    {
        $snapshot = $this->coordinationSnapshot();
        if ($snapshot['problem'] || $snapshot['owner'] === null) {
            throw new RuntimeException('Ownership must be valid before verifying rejoin.');
        }
        $local = $this->approvedNodes()->firstWhere('system_identifier', $this->localNodeId());
        if (! $local || ! $this->matchesLocalHost($local)) {
            throw new RuntimeException('This host is not an approved node. Keep its scheduler and workers stopped.');
        }
        foreach ($this->approvedNodes() as $node) {
            $identity = $this->peerClient->identify($node->endpoint);
            if (! $this->identityMatches($node, $identity) || ($identity['coordination'] ?? null) !== $snapshot) {
                throw new RuntimeException('Node identity, membership or ownership has not converged. Keep the returning workers stopped.');
            }
        }
    }

    private function managementWriter(?Collection $candidates = null): string
    {
        [$owner] = $this->effectiveOwner($this->configuredNode());
        if ($owner !== null) {
            return $owner;
        }
        if ($this->nodes()->isNotEmpty()) {
            $writers = $this->nodes()->pluck('registered_on_node_id')->unique();
            if ($writers->count() !== 1 || blank($writers->first())) {
                throw new RuntimeException('Initial membership has conflicting writers. Repair replication before continuing.', 409);
            }

            return $writers->first();
        }
        $ids = ($candidates ?? collect($this->discover()))->where('reachable', true)
            ->filter(fn ($candidate) => ! ($candidate['duplicate_identity'] ?? true))
            ->pluck('system_identifier')->unique()->sort(SORT_STRING)->values();
        // Initial HA registration requires a reachable pair, not just whichever
        // server happens to see itself during a partition. Stale hints never vote.
        if ($ids->count() !== 2 || ! $ids->contains($this->localNodeId())) {
            throw new RuntimeException('Discover both nodes before the first approval. Initial HA registration requires a verified pair.', 409);
        }

        return $ids->first();
    }

    private function assertManagementWriter(?string $initialWriter = null): void
    {
        $writer = $this->nodes()->isEmpty() && $initialWriter ? $initialWriter : $this->managementWriter();
        if ($writer !== $this->localNodeId()) {
            throw new RuntimeException('Manage scheduled-job coordination on node '.$writer.'.', 409);
        }
        $local = $this->nodes()->firstWhere('system_identifier', $writer);
        if ($local && ! $this->matchesLocalHost($local)) {
            throw new RuntimeException('The local host does not match its approved identity.', 409);
        }
    }

    /** @return array{0: ?string, 1: bool} */
    private function effectiveOwner(?string $configured, ?Collection $nodes = null): array
    {
        if ($configured === null) {
            return [null, false];
        }
        $nodes ??= $this->approvedNodes();
        if ($nodes->contains('system_identifier', $configured)) {
            return [$configured, false];
        }
        $needle = strtolower(trim($configured, " \t\n\r\0\x0B./"));
        $matches = $nodes->filter(function (ScheduledJobNode $node) use ($needle) {
            return strtolower($node->hostname) === $needle
                || strtolower((string) parse_url($node->endpoint, PHP_URL_HOST)) === $needle;
        })->values();

        return $matches->count() === 1 ? [(string) $matches->first()->system_identifier, true] : [null, false];
    }

    private function pendingHandoff(string $ownerId, int $generation): ?ScheduledJobHandoff
    {
        return Schema::hasTable('scheduled_job_handoffs')
            ? ScheduledJobHandoff::query()->where('from_node_id', $ownerId)->where('expected_generation', $generation)
                ->whereIn('status', ['requested', 'draining'])->orderBy('requested_at')->first()
            : null;
    }

    private function finalizeHandoff(ScheduledJobHandoff $handoff): ?ScheduledJobHandoff
    {
        return DB::transaction(function () use ($handoff) {
            $this->lockOwnershipSettings();
            $this->assertManagementWriter();
            $handoff = ScheduledJobHandoff::query()->lockForUpdate()->find($handoff->getKey());
            if (! $handoff || ! in_array($handoff->status, ['requested', 'draining'], true)) {
                return $handoff;
            }
            [$owner] = $this->effectiveOwner($this->configuredNode());
            if ($owner !== $handoff->from_node_id || $this->generation() !== (int) $handoff->expected_generation) {
                $handoff->forceFill(['status' => 'superseded', 'message' => 'Ownership changed before this handoff completed.', 'completed_at' => now()])->save();

                return $handoff;
            }
            ScheduledJobExecution::query()->where('node_id', $handoff->from_node_id)
                ->where('status', 'running')
                ->where('expires_at', '<=', now())->update(['status' => 'expired', 'finished_at' => now(), 'message' => 'Execution claim expired while draining.']);
            if (ScheduledJobExecution::query()->where('node_id', $handoff->from_node_id)
                ->where('status', 'running')->exists()) {
                return $handoff;
            }

            $this->setOwnership($handoff->to_node_id, $this->generation() + 1);
            $handoff->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            return $handoff;
        }, 3);
    }

    private function bootstrapOwner(array $payload): ScheduledJobHandoff
    {
        $this->assertManagementWriter();
        // Both nodes must have received the exact membership/settings rows,
        // including UUIDs. A local row lock alone cannot elect a global writer.
        $snapshot = $this->coordinationSnapshot();
        foreach ($this->approvedNodes() as $node) {
            $identity = $this->peerClient->identify($node->endpoint);
            if (! $this->identityMatches($node, $identity) || ($identity['coordination'] ?? null) !== $snapshot) {
                throw new RuntimeException('Wait for both nodes to agree on membership and initial ownership settings before selecting the first owner.', 409);
            }
        }
        return DB::transaction(function () use ($payload, $snapshot) {
            $this->lockOwnershipSettings();
            $this->assertManagementWriter();
            if ($snapshot !== $this->coordinationSnapshot()) {
                throw new RuntimeException('Initial coordination state changed while verifying peers. Try again.', 409);
            }
            if ($this->configuredNode() !== null || $this->generation() !== (int) $payload['expected_generation']) {
                throw new RuntimeException('Scheduled-job ownership was initialized by another request.', 409);
            }
            $generation = $this->generation() + 1;
            $this->setOwnership($payload['target_node_id'], $generation);

            return ScheduledJobHandoff::query()->create([
                'idempotency_key' => $payload['idempotency_key'], 'from_node_id' => null,
                'to_node_id' => $payload['target_node_id'], 'expected_generation' => $generation - 1,
                'status' => 'completed', 'forced' => false, 'requested_by' => $payload['requested_by'] ?? null,
                'requested_at' => now(), 'completed_at' => now(), 'message' => 'Initial scheduled-job owner selected.',
            ]);
        }, 3);
    }

    private function normalizeLegacyOwner(string $targetNodeId, int $expectedGeneration, ?string $userUuid): ScheduledJobHandoff
    {
        return DB::transaction(function () use ($targetNodeId, $expectedGeneration, $userUuid) {
            $this->lockOwnershipSettings();
            $this->assertManagementWriter();
            [$owner, $legacyMatch] = $this->effectiveOwner($this->configuredNode());
            if ($owner !== $targetNodeId || ! $legacyMatch || $this->generation() !== $expectedGeneration) {
                throw new RuntimeException('The existing owner selection changed. Refresh the page and try again.', 409);
            }
            $this->setOwnership($targetNodeId, $expectedGeneration);

            return ScheduledJobHandoff::query()->create([
                'idempotency_key' => (string) Str::uuid(), 'from_node_id' => $targetNodeId,
                'to_node_id' => $targetNodeId, 'expected_generation' => $expectedGeneration,
                'status' => 'completed', 'forced' => false, 'requested_by' => $userUuid,
                'requested_at' => now(), 'completed_at' => now(),
                'message' => 'Existing hostname or IP owner selection confirmed and converted to a PostgreSQL system identifier.',
            ]);
        }, 3);
    }

    private function assertIdentity(ScheduledJobNode $node): void
    {
        $identity = $this->peerClient->identify($node->endpoint);
        if (! $this->identityMatches($node, $identity)) {
            throw new RuntimeException('The approved endpoint returned a different PostgreSQL or host identity.');
        }
    }

    private function identityMatches(ScheduledJobNode $node, array $identity): bool
    {
        return filled($node->host_fingerprint)
            && hash_equals($node->system_identifier, (string) ($identity['system_identifier'] ?? ''))
            && hash_equals($node->host_fingerprint, (string) ($identity['host_fingerprint'] ?? ''));
    }

    private function lockOwnershipSettings(): void
    {
        // Unlike row locks, this also serializes initialization when no setting
        // rows exist. All coordination write paths use this same lock order.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(1179861584, 1)');
        }
        $rows = DefaultSettings::query()->where('default_setting_category', self::SETTING_CATEGORY)
            ->whereIn('default_setting_subcategory', [self::ACTIVE_NODE_SETTING, self::GENERATION_SETTING])
            ->orderBy('default_setting_subcategory')->lockForUpdate()->get();
        if ($problem = $this->ownershipProblem($rows)) {
            throw new RuntimeException($problem, 409);
        }
    }

    private function ownershipSettings(): Collection
    {
        return DefaultSettings::query()->where('default_setting_category', self::SETTING_CATEGORY)
            ->whereIn('default_setting_subcategory', [self::ACTIVE_NODE_SETTING, self::GENERATION_SETTING])->get();
    }

    private function ownershipProblem(?Collection $rows = null): ?string
    {
        $rows ??= $this->ownershipSettings();
        if ($rows->groupBy('default_setting_subcategory')->contains(fn ($group) => $group->count() > 1)) {
            return __('Duplicate scheduled-job ownership settings must be repaired before coordination can run.');
        }
        if ($rows->contains(fn ($row) => ! filter_var($row->default_setting_enabled, FILTER_VALIDATE_BOOLEAN))) {
            return __('Scheduled-job ownership settings are disabled. Review Default Settings.');
        }
        $generation = $rows->firstWhere('default_setting_subcategory', self::GENERATION_SETTING)?->default_setting_value;
        $configured = $this->setting(self::ACTIVE_NODE_SETTING, $rows);
        if (($generation !== null && (! preg_match('/^\d+$/', (string) $generation) || strlen((string) $generation) > 18))
            || ($configured !== null && $generation === null)
            || ($configured === null && (int) $generation !== 0)) {
            return __('Scheduled-job owner and generation settings are inconsistent. Review Default Settings.');
        }

        return null;
    }

    private function setOwnership(string $nodeId, int $generation): void
    {
        $this->writeSetting(self::ACTIVE_NODE_SETTING, $nodeId, 'text', 'PostgreSQL system identifier of the approved server that owns coordinated scheduled jobs.');
        $this->writeSetting(self::GENERATION_SETTING, (string) $generation, 'numeric', 'Internal fencing generation for coordinated scheduled jobs.');
        Cache::forget('scheduled_jobs_settings');
    }

    private function writeSetting(string $subcategory, string $value, string $type, string $description): void
    {
        $setting = DefaultSettings::query()->firstOrNew(['default_setting_category' => self::SETTING_CATEGORY, 'default_setting_subcategory' => $subcategory]);
        $setting->default_setting_name = $type;
        $setting->default_setting_value = $value;
        $setting->default_setting_enabled = true;
        $setting->default_setting_description = $description;
        $setting->save();
    }

    private function setting(string $subcategory, ?Collection $rows = null): ?string
    {
        $values = $rows !== null
            ? $rows->where('default_setting_subcategory', $subcategory)
                ->filter(fn ($row) => filter_var($row->default_setting_enabled, FILTER_VALIDATE_BOOLEAN))
                ->pluck('default_setting_value')
            : DefaultSettings::query()->where('default_setting_category', self::SETTING_CATEGORY)
                ->where('default_setting_subcategory', $subcategory)->where('default_setting_enabled', true)
                ->pluck('default_setting_value');
        $value = $values->count() === 1 ? $values->first() : null;
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    private function localEndpoint(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $this->hostname();
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return 'https://'.$host.($port ? ':'.$port : '');
    }

    private function endpointForHost(string $host): string
    {
        return 'https://'.(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host);
    }

    /** @return array<string, mixed> */
    private function handoffArray(ScheduledJobHandoff $handoff): array
    {
        return [
            'id' => $handoff->scheduled_job_handoff_uuid,
            'from_node_id' => $handoff->from_node_id, 'to_node_id' => $handoff->to_node_id,
            'expected_generation' => $handoff->expected_generation, 'status' => $handoff->status,
            'forced' => $handoff->forced, 'message' => $handoff->message,
            'requested_at' => optional($handoff->requested_at)->toIso8601String(),
            'completed_at' => optional($handoff->completed_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function result(bool $active, string $status, string $source, string $reason, ?string $configured, ?string $thisNode, int $generation, array $subscriptions, array $extra = []): array
    {
        return array_merge([
            'active' => $active, 'status' => $status, 'source' => $source, 'reason' => $reason,
            'configured' => $configured, 'effective_owner' => null, 'this_node' => $thisNode,
            'generation' => $generation, 'clustered' => ! empty($subscriptions['endpoints']),
            'recognized' => true, 'subscription_discovery' => $subscriptions, 'handoff' => null,
        ], $extra);
    }
}
