<?php

namespace App\Http\Requests;

class UpdateCallFlowRequest extends StoreCallFlowRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_flow_edit');
    }

    protected function callFlowUuid(): ?string
    {
        $callFlow = $this->route('call_flow');

        return is_string($callFlow) ? $callFlow : $callFlow?->call_flow_uuid;
    }
}
