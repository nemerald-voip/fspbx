<?php

namespace App\Services;

class CallCenterAgentRuntimeStatus
{
    public static function normalize(array $agent): array
    {
        $state = $agent['state'] ?? '';
        $loginStatus = $agent['status'] ?? '';
        $status = match ($state) {
            'Receiving' => 'Receiving',
            'In a queue call' => 'On a Call',
            default => $loginStatus,
        };
        $externalCalls = max(0, (int) ($agent['external_calls_count'] ?? 0));
        $activity = $externalCalls > 0 && ! in_array($state, ['Receiving', 'In a queue call'], true)
            ? 'non_queue_call' : null;

        return [
            'name' => $agent['name'] ?? '',
            'status' => $status,
            'state' => $state,
            'login_status' => $loginStatus,
            'external_calls_count' => $externalCalls,
            'activity' => $activity,
            'activity_label' => $activity ? __('On a non-queue call') : null,
            'last_status_change' => $agent['last_status_change'] ?? null,
            'talk_time' => $agent['talk_time'] ?? null,
            'calls_answered' => $agent['calls_answered'] ?? null,
        ];
    }
}
