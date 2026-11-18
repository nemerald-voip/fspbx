<?php

namespace App\Http\Requests;

use App\Services\ProvisioningTemplateAccessService;

class UpdateProvisioningTemplateRequest extends StoreProvisioningTemplateRequest
{
    public function authorize(): bool
    {
        $access = app(ProvisioningTemplateAccessService::class);
        abort_unless($access->canModify($this->route('provisioning_template'), 'update'), 404);

        return $this->canSetSharing();
    }
}
