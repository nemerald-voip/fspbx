<?php

namespace App\Http\Requests;

class UpdateBasicQueueAgentRequest extends StoreBasicQueueAgentRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_center_agent_edit');
    }

    protected function agentUuid(): ?string
    {
        $agent = $this->route('agent');

        return is_object($agent)
            ? $agent->call_center_agent_uuid
            : $agent;
    }
}
