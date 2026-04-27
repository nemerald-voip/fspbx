<?php

namespace Tests\Unit\CallFlow;

use App\Data\Api\V1\CallFlow\CallFlowBranchData;
use App\Data\Api\V1\CallFlow\CallFlowNodeData;
use App\Data\Api\V1\CallFlow\CallFlowSimulationData;
use App\Services\CallFlow\CallFlowMermaidRenderer;
use Tests\TestCase;

class CallFlowMermaidRendererTest extends TestCase
{
    public function test_renders_simple_tree(): void
    {
        $vm = new CallFlowNodeData('n3', 'voicemail', 'Voicemail 810', null, '810', null, []);
        $bh = new CallFlowNodeData('n2', 'business_hours', 'Business Hours: Main', null, '9200', null, [
            new CallFlowBranchData('after_hours', 'after hours', true, $vm),
        ]);
        $root = new CallFlowNodeData('n1', 'inbound_did', 'Inbound +441225800810', null, null, null, [
            new CallFlowBranchData('enter', null, true, $bh),
        ]);
        $sim = new CallFlowSimulationData(
            object: 'call_flow_simulation',
            url: '/x',
            domain_uuid: 'd',
            domain_name: 'test',
            phone_number: '+441225800810',
            evaluated_at: '2026-04-23T19:00:00Z',
            timezone: 'Europe/London',
            tree: $root,
            warnings: [],
        );

        $out = (new CallFlowMermaidRenderer())->render($sim);

        $this->assertStringStartsWith("flowchart LR\n", $out);
        $this->assertStringContainsString('n1["Inbound +441225800810"]', $out);
        $this->assertStringContainsString('n3["Voicemail 810 (810)"]', $out);
        $this->assertStringContainsString('n1 ==>|enter| n2', $out);
        $this->assertStringContainsString('n2 ==>|after hours| n3', $out);
    }

    public function test_pipe_in_label_is_escaped(): void
    {
        $node = new CallFlowNodeData('n1', 'inbound_did', 'a|b|c', null, null, null, []);
        $sim = new CallFlowSimulationData(
            'call_flow_simulation', '/x', 'd', null, '+1', 'now', 'UTC', $node, [],
        );
        $out = (new CallFlowMermaidRenderer())->render($sim);
        $this->assertStringContainsString('a/b/c', $out);
    }

    public function test_inactive_branches_use_thin_arrow(): void
    {
        $child = new CallFlowNodeData('n2', 'hangup', 'Hang up', null, null, null, []);
        $root = new CallFlowNodeData('n1', 'inbound_did', 'In', null, null, null, [
            new CallFlowBranchData('press_1', 'press 1', false, $child),
        ]);
        $sim = new CallFlowSimulationData(
            'call_flow_simulation', '/x', 'd', null, '+1', 'now', 'UTC', $root, [],
        );
        $out = (new CallFlowMermaidRenderer())->render($sim);
        $this->assertStringContainsString('n1 -->|press 1| n2', $out);
    }
}
