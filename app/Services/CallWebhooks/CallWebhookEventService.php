<?php

namespace App\Services\CallWebhooks;

use App\Models\CallCenterAgents;
use App\Models\CallCenterQueues;
use App\Models\CallWebhookSubscription;
use App\Models\Extensions;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class CallWebhookEventService
{
    private const STATE_TTL_SECONDS = 21600;
    private const LOOKUP_TTL_SECONDS = 600;

    private array $lookupCache = [];
    private int $lookupCacheWrites = 0;

    public function __construct(
        private CallWebhookDeliveryService $deliveryService,
        private CallWebhookSubscriptionRegistry $subscriptionRegistry,
    ) {
    }

    public function handle(object $event): void
    {
        if (! $this->subscriptionRegistry->hasAny()) {
            return;
        }

        $eventName = strtoupper($this->header($event, 'Event-Name'));
        $subclass = strtolower($this->header($event, 'Event-Subclass'));

        if ($eventName === 'CUSTOM' && $subclass === 'callcenter::info') {
            $this->handleQueueEvent($event);
            return;
        }

        if (in_array($eventName, ['CHANNEL_CREATE', 'CHANNEL_ANSWER', 'CHANNEL_HANGUP_COMPLETE'], true)) {
            $this->rememberInboundCall($event);
            $this->handleExtensionEvent($eventName, $event);
        }
    }

    private function handleExtensionEvent(string $eventName, object $event): void
    {
        if (strtolower($this->header($event, 'variable_call_direction')) !== 'inbound') {
            return;
        }

        if (strtolower($this->header($event, 'Call-Direction')) !== 'outbound') {
            return;
        }

        if ($this->isQueueAgentChannel($event)) {
            return;
        }

        $domainUuid = $this->resolveDomainUuid($event);
        if (! $domainUuid) {
            return;
        }

        $subscription = $this->subscription($domainUuid);
        if (! $subscription) {
            return;
        }

        $extension = $this->resolveExtension($event, $domainUuid);
        if (! $extension) {
            return;
        }

        $interactionId = $this->header($event, 'Channel-Call-UUID');
        $channelUuid = $this->firstHeader($event, ['Unique-ID', 'Caller-Unique-ID']);

        if ($interactionId === '' || $channelUuid === '') {
            return;
        }

        $stateKey = $this->stateKey($interactionId, 'extension', $extension->extension_uuid);
        $state = $this->cache()->get($stateKey, $this->newExtensionState($event, $domainUuid, $extension, $interactionId));
        $state['channel_uuid'] = $channelUuid;
        $state['channels'] ??= [];

        if ($eventName === 'CHANNEL_CREATE') {
            $state['channels'][$channelUuid] = true;
            $state['ringing_at'] ??= $this->eventTime($event);
            $this->storeState($stateKey, $state);
            $this->emit($subscription, CallWebhookSubscription::EVENT_RINGING, $state);
            return;
        }

        if ($eventName === 'CHANNEL_ANSWER') {
            $state['channels'][$channelUuid] = true;
            $state['answered_at'] ??= $this->eventTime($event);
            $this->storeState($stateKey, $state);
            $this->emit($subscription, CallWebhookSubscription::EVENT_ANSWERED, $state);
            return;
        }

        unset($state['channels'][$channelUuid]);
        $state['hangup_cause'] = $this->firstHeader($event, [
            'Hangup-Cause',
            'variable_hangup_cause',
            'Caller-Channel-Hangup-Cause',
        ]);

        if ($state['channels'] !== []) {
            $this->storeState($stateKey, $state);
            return;
        }

        $state['ended_at'] = $this->eventTime($event);
        $state['outcome'] = $this->extensionOutcome($state);
        $this->storeState($stateKey, $state);
        $this->emit($subscription, CallWebhookSubscription::EVENT_ENDED, $state);
    }

    private function handleQueueEvent(object $event): void
    {
        $action = strtolower($this->header($event, 'CC-Action'));

        if (in_array($action, ['member-queue-start', 'member-queue-resume'], true)) {
            $this->rememberQueueMember($event);
            return;
        }

        if (! in_array($action, [
            'agent-offering',
            'bridge-agent-start',
            'bridge-agent-end',
            'bridge-agent-fail',
        ], true)) {
            return;
        }

        $interactionId = $this->firstHeader($event, [
            'CC-Member-Session-UUID',
            'CC-Member-UUID',
        ]);
        $agentUuid = $this->header($event, 'CC-Agent');

        if ($interactionId === '' || $agentUuid === '') {
            return;
        }

        $memberState = $this->cache()->get($this->queueMemberKey($interactionId));
        if (! is_array($memberState) || ($memberState['direction'] ?? null) !== 'inbound') {
            return;
        }

        $agent = $this->rememberLookup(
            "agent:{$memberState['domain_uuid']}:{$agentUuid}",
            fn () => CallCenterAgents::query()
                ->where('domain_uuid', $memberState['domain_uuid'])
                ->where('call_center_agent_uuid', $agentUuid)
                ->first()
        );
        if (! $agent) {
            return;
        }

        $subscription = $this->subscription($agent->domain_uuid);
        if (! $subscription) {
            return;
        }

        $queue = $this->resolveQueue($event, $agent->domain_uuid);
        $extension = filled($agent->agent_id)
            ? $this->uniqueExtensionByNumber($agent->domain_uuid, (string) $agent->agent_id)
            : null;

        $stateKey = $this->stateKey($interactionId, 'queue_agent', $agentUuid);
        $state = $this->cache()->get(
            $stateKey,
            $this->newQueueAgentState($event, $memberState, $agent, $extension, $queue, $interactionId)
        );

        $agentChannelUuid = $this->header($event, 'CC-Agent-UUID');
        if ($agentChannelUuid !== '') {
            $state['channel_uuid'] = $agentChannelUuid;
        }

        if ($action === 'agent-offering') {
            $state['ringing_at'] ??= $this->eventTime($event, 'CC-Agent-Called-Time');
            $this->storeState($stateKey, $state);
            $this->emit($subscription, CallWebhookSubscription::EVENT_RINGING, $state);
            return;
        }

        if ($action === 'bridge-agent-start') {
            $state['answered_at'] ??= $this->eventTime($event, 'CC-Agent-Answered-Time');
            $this->storeState($stateKey, $state);
            $this->emit($subscription, CallWebhookSubscription::EVENT_ANSWERED, $state);
            return;
        }

        $state['ended_at'] = $this->eventTime($event, $action === 'bridge-agent-end'
            ? 'CC-Bridge-Terminated-Time'
            : 'CC-Agent-Aborted-Time');
        $state['hangup_cause'] = $this->header($event, 'CC-Hangup-Cause');
        if ($state['hangup_cause'] === '') {
            $state['hangup_cause'] = $this->firstHeader($event, ['Hangup-Cause', 'variable_hangup_cause']);
        }
        $state['outcome'] = $action === 'bridge-agent-end'
            ? 'completed'
            : $this->queueFailureOutcome($state['hangup_cause']);
        $this->storeState($stateKey, $state);
        $this->emit($subscription, CallWebhookSubscription::EVENT_ENDED, $state);
    }

    private function rememberQueueMember(object $event): void
    {
        $interactionId = $this->firstHeader($event, ['CC-Member-Session-UUID', 'CC-Member-UUID']);
        if ($interactionId === '') {
            return;
        }

        $inboundCall = $this->cache()->get($this->inboundCallKey($interactionId));
        if (! is_array($inboundCall) || ($inboundCall['direction'] ?? null) !== 'inbound') {
            return;
        }

        $domainUuid = (string) ($inboundCall['domain_uuid'] ?? '');
        $queue = $domainUuid !== '' ? $this->resolveQueue($event, $domainUuid) : null;
        if ($domainUuid === '' || ! $queue || ! $this->subscription($domainUuid)) {
            return;
        }

        $this->cache()->put($this->queueMemberKey($interactionId), [
            'direction' => 'inbound',
            'domain_uuid' => $domainUuid,
            'caller_name' => $this->firstHeader($event, ['CC-Member-CID-Name', 'Caller-Caller-ID-Name'])
                ?: ($inboundCall['caller_name'] ?? null),
            'caller_number' => $this->firstHeader($event, ['CC-Member-CID-Number', 'Caller-Caller-ID-Number'])
                ?: ($inboundCall['caller_number'] ?? null),
            'destination_number' => $this->firstHeader($event, [
                'CC-Member-DNIS',
                'Caller-Destination-Number',
                'variable_destination_number',
            ]) ?: $queue->queue_extension,
        ], now()->addSeconds(self::STATE_TTL_SECONDS));
    }

    private function rememberInboundCall(object $event): void
    {
        if (strtolower($this->header($event, 'variable_call_direction')) !== 'inbound') {
            return;
        }

        $domainUuid = $this->resolveDomainUuid($event);
        if (! $domainUuid || ! $this->subscription($domainUuid)) {
            return;
        }

        $context = [
            'direction' => 'inbound',
            'domain_uuid' => $domainUuid,
            'caller_name' => $this->header($event, 'Caller-Caller-ID-Name'),
            'caller_number' => $this->header($event, 'Caller-Caller-ID-Number'),
        ];

        foreach (array_unique(array_filter([
            $this->header($event, 'Channel-Call-UUID'),
            $this->header($event, 'variable_call_uuid'),
            $this->header($event, 'Unique-ID'),
            $this->header($event, 'Caller-Unique-ID'),
        ])) as $interactionId) {
            $this->cache()->put(
                $this->inboundCallKey($interactionId),
                $context,
                now()->addSeconds(self::STATE_TTL_SECONDS)
            );
        }
    }

    private function emit(
        CallWebhookSubscription $subscription,
        string $eventType,
        array $state
    ): void {
        if (! $subscription->accepts($eventType)) {
            return;
        }

        $targetUuid = $state['target']['call_center_agent_uuid']
            ?? $state['target']['extension_uuid']
            ?? null;
        if (! $targetUuid) {
            return;
        }

        $dedupeKey = 'call_webhook:emitted:' . hash('sha256', implode('|', [
            $state['interaction_id'],
            $state['target']['type'],
            $targetUuid,
            $eventType,
        ]));

        if (! $this->cache()->add($dedupeKey, true, now()->addSeconds(self::STATE_TTL_SECONDS))) {
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'id' => $eventId,
            'type' => $eventType,
            'occurred_at' => match ($eventType) {
                CallWebhookSubscription::EVENT_RINGING => $state['ringing_at'] ?? now('UTC')->toISOString(),
                CallWebhookSubscription::EVENT_ANSWERED => $state['answered_at'] ?? now('UTC')->toISOString(),
                default => $state['ended_at'] ?? now('UTC')->toISOString(),
            },
            'data' => [
                'interaction_id' => $state['interaction_id'],
                'channel_uuid' => $state['channel_uuid'] ?? null,
                'domain_uuid' => $state['domain_uuid'],
                'direction' => 'inbound',
                'caller' => [
                    'name' => $state['caller_name'] ?? null,
                    'number' => $state['caller_number'] ?? null,
                ],
                'destination_number' => $state['destination_number'] ?? null,
                'target' => $state['target'],
                'state' => [
                    'answered_at' => $state['answered_at'] ?? null,
                    'ended_at' => $state['ended_at'] ?? null,
                    'outcome' => $state['outcome'] ?? null,
                    'hangup_cause' => $state['hangup_cause'] ?: null,
                ],
            ],
        ];

        try {
            $this->deliveryService->dispatch($subscription, $payload);
        } catch (Throwable $exception) {
            $this->cache()->forget($dedupeKey);
            report($exception);
        }
    }

    private function newExtensionState(
        object $event,
        string $domainUuid,
        Extensions $extension,
        string $interactionId
    ): array {
        return [
            'interaction_id' => $interactionId,
            'domain_uuid' => $domainUuid,
            'caller_name' => $this->header($event, 'Caller-Caller-ID-Name'),
            'caller_number' => $this->header($event, 'Caller-Caller-ID-Number'),
            'destination_number' => $extension->extension,
            'channels' => [],
            'answered_at' => null,
            'ended_at' => null,
            'outcome' => null,
            'hangup_cause' => null,
            'target' => [
                'type' => 'extension',
                'extension_uuid' => $extension->extension_uuid,
                'extension' => $extension->extension,
                'call_center_agent_uuid' => null,
                'agent_id' => null,
                'agent_name' => null,
                'call_center_queue_uuid' => null,
                'queue_extension' => null,
                'queue_name' => null,
            ],
        ];
    }

    private function newQueueAgentState(
        object $event,
        array $memberState,
        CallCenterAgents $agent,
        ?Extensions $extension,
        ?CallCenterQueues $queue,
        string $interactionId
    ): array {
        return [
            'interaction_id' => $interactionId,
            'channel_uuid' => $this->header($event, 'CC-Agent-UUID') ?: null,
            'domain_uuid' => $agent->domain_uuid,
            'caller_name' => $this->firstHeader($event, ['CC-Member-CID-Name']) ?: ($memberState['caller_name'] ?? null),
            'caller_number' => $this->firstHeader($event, ['CC-Member-CID-Number']) ?: ($memberState['caller_number'] ?? null),
            'destination_number' => $memberState['destination_number'] ?? $queue?->queue_extension,
            'answered_at' => null,
            'ended_at' => null,
            'outcome' => null,
            'hangup_cause' => null,
            'target' => [
                'type' => 'queue_agent',
                'extension_uuid' => $extension?->extension_uuid,
                'extension' => $extension?->extension,
                'call_center_agent_uuid' => $agent->call_center_agent_uuid,
                'agent_id' => $agent->agent_id,
                'agent_name' => $agent->agent_name,
                'call_center_queue_uuid' => $queue?->call_center_queue_uuid,
                'queue_extension' => $queue?->queue_extension,
                'queue_name' => $queue?->queue_name,
            ],
        ];
    }

    private function subscription(string $domainUuid): ?CallWebhookSubscription
    {
        return $this->subscriptionRegistry->forDomainUuid($domainUuid);
    }

    private function resolveDomainUuid(object $event): ?string
    {
        $domainUuid = $this->header($event, 'variable_domain_uuid');
        if ($domainUuid !== '' && $this->subscription($domainUuid)) {
            return $domainUuid;
        }

        $domainName = $this->firstHeader($event, [
            'variable_domain_name',
            'variable_sip_invite_domain',
            'Caller-Context',
            'Channel-Context',
        ]);

        return $domainName === '' ? null : $this->subscriptionRegistry->domainUuidForName($domainName);
    }

    private function resolveExtension(object $event, string $domainUuid): ?Extensions
    {
        $extensionUuid = $this->header($event, 'variable_extension_uuid');
        if ($extensionUuid !== '') {
            $extension = $this->rememberLookup(
                "extension_uuid:{$domainUuid}:{$extensionUuid}",
                fn () => Extensions::query()
                    ->where('domain_uuid', $domainUuid)
                    ->where('extension_uuid', $extensionUuid)
                    ->first()
            );
            if ($extension) {
                return $extension;
            }
        }

        foreach ([
            'variable_dialed_extension',
            'Caller-Destination-Number',
            'Caller-Callee-ID-Number',
            'variable_sip_to_user',
        ] as $header) {
            $number = $this->header($event, $header);
            if ($number === '') {
                continue;
            }

            $extension = $this->uniqueExtensionByNumber($domainUuid, $number);
            if ($extension) {
                return $extension;
            }
        }

        return null;
    }

    private function resolveQueue(object $event, string $domainUuid): ?CallCenterQueues
    {
        $queueIdentifier = $this->header($event, 'CC-Queue');
        $queueExtension = explode('@', $queueIdentifier, 2)[0] ?? '';

        return $queueExtension === '' ? null : $this->rememberLookup(
            "queue:{$domainUuid}:{$queueExtension}",
            fn () => CallCenterQueues::query()
                ->where('domain_uuid', $domainUuid)
                ->where('queue_extension', $queueExtension)
                ->first()
        );
    }

    private function isQueueAgentChannel(object $event): bool
    {
        foreach ([
            'variable_cc_side',
            'variable_cc_agent',
            'variable_cc_member_uuid',
            'variable_cc_member_session_uuid',
            'variable_cc_queue',
            'variable_cc_agent_uuid',
            'CC-Agent',
        ] as $header) {
            if ($this->header($event, $header) !== '') {
                return true;
            }
        }

        return false;
    }

    private function extensionOutcome(array $state): string
    {
        if (! empty($state['answered_at'])) {
            return 'completed';
        }

        return $this->queueFailureOutcome((string) ($state['hangup_cause'] ?? ''));
    }

    private function queueFailureOutcome(string $cause): string
    {
        return match (strtoupper($cause)) {
            'NO_ANSWER', 'NO_USER_RESPONSE', 'ALLOTTED_TIMEOUT', 'RECOVERY_ON_TIMER_EXPIRE' => 'missed',
            'USER_BUSY' => 'busy',
            'CALL_REJECTED' => 'rejected',
            'ORIGINATOR_CANCEL', 'NORMAL_CLEARING' => 'canceled',
            default => 'failed',
        };
    }

    private function uniqueExtensionByNumber(string $domainUuid, string $number): ?Extensions
    {
        return $this->rememberLookup("extension_number:{$domainUuid}:{$number}", function () use ($domainUuid, $number) {
            $matches = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension', $number)
                ->limit(2)
                ->get();

            return $matches->count() === 1 ? $matches->first() : null;
        });
    }

    private function rememberLookup(string $key, callable $resolver): mixed
    {
        $cached = $this->lookupCache[$key] ?? null;
        if ($cached && $cached['expires_at'] > microtime(true)) {
            return $cached['value'];
        }

        $value = $resolver();
        $this->lookupCache[$key] = [
            'value' => $value,
            'expires_at' => microtime(true) + self::LOOKUP_TTL_SECONDS,
        ];
        $this->pruneLookupCachePeriodically();

        return $value;
    }

    private function pruneLookupCachePeriodically(): void
    {
        $this->lookupCacheWrites++;
        if (($this->lookupCacheWrites % 500) !== 0) {
            return;
        }

        $now = microtime(true);
        foreach ($this->lookupCache as $key => $cached) {
            if ($cached['expires_at'] <= $now) {
                unset($this->lookupCache[$key]);
            }
        }
    }

    private function eventTime(object $event, ?string $fallbackHeader = null): string
    {
        if ($fallbackHeader) {
            $seconds = $this->header($event, $fallbackHeader);
            if (ctype_digit($seconds) && (int) $seconds > 0) {
                return Carbon::createFromTimestamp((int) $seconds, 'UTC')->format('Y-m-d\TH:i:s.v\Z');
            }
        }

        $microseconds = $this->header($event, 'Event-Date-Timestamp');
        if (ctype_digit($microseconds) && (int) $microseconds > 0) {
            return Carbon::createFromTimestampMs((int) floor(((int) $microseconds) / 1000), 'UTC')
                ->format('Y-m-d\TH:i:s.v\Z');
        }

        return now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    private function storeState(string $key, array $state): void
    {
        $this->cache()->put($key, $state, now()->addSeconds(self::STATE_TTL_SECONDS));
    }

    private function stateKey(string $interactionId, string $targetType, string $targetUuid): string
    {
        return 'call_webhook:state:' . hash('sha256', "{$interactionId}|{$targetType}|{$targetUuid}");
    }

    private function queueMemberKey(string $interactionId): string
    {
        return 'call_webhook:queue_member:' . hash('sha256', $interactionId);
    }

    private function inboundCallKey(string $interactionId): string
    {
        return 'call_webhook:inbound_call:' . hash('sha256', $interactionId);
    }

    private function cache(): Repository
    {
        return Cache::store('redis');
    }

    private function firstHeader(object $event, array $headers): string
    {
        foreach ($headers as $header) {
            $value = $this->header($event, $header);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function header(object $event, string $name): string
    {
        if (! method_exists($event, 'getHeader')) {
            return '';
        }

        return trim((string) ($event->getHeader($name) ?? ''));
    }
}
