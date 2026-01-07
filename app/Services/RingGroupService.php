<?php

namespace App\Services;

class RingGroupService
{
    /**
     * Helper function to build destination action based on exit action.
     */
    private function buildForwardDestinationTarget($payload)
    {
        switch ($payload['forward_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'conferences':
            case 'faxes':
            case 'call_flows':
                return  $payload['forward_target'];
            case 'voicemails':
                return '*99' . $payload['forward_target'];
                // Add other cases as necessary for different types
            case 'external':
                return $payload['forward_external_target'] ?? $payload['forward_target'];
            default:
                return null;
        }
    }

    /**
     * Helper function to build destination action based on exit action.
     */
    private function buildExitDestinationAction($payload, $domain_name)
    {
        switch ($payload['timeout_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'conferences':
            case 'call_flows':
                return  ['action' => 'transfer', 'data' => $payload['timeout_target'] . ' XML ' . $domain_name];
            case 'voicemails':
                return ['action' => 'transfer', 'data' => '*99' . $payload['timeout_target'] . ' XML ' . $domain_name];

            case 'recordings':
                // Handle recordings with 'lua' destination app
                return ['action' => 'lua', 'data' => 'streamfile.lua ' . $payload['timeout_target']];

            case 'check_voicemail':
                return ['action' => 'transfer', 'data' => '*98 XML ' . $domain_name];

            case 'company_directory':
                return ['action' => 'transfer', 'data' => '*411 XML ' . $domain_name];

            case 'hangup':
                return ['action' => 'hangup', 'data' => ''];

                // Add other cases as necessary for different types
            default:
                return [];
        }
    }

    public function buildUpdateData(array $validated, $domain_name): array
    {
        $updateData = $validated;

        $updateData['ring_group_call_timeout'] = $this->calculateTimeout($validated);

        if (array_key_exists('timeout_action', $validated)) {
            $timeout = $this->buildExitDestinationAction($validated, $domain_name);
            $updateData['ring_group_timeout_app']  = $timeout['action'] ?? null;
            $updateData['ring_group_timeout_data'] = $timeout['data'] ?? null;
        }

        $forwardEnabled = ($validated['ring_group_forward_enabled'] ?? 'false') === 'true';

        if ($forwardEnabled && array_key_exists('forward_action', $validated)) {
            $updateData['ring_group_forward_destination'] = $this->buildForwardDestinationTarget($validated);
        }

        if (array_key_exists('missed_call_notifications', $validated)) {
            $missedEnabled = ($validated['missed_call_notifications'] === true);

            $updateData['ring_group_missed_call_app']  = $missedEnabled ? 'email' : null;
            $updateData['ring_group_missed_call_data'] = $missedEnabled
                ? ($validated['ring_group_missed_call_data'] ?? null)
                : null;
        }

        return $updateData;
    }

    private function calculateTimeout(array $validated): int
    {
        $enabledMembers = array_filter($validated['members'] ?? [], fn ($m) => !empty($m['enabled']));

        if (in_array($validated['ring_group_strategy'] ?? '', ['random', 'sequence', 'rollover'], true)) {
            return array_reduce($enabledMembers, fn ($carry, $m) => $carry + (int) ($m['timeout'] ?? 0), 0);
        }

        $max = 0;
        foreach ($enabledMembers as $m) {
            $max = max($max, (int) ($m['delay'] ?? 0) + (int) ($m['timeout'] ?? 0));
        }
        return $max;
    }
}
