<?php

namespace App\Services\CallFlow;

use App\Data\Api\V1\CallFlow\CallFlowBranchData;
use App\Data\Api\V1\CallFlow\CallFlowNodeData;
use App\Data\Api\V1\CallFlow\CallFlowSimulationData;
use App\Exceptions\ApiException;
use App\Models\BusinessHour;
use App\Models\Destinations;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Services\CallRoutingOptionsService;
use DateTimeImmutable;

/**
 * Walks the inbound-routing graph for a given (domain, phone_number, time)
 * and produces a tree of `CallFlowNodeData` showing every branch a call could
 * take. Terminal nodes are voicemail/hangup/external; interior nodes
 * (business_hours, time_condition, ivr, ring_group, extension) expose their
 * branches with labels so the caller can see "what happens now" and also
 * "what happens if the user presses 2 / no one answers / it's after hours".
 */
class CallFlowSimulator
{
    public function __construct(
        protected BusinessHoursEvaluator $businessHours,
        protected TimeConditionEvaluator $timeConditions,
        protected RingGroupStrategyEvaluator $ringGroups,
    ) {}

    public function simulate(
        string $domainUuid,
        string $phoneNumber,
        DateTimeImmutable $at,
        int $maxDepth = 20,
    ): CallFlowSimulationData {
        $domain = Domain::where('domain_uuid', $domainUuid)->first();
        if (! $domain) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid',
            );
        }

        $destination = $this->findDestination($domainUuid, $phoneNumber);
        if (! $destination) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'No phone number matching the supplied input was found for this domain.',
                'resource_missing',
                'phone_number',
            );
        }

        $timezone = get_local_time_zone($domainUuid) ?: 'UTC';
        $ctx = new CallFlowContext($domainUuid, $domain->domain_name, $at, $timezone, $maxDepth);

        $tree = $this->walkDestination($destination, $ctx);

        return new CallFlowSimulationData(
            object: 'call_flow_simulation',
            url: '/api/v1/domains/' . $domainUuid . '/call-flow/simulate',
            domain_uuid: $domainUuid,
            domain_name: $domain->domain_name,
            phone_number: $this->displayPhoneNumber($phoneNumber, $destination),
            evaluated_at: $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            timezone: $timezone,
            tree: $tree,
            warnings: $ctx->warnings,
        );
    }

    /**
     * Find the Destinations row that matches the supplied number. Tries a few
     * normalisations so callers can pass `+441225800810`, `01225800810`, or
     * `1225800810` and hit the same row.
     */
    private function findDestination(string $domainUuid, string $phoneNumber): ?Destinations
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);
        if ($digits === '' || $digits === null) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'phone_number must contain at least one digit.',
                'invalid_request',
                'phone_number',
            );
        }

        $candidates = collect([$digits])
            ->when(strlen($digits) > 1 && str_starts_with($digits, '0'), fn ($c) => $c->push(ltrim($digits, '0')));

        $rows = Destinations::where('domain_uuid', $domainUuid)->get();

        foreach ($rows as $row) {
            $storedRaw = preg_replace('/\D/', '', (string) $row->destination_number);
            $prefix = preg_replace('/\D/', '', (string) $row->destination_prefix);
            $storedFull = $prefix . $storedRaw;

            foreach ($candidates as $cand) {
                if ($cand === '' || $cand === null) {
                    continue;
                }
                if ($cand === $storedRaw || $cand === $storedFull) {
                    return $row;
                }
            }
        }

        return null;
    }

    private function displayPhoneNumber(string $input, Destinations $destination): string
    {
        if (str_starts_with(trim($input), '+')) {
            return trim($input);
        }
        $e164 = $destination->destination_number_e164;
        return $e164 !== '' ? $e164 : trim($input);
    }

    private function walkDestination(Destinations $destination, CallFlowContext $ctx): CallFlowNodeData
    {
        $label = $destination->destination_number_e164
            . ($destination->destination_description ? ' (' . $destination->destination_description . ')' : '');

        $root = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'inbound_did',
            label: $label,
            resource_uuid: $destination->destination_uuid,
            extension: null,
            metadata: [
                'destination_description' => $destination->destination_description,
            ],
            branches: [],
        );

        $service = new CallRoutingOptionsService($ctx->domainUuid);
        $options = $service->reverseEngineerDestinationActions($destination->destination_actions) ?? [];

        foreach ($options as $option) {
            if (! is_array($option) || empty($option['type'])) {
                continue;
            }
            $child = $this->walkOption($option, $ctx);
            $root->branches[] = new CallFlowBranchData(
                condition: 'enter',
                label: null,
                active: true,
                child: $child,
            );
        }

        if ($root->branches === []) {
            $root->branches[] = new CallFlowBranchData(
                condition: 'enter',
                label: 'no routing configured',
                active: true,
                child: $this->terminalNode($ctx, 'unresolved', 'No routing configured on this DID', null, null, [
                    'destination_actions' => $destination->destination_actions,
                ]),
            );
            $ctx->warn('DID ' . $destination->destination_number_e164 . ' has no decodable destination_actions');
        }

        return $root;
    }

    /**
     * Main type dispatch. $option shape is the output of
     * CallRoutingOptionsService's reverseEngineer* methods:
     *   ['type' => string|null, 'extension' => ?string, 'option' => ?string, 'name' => ?string]
     */
    public function walkOption(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        if ($ctx->depth >= $ctx->maxDepth) {
            $ctx->warn('max traversal depth (' . $ctx->maxDepth . ') reached');
            return $this->terminalNode($ctx, 'unresolved', 'max depth reached', null, null);
        }

        $type = (string) ($option['type'] ?? '');
        $targetId = $option['option'] ?? null;

        if ($ctx->hasVisited($type, $targetId)) {
            $ctx->warn('cycle detected at ' . $type . ':' . ($targetId ?? ''));
            return $this->terminalNode($ctx, 'cycle_detected', 'cycle: ' . $type, $targetId, $option['extension'] ?? null);
        }
        $ctx->markVisited($type, $targetId);
        $ctx->depth++;

        try {
            return match ($type) {
                'business_hours' => $this->walkBusinessHours($option, $ctx),
                'ivrs' => $this->walkIvr($option, $ctx),
                'ring_groups' => $this->walkRingGroup($option, $ctx),
                'extensions' => $this->walkExtension($option, $ctx),
                'voicemails' => $this->walkVoicemail($option, $ctx),
                'time_conditions' => $this->walkTimeCondition($option, $ctx),
                'hangup' => $this->terminalNode($ctx, 'hangup', 'Hang up'),
                'check_voicemail' => $this->terminalNode($ctx, 'check_voicemail', 'Check Voicemail (*98)'),
                'company_directory' => $this->terminalNode($ctx, 'company_directory', 'Company Directory'),
                'external' => $this->terminalNode($ctx, 'external', 'External: ' . ($option['extension'] ?? ''), null, $option['extension'] ?? null),
                'ai_agents' => $this->terminalNode($ctx, 'ai_agent', 'AI Agent ' . ($option['extension'] ?? ''), $targetId, $option['extension'] ?? null, ['name' => $option['name'] ?? null]),
                'conferences' => $this->terminalNode($ctx, 'conference', 'Conference ' . ($option['extension'] ?? ''), $targetId, $option['extension'] ?? null, ['name' => $option['name'] ?? null]),
                'contact_centers' => $this->terminalNode($ctx, 'contact_center', 'Contact Center ' . ($option['extension'] ?? ''), $targetId, $option['extension'] ?? null, ['name' => $option['name'] ?? null]),
                'faxes' => $this->terminalNode($ctx, 'fax', 'Fax ' . ($option['extension'] ?? ''), $targetId, $option['extension'] ?? null, ['name' => $option['name'] ?? null]),
                'call_flows' => $this->terminalNode($ctx, 'call_flow', 'Call Flow ' . ($option['extension'] ?? ''), $targetId, $option['extension'] ?? null, ['name' => $option['name'] ?? null]),
                'recordings' => $this->terminalNode($ctx, 'recording', 'Play Recording', $targetId, null, ['name' => $option['name'] ?? null]),
                default => $this->terminalNode($ctx, 'unresolved', 'Unresolved destination', $targetId, $option['extension'] ?? null, [
                    'raw_option' => $option,
                ]),
            };
        } finally {
            $ctx->depth--;
        }
    }

    private function walkBusinessHours(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $bh = BusinessHour::with(['periods', 'holidays'])
            ->where('uuid', $option['option'] ?? '')
            ->first();

        $node = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'business_hours',
            label: $bh ? ('Business Hours: ' . ($bh->name ?: $option['extension'] ?? '')) : ('Business Hours ' . ($option['extension'] ?? '')),
            resource_uuid: $bh?->uuid,
            extension: $option['extension'] ?? $bh?->extension ?? null,
            metadata: null,
            branches: [],
        );

        if (! $bh) {
            $ctx->warn('business_hours record not found: ' . ($option['option'] ?? ''));
            return $node;
        }

        $eval = $this->businessHours->evaluate($bh, $ctx->at);
        foreach ($eval['warnings'] as $w) {
            $ctx->warn($w);
        }
        $node->metadata = [
            'timezone' => $eval['timezone'],
            'is_in_hours' => $eval['is_in_hours'],
            'active_period' => $eval['period'] ? $this->describePeriod($eval['period']) : null,
            'active_holiday' => $eval['holiday'] ? (string) ($eval['holiday']->description ?? $eval['holiday']->uuid) : null,
        ];

        // In-hours branch: walk each period that exists (we pick one "active"
        // — the one that matches the current time if any).
        $periodTarget = $this->businessHoursPeriodTarget($bh, $eval['period'], $ctx);
        if ($periodTarget !== null) {
            $node->branches[] = new CallFlowBranchData(
                condition: 'in_hours',
                label: $eval['period'] ? $this->describePeriod($eval['period']) : 'in hours',
                active: $eval['is_in_hours'],
                child: $this->walkOption($periodTarget, $ctx),
            );
        }

        // After-hours branch.
        $afterHoursTarget = $this->businessHoursAfterHoursTarget($bh, $ctx);
        if ($afterHoursTarget !== null) {
            $node->branches[] = new CallFlowBranchData(
                condition: 'after_hours',
                label: 'after hours',
                active: ! $eval['is_in_hours'] && $eval['holiday'] === null,
                child: $this->walkOption($afterHoursTarget, $ctx),
            );
        }

        // Holiday branch, if any applies right now.
        if ($eval['holiday'] !== null) {
            $holidayTarget = $this->holidayTarget($eval['holiday'], $ctx);
            if ($holidayTarget !== null) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'holiday',
                    label: (string) ($eval['holiday']->description ?? 'holiday'),
                    active: true,
                    child: $this->walkOption($holidayTarget, $ctx),
                );
            }
        }

        return $node;
    }

    private function businessHoursPeriodTarget(BusinessHour $bh, $period, CallFlowContext $ctx): ?array
    {
        // Prefer the currently active period; fall back to the first period
        // that has a target so the in_hours branch still renders.
        $candidates = $period ? [$period] : [];
        foreach ($bh->periods as $p) {
            if ($period === null || $p->uuid !== $period->uuid) {
                $candidates[] = $p;
            }
        }
        foreach ($candidates as $p) {
            $target = $this->polymorphicTargetToOption($p->target_type ?? null, $p->target_id ?? null, $ctx);
            if ($target !== null) {
                return $target;
            }
        }
        return null;
    }

    private function businessHoursAfterHoursTarget(BusinessHour $bh, CallFlowContext $ctx): ?array
    {
        return $this->polymorphicTargetToOption(
            $bh->after_hours_target_type,
            $bh->after_hours_target_id,
            $ctx,
        );
    }

    private function holidayTarget($holiday, CallFlowContext $ctx): ?array
    {
        return $this->polymorphicTargetToOption($holiday->target_type ?? null, $holiday->target_id ?? null, $ctx);
    }

    /**
     * Translate a Laravel morphTo pair into a routing option array that
     * walkOption() can consume. Supports the BusinessHour target types.
     */
    private function polymorphicTargetToOption(?string $targetType, ?string $targetId, CallFlowContext $ctx): ?array
    {
        if (! $targetType || ! $targetId) {
            return null;
        }

        $class = class_basename($targetType);

        return match ($class) {
            'Extensions' => [
                'type' => 'extensions',
                'option' => $targetId,
                'extension' => $this->extensionNumberByUuid($targetId),
                'name' => null,
            ],
            'Voicemails' => [
                'type' => 'voicemails',
                'option' => $targetId,
                'extension' => null,
                'name' => null,
            ],
            'RingGroups' => [
                'type' => 'ring_groups',
                'option' => $targetId,
                'extension' => null,
                'name' => null,
            ],
            'IvrMenus' => [
                'type' => 'ivrs',
                'option' => $targetId,
                'extension' => null,
                'name' => null,
            ],
            'Dialplans' => [
                'type' => 'time_conditions',
                'option' => $targetId,
                'extension' => null,
                'name' => null,
            ],
            'Recordings' => ['type' => 'recordings', 'option' => $targetId, 'extension' => null, 'name' => null],
            'CallCenterQueues' => ['type' => 'contact_centers', 'option' => $targetId, 'extension' => null, 'name' => null],
            'Faxes' => ['type' => 'faxes', 'option' => $targetId, 'extension' => null, 'name' => null],
            'CallFlows' => ['type' => 'call_flows', 'option' => $targetId, 'extension' => null, 'name' => null],
            default => null,
        };
    }

    private function extensionNumberByUuid(string $uuid): ?string
    {
        $ext = Extensions::where('extension_uuid', $uuid)->first();
        return $ext?->extension;
    }

    private function describePeriod($period): string
    {
        $days = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];
        $dayName = $days[(int) $period->day_of_week] ?? (string) $period->day_of_week;
        return $dayName . ' ' . substr((string) $period->start_time, 0, 5) . '–' . substr((string) $period->end_time, 0, 5);
    }

    private function walkIvr(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $ivr = IvrMenus::with('options')
            ->where('ivr_menu_uuid', $option['option'] ?? '')
            ->first();

        $node = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'ivr',
            label: $ivr ? ('IVR: ' . $ivr->ivr_menu_name . ' (ext ' . $ivr->ivr_menu_extension . ')') : ('IVR ' . ($option['extension'] ?? '')),
            resource_uuid: $ivr?->ivr_menu_uuid,
            extension: $option['extension'] ?? $ivr?->ivr_menu_extension,
            metadata: $ivr ? [
                'timeout_sec' => (int) $ivr->ivr_menu_timeout,
            ] : null,
            branches: [],
        );

        if (! $ivr) {
            $ctx->warn('ivr_menu record not found: ' . ($option['option'] ?? ''));
            return $node;
        }

        $service = new CallRoutingOptionsService($ctx->domainUuid);

        foreach ($ivr->options as $opt) {
            if ((string) ($opt->ivr_menu_option_enabled ?? 'true') === 'false') {
                continue;
            }
            $action = (string) ($opt->ivr_menu_option_action ?? '');
            $param = (string) ($opt->ivr_menu_option_param ?? '');
            $full = trim($action . ' ' . $param);
            if ($full === '') {
                continue;
            }
            $parsed = $service->reverseEngineerIVROption($full);
            $child = $parsed && ! empty($parsed['type'])
                ? $this->walkOption($parsed, $ctx)
                : $this->terminalNode($ctx, 'unresolved', 'IVR option unresolved', null, null, ['raw' => $full]);

            $node->branches[] = new CallFlowBranchData(
                condition: 'press_' . (string) $opt->ivr_menu_option_digits,
                label: (string) ($opt->ivr_menu_option_description ?: ('press ' . $opt->ivr_menu_option_digits)),
                active: false,
                child: $child,
            );
        }

        // Timeout / invalid fallback: use ivr_menu_exit_app+data.
        $exitAction = trim(($ivr->ivr_menu_exit_app ?? '') . ' ' . ($ivr->ivr_menu_exit_data ?? ''));
        if ($exitAction !== '') {
            $parsed = $service->reverseEngineerIVROption($exitAction);
            if ($parsed && ! empty($parsed['type'])) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'timeout',
                    label: 'no input (timeout)',
                    active: false,
                    child: $this->walkOption($parsed, $ctx),
                );
            }
        }

        return $node;
    }

    private function walkRingGroup(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $group = RingGroups::with('destinations')
            ->where('ring_group_uuid', $option['option'] ?? '')
            ->first();

        if (! $group) {
            $ctx->warn('ring_group record not found: ' . ($option['option'] ?? ''));
            return $this->terminalNode($ctx, 'ring_group', 'Ring Group ' . ($option['extension'] ?? ''), $option['option'] ?? null, $option['extension'] ?? null);
        }

        return $this->ringGroups->expand($group, $ctx, fn (array $opt) => $this->walkOption($opt, $ctx));
    }

    private function walkExtension(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $ext = null;
        if (! empty($option['option'])) {
            $ext = Extensions::where('extension_uuid', $option['option'])->first();
        }
        if (! $ext && ! empty($option['extension'])) {
            $ext = Extensions::where('domain_uuid', $ctx->domainUuid)
                ->where('extension', $option['extension'])
                ->first();
        }

        $node = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'extension',
            label: $ext
                ? ('Extension: ' . $ext->extension . ($ext->effective_caller_id_name ? ' - ' . $ext->effective_caller_id_name : ''))
                : ('Extension ' . ($option['extension'] ?? '')),
            resource_uuid: $ext?->extension_uuid,
            extension: $ext?->extension ?? $option['extension'] ?? null,
            metadata: $ext ? [
                'call_timeout_sec' => (int) $ext->call_timeout,
                'enabled' => $ext->enabled,
                'do_not_disturb' => $ext->do_not_disturb,
            ] : null,
            branches: [],
        );

        if (! $ext) {
            return $node;
        }

        $service = new CallRoutingOptionsService($ctx->domainUuid);

        // forward_all takes precedence at runtime — emit it as a branch the
        // support agent can see is active if enabled.
        if ((string) $ext->forward_all_enabled === 'true' && filled($ext->forward_all_destination)) {
            $parsed = $service->reverseEngineerForwardAction($ext->forward_all_destination);
            if ($parsed && ! empty($parsed['type'])) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'forward_all',
                    label: 'forward-all (always)',
                    active: true,
                    child: $this->walkOption($parsed, $ctx),
                );
                return $node;
            }
        }

        if ((string) $ext->forward_busy_enabled === 'true' && filled($ext->forward_busy_destination)) {
            $parsed = $service->reverseEngineerForwardAction($ext->forward_busy_destination);
            if ($parsed && ! empty($parsed['type'])) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'busy',
                    label: 'on busy',
                    active: false,
                    child: $this->walkOption($parsed, $ctx),
                );
            }
        }

        // no_answer: explicit forward first, else default to personal voicemail
        // at *99<ext> if the extension has one (matches FusionPBX default).
        $noAnswerHandled = false;
        if ((string) $ext->forward_no_answer_enabled === 'true' && filled($ext->forward_no_answer_destination)) {
            $parsed = $service->reverseEngineerForwardAction($ext->forward_no_answer_destination);
            if ($parsed && ! empty($parsed['type'])) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'no_answer',
                    label: 'after ' . ((int) $ext->call_timeout) . 's no-answer',
                    active: false,
                    child: $this->walkOption($parsed, $ctx),
                );
                $noAnswerHandled = true;
            }
        }
        if (! $noAnswerHandled) {
            $vm = Voicemails::where('domain_uuid', $ctx->domainUuid)
                ->where('voicemail_id', $ext->extension)
                ->first();
            if ($vm) {
                $node->branches[] = new CallFlowBranchData(
                    condition: 'no_answer',
                    label: 'after ' . ((int) $ext->call_timeout) . 's → voicemail',
                    active: false,
                    child: $this->walkOption([
                        'type' => 'voicemails',
                        'option' => $vm->voicemail_uuid,
                        'extension' => $vm->voicemail_id,
                        'name' => null,
                    ], $ctx),
                );
            }
        }

        return $node;
    }

    private function walkVoicemail(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $vm = null;
        if (! empty($option['option'])) {
            $vm = Voicemails::where('voicemail_uuid', $option['option'])->first();
        }
        if (! $vm && ! empty($option['extension'])) {
            $vm = Voicemails::where('domain_uuid', $ctx->domainUuid)
                ->where('voicemail_id', $option['extension'])
                ->first();
        }

        return new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'voicemail',
            label: $vm ? ('Voicemail ' . $vm->voicemail_id) : ('Voicemail ' . ($option['extension'] ?? '')),
            resource_uuid: $vm?->voicemail_uuid,
            extension: $vm?->voicemail_id ?? $option['extension'] ?? null,
            metadata: null,
            branches: [],
        );
    }

    private function walkTimeCondition(array $option, CallFlowContext $ctx): CallFlowNodeData
    {
        $dialplan = null;
        if (! empty($option['option'])) {
            $dialplan = Dialplans::where('dialplan_uuid', $option['option'])->first();
        }

        $node = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'time_condition',
            label: $dialplan ? ('Schedule: ' . $dialplan->dialplan_name) : ('Schedule ' . ($option['extension'] ?? '')),
            resource_uuid: $dialplan?->dialplan_uuid,
            extension: $dialplan?->dialplan_number ?? $option['extension'] ?? null,
            metadata: null,
            branches: [],
        );

        if (! $dialplan || empty($dialplan->dialplan_xml)) {
            $ctx->warn('time_condition dialplan missing XML: ' . ($option['option'] ?? ''));
            return $node;
        }

        $service = new CallRoutingOptionsService($ctx->domainUuid);

        try {
            $evaluation = $this->timeConditions->evaluate($dialplan->dialplan_xml, $ctx->at, $ctx->timezone);
        } catch (TimeConditionParseException $e) {
            $ctx->warn('failed to parse time_condition XML: ' . $e->getMessage());
            return $node;
        }

        $node->metadata = [
            'matched_summary' => $evaluation['matched_summary'],
        ];

        if ($evaluation['matched_action']) {
            $child = $this->actionToOptionThenWalk($evaluation['matched_action'], $service, $ctx);
            $node->branches[] = new CallFlowBranchData(
                condition: 'time_match',
                label: $evaluation['matched_summary'] ?: 'matches current time',
                active: true,
                child: $child,
            );
        }

        if ($evaluation['fallback_action']) {
            $child = $this->actionToOptionThenWalk($evaluation['fallback_action'], $service, $ctx);
            $node->branches[] = new CallFlowBranchData(
                condition: 'time_no_match',
                label: 'fallthrough',
                active: $evaluation['matched_action'] === null,
                child: $child,
            );
        }

        return $node;
    }

    private function actionToOptionThenWalk(array $action, CallRoutingOptionsService $service, CallFlowContext $ctx): CallFlowNodeData
    {
        $app = $action['destination_app'] ?? '';
        $data = $action['destination_data'] ?? '';

        if ($app === 'transfer') {
            $parsed = $service->reverseEngineerIVROption(trim('transfer ' . $data));
        } elseif ($app === 'hangup') {
            $parsed = ['type' => 'hangup'];
        } else {
            $parsed = null;
        }

        if (! $parsed || empty($parsed['type'])) {
            return $this->terminalNode($ctx, 'unresolved', $app . ' ' . $data, null, null, ['raw' => $action]);
        }
        return $this->walkOption($parsed, $ctx);
    }

    public function terminalNode(
        CallFlowContext $ctx,
        string $type,
        string $label,
        ?string $resourceUuid = null,
        ?string $extension = null,
        ?array $metadata = null,
    ): CallFlowNodeData {
        return new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: $type,
            label: $label,
            resource_uuid: $resourceUuid,
            extension: $extension,
            metadata: $metadata,
            branches: [],
        );
    }
}
