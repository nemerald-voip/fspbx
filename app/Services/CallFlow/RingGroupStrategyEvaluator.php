<?php

namespace App\Services\CallFlow;

use App\Data\Api\V1\CallFlow\CallFlowBranchData;
use App\Data\Api\V1\CallFlow\CallFlowNodeData;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Services\CallRoutingOptionsService;
use Closure;

/**
 * Expands a ring group into a tree that reflects its ring strategy:
 *
 *  - sequence / rollover      → chained `ring_group_member` nodes, each
 *                               with its own `no_answer` branch pointing at
 *                               either the next member or the group exit.
 *  - simultaneous / enterprise → single `ring_group` node whose members are
 *                               recorded as metadata and one
 *                               `member_timeout` branch to the group exit.
 *  - random                   → `ring_group` node with members; order is
 *                               nondeterministic (warning attached).
 *
 * The caller provides a `walker` closure (the main simulator's walkOption)
 * so children can recurse back through the normal dispatch.
 */
class RingGroupStrategyEvaluator
{
    /**
     * @param Closure $walker  fn(array $option): CallFlowNodeData
     */
    public function expand(RingGroups $group, CallFlowContext $ctx, Closure $walker): CallFlowNodeData
    {
        $strategy = strtolower((string) ($group->ring_group_strategy ?? 'simultaneous'));
        $timeoutSec = (int) ($group->ring_group_call_timeout ?? 0);
        $members = $group->destinations
            ->sortBy(function ($m) {
                return [(int) ($m->destination_delay ?? 0), (string) $m->destination_number];
            })
            ->values();

        $groupLabel = 'Ring Group: ' . $group->ring_group_name . ' (ext ' . $group->ring_group_extension . ')';

        $groupExit = $this->groupExitOption($group);

        if (in_array($strategy, ['sequence', 'rollover', 'sequential'], true)) {
            return $this->buildSequential($group, $members, $groupExit, $ctx, $walker);
        }

        if ($strategy === 'random') {
            $ctx->warn('ring_group ' . $group->ring_group_extension . ' uses random strategy — member order is nondeterministic at runtime');
        }

        // simultaneous / enterprise / random: one node, parallel ring.
        $memberSummaries = [];
        foreach ($members as $m) {
            $memberSummaries[] = [
                'extension' => (string) $m->destination_number,
                'delay_sec' => (int) ($m->destination_delay ?? 0),
                'timeout_sec' => (int) ($m->destination_timeout ?: $timeoutSec),
                'prompt' => (string) ($m->destination_prompt ?? ''),
            ];
        }

        $node = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'ring_group',
            label: $groupLabel,
            resource_uuid: $group->ring_group_uuid,
            extension: $group->ring_group_extension,
            metadata: [
                'strategy' => $strategy,
                'call_timeout_sec' => $timeoutSec,
                'members' => $memberSummaries,
            ],
            branches: [],
        );

        if ($groupExit !== null) {
            $node->branches[] = new CallFlowBranchData(
                condition: 'member_timeout',
                label: 'all members ' . $timeoutSec . 's no-answer',
                active: false,
                child: $walker($groupExit),
            );
        }

        return $node;
    }

    private function buildSequential(RingGroups $group, $members, ?array $groupExit, CallFlowContext $ctx, Closure $walker): CallFlowNodeData
    {
        $timeoutSec = (int) ($group->ring_group_call_timeout ?? 0);

        $header = new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'ring_group',
            label: 'Ring Group: ' . $group->ring_group_name . ' (ext ' . $group->ring_group_extension . ', sequential)',
            resource_uuid: $group->ring_group_uuid,
            extension: $group->ring_group_extension,
            metadata: [
                'strategy' => 'sequence',
                'call_timeout_sec' => $timeoutSec,
            ],
            branches: [],
        );

        if ($members->isEmpty()) {
            if ($groupExit !== null) {
                $header->branches[] = new CallFlowBranchData(
                    condition: 'member_timeout',
                    label: 'no members',
                    active: false,
                    child: $walker($groupExit),
                );
            }
            return $header;
        }

        // Build the chain head-first so each member's no_answer branch points
        // at the next member, with the last member falling back to group exit.
        $head = null;
        $tail = null;

        foreach ($members as $m) {
            $memberTimeout = (int) ($m->destination_timeout ?: $timeoutSec);
            $memberNode = $this->buildMemberNode($group, $m, $ctx, $walker);
            $memberNode->metadata = array_merge($memberNode->metadata ?? [], [
                'timeout_sec' => $memberTimeout,
                'delay_sec' => (int) ($m->destination_delay ?? 0),
            ]);

            if ($head === null) {
                $head = $memberNode;
                $tail = $memberNode;
            } else {
                $tail->branches[] = new CallFlowBranchData(
                    condition: 'member_next',
                    label: 'no answer, next member',
                    active: false,
                    child: $memberNode,
                );
                $tail = $memberNode;
            }
        }

        // Final fallback from tail → group exit.
        if ($tail !== null && $groupExit !== null) {
            $tail->branches[] = new CallFlowBranchData(
                condition: 'member_timeout',
                label: 'all members exhausted',
                active: false,
                child: $walker($groupExit),
            );
        }

        $header->branches[] = new CallFlowBranchData(
            condition: 'enter',
            label: 'ring first member',
            active: true,
            child: $head,
        );

        return $header;
    }

    private function buildMemberNode(RingGroups $group, $member, CallFlowContext $ctx, Closure $walker): CallFlowNodeData
    {
        $destNumber = (string) $member->destination_number;

        // External SIP URI member (e.g. sip:alice@external.example) — terminal.
        if (str_contains($destNumber, '@') || str_starts_with($destNumber, 'sip:')) {
            return new CallFlowNodeData(
                node_id: $ctx->nextNodeId(),
                type: 'ring_group_member',
                label: 'External: ' . $destNumber,
                resource_uuid: null,
                extension: $destNumber,
                metadata: ['target_kind' => 'external'],
                branches: [],
            );
        }

        // Internal extension — walk through a shallow extension node. We
        // don't recurse into forward-all/busy to avoid exploding the tree
        // for deep org charts; we rely on the main extension walker for the
        // per-extension detail when the simulator targets an extension
        // directly. Here we just show that the member rings.
        $ext = Extensions::where('domain_uuid', $group->domain_uuid)
            ->where('extension', $destNumber)
            ->first();

        return new CallFlowNodeData(
            node_id: $ctx->nextNodeId(),
            type: 'ring_group_member',
            label: $ext
                ? ('Member ext ' . $ext->extension . ($ext->effective_caller_id_name ? ' - ' . $ext->effective_caller_id_name : ''))
                : ('Member ext ' . $destNumber),
            resource_uuid: $ext?->extension_uuid,
            extension: $destNumber,
            metadata: [
                'target_kind' => $ext ? 'extension' : 'unknown',
                'do_not_disturb' => $ext?->do_not_disturb,
                'enabled' => $ext?->enabled,
            ],
            branches: [],
        );
    }

    private function groupExitOption(RingGroups $group): ?array
    {
        $exitAction = trim(($group->ring_group_timeout_app ?? '') . ' ' . ($group->ring_group_timeout_data ?? ''));
        if ($exitAction === '') {
            return null;
        }
        $service = new CallRoutingOptionsService($group->domain_uuid);
        $parsed = $service->reverseEngineerRingGroupExitAction($exitAction);
        return ($parsed && ! empty($parsed['type'])) ? $parsed : null;
    }
}
