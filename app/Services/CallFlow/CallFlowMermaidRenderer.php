<?php

namespace App\Services\CallFlow;

use App\Data\Api\V1\CallFlow\CallFlowNodeData;
use App\Data\Api\V1\CallFlow\CallFlowSimulationData;

/**
 * Renders a CallFlowSimulationData tree as a Mermaid `flowchart LR`
 * diagram. Pure function of the tree — no DB calls, no side effects.
 */
class CallFlowMermaidRenderer
{
    public function render(CallFlowSimulationData $sim): string
    {
        $nodeLines = [];
        $edgeLines = [];

        $this->walk($sim->tree, null, null, false, $nodeLines, $edgeLines);

        $body = implode("\n", array_merge(
            array_map(fn ($l) => '  ' . $l, $nodeLines),
            array_map(fn ($l) => '  ' . $l, $edgeLines),
        ));

        return "flowchart LR\n" . $body . "\n";
    }

    private function walk(
        CallFlowNodeData $node,
        ?string $parentId,
        ?string $edgeCondition,
        bool $edgeActive,
        array &$nodeLines,
        array &$edgeLines,
    ): void {
        $nodeLines[] = $node->node_id . '["' . $this->escapeLabel($this->composeLabel($node)) . '"]';

        if ($parentId !== null && $edgeCondition !== null) {
            $arrow = $edgeActive ? '==>' : '-->';
            $edgeLines[] = $parentId . ' ' . $arrow . '|' . $this->escapeLabel($edgeCondition) . '| ' . $node->node_id;
        }

        foreach ($node->branches as $branch) {
            $this->walk($branch->child, $node->node_id, $branch->label ?: $branch->condition, $branch->active, $nodeLines, $edgeLines);
        }
    }

    private function composeLabel(CallFlowNodeData $node): string
    {
        $label = $node->label;
        // Some node types already include the extension in the label; only
        // append when not already present.
        if ($node->extension !== null && ! str_contains($label, (string) $node->extension)) {
            $label .= ' (' . $node->extension . ')';
        }
        return $this->truncate($label, 80);
    }

    private function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max - 1) . '…';
    }

    private function escapeLabel(string $s): string
    {
        // Mermaid label-safe: drop double-quotes and backticks; collapse
        // whitespace; escape pipe which delimits edge labels.
        $s = preg_replace('/\s+/', ' ', $s);
        $s = str_replace(['"', '`'], ["'", "'"], $s);
        $s = str_replace('|', '/', $s);
        return trim($s);
    }
}
