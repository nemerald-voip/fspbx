<?php

namespace App\Http\Requests;

class UpdateBridgeRequest extends StoreBridgeRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('bridge_edit');
    }

    protected function bridgeUuid(): ?string
    {
        $bridge = $this->route('bridge');

        return is_object($bridge)
            ? $bridge->bridge_uuid
            : $bridge;
    }
}
