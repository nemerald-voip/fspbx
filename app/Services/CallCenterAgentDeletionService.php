<?php

namespace App\Services;

use App\Models\CallCenterAgents;
use App\Models\CallCenterQueueAgents;
use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CallCenterAgentDeletionService
{
    public function __construct(
        protected FreeswitchEslService $esl,
    ) {}

    public function delete(string $agentUuid, string $domainUuid, ?string $expectedContact = null): bool
    {
        return $this->deleteMatchingAgents([$agentUuid], $domainUuid, $expectedContact) > 0;
    }

    public function deleteMany(array $agentUuids, string $domainUuid): int
    {
        return $this->deleteMatchingAgents($agentUuids, $domainUuid);
    }

    private function deleteMatchingAgents(array $agentUuids, string $domainUuid, ?string $expectedContact = null): int
    {
        try {
            return DB::transaction(function () use ($agentUuids, $domainUuid, $expectedContact) {
                $agents = CallCenterAgents::where('domain_uuid', $domainUuid)
                    ->whereKey($agentUuids)->orderBy('call_center_agent_uuid')->lockForUpdate()->get();
                // A queued deletion must not follow an agent reassigned since dispatch.
                $agents = $agents->filter(fn ($agent) => $expectedContact === null || $agent->agent_contact === $expectedContact);
                if ($agents->isEmpty()) {
                    return 0;
                }

                $domainName = $agents->first()->domain()->value('domain_name');
                $queuesByAgent = [];
                foreach ($agents as $agent) {
                    $queues = $agent->queues()->where('v_call_center_queues.domain_uuid', $domainUuid)
                        ->wherePivot('domain_uuid', $domainUuid)->get();
                    $tiers = CallCenterQueueAgents::where('call_center_agent_uuid', $agent->getKey())->get();

                    // Refuse corrupt cross-account/orphan assignments before any runtime command.
                    if (! $domainName || $tiers->contains(fn ($tier) => $tier->domain_uuid !== $domainUuid
                        || ! $queues->contains('call_center_queue_uuid', $tier->call_center_queue_uuid))) {
                        throw new RuntimeException('Agent assignments do not belong to the selected account.');
                    }
                    $queuesByAgent[$agent->getKey()] = $queues;
                }

                $ha = class_exists(\Modules\ContactCenter\Services\Ha\HaSettings::class)
                    && app(\Modules\ContactCenter\Services\Ha\HaSettings::class)->enabled();
                if (! $ha) {
                if (! $this->esl->isConnected()) {
                    throw new RuntimeException('Unable to connect to FreeSWITCH. Agent was not deleted.');
                }

                FusionCache::clearPattern('configuration:callcenter.conf*');
                foreach ($queuesByAgent as $agentUuid => $queues) {
                    foreach ($queues as $queue) {
                        $queueName = $queue->queue_extension.'@'.$domainName;
                        $this->command('callcenter_config tier del '.$queueName.' '.$agentUuid);
                        $this->command('callcenter_config queue reload '.$queueName);
                    }
                }
                // Finish all reloads first: they can restore other agents in this batch.
                // Native agent deletion also removes tiers restored by queue reloads.
                foreach ($agents as $agent) {
                    $this->command('callcenter_config agent del '.$agent->getKey());
                }
                }

                $tiers = CallCenterQueueAgents::where('domain_uuid', $domainUuid)
                    ->whereIn('call_center_agent_uuid', $agents->modelKeys());
                $tiers->get()->each->delete();
                foreach ($agents as $agent) {
                    if (! $agent->delete()) {
                        throw new RuntimeException('Unable to delete the agent record.');
                    }
                }

                $queues = collect($queuesByAgent)->flatten(1)->unique('call_center_queue_uuid');
                DB::afterCommit(function () use ($queues, $domainUuid) {
                    // Reloads above can cache the pre-deletion XML. Invalidate it after commit.
                    FusionCache::clearPattern('configuration:callcenter.conf*');
                    if (class_exists(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class)) {
                        $realtime = app(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class);
                        foreach ($queues as $queue) {
                            $realtime->forgetQueue($queue->call_center_queue_uuid);
                        }
                        $realtime->forgetDomainQueues($domainUuid);
                    }
                });

                return $agents->count();
            });
        } finally {
            $this->esl->disconnect();
        }
    }

    protected function command(string $command): void
    {
        $response = $this->esl->executeCommand($command, false);
        if (! is_string($response) || ! preg_match('/^\+?OK\b/i', trim($response))) {
            throw new RuntimeException('FreeSWITCH could not complete agent deletion: '.
                (is_string($response) ? trim($response) : 'no response'));
        }
    }
}
