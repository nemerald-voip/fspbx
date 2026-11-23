<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

trait ValidatesDeviceReferences
{
    private function deviceReferenceRules(): array
    {
        $domain = (string) $this->route('domain_uuid');
        $visible = fn ($query) => $query->where('domain_uuid', $domain)->orWhereNull('domain_uuid');

        return [
            'device_profile_uuid' => ['bail', 'nullable', 'uuid', Rule::exists('v_device_profiles', 'device_profile_uuid')->where($visible)],
            'device_template_uuid' => ['bail', 'nullable', 'uuid', Rule::exists('provisioning_templates', 'template_uuid')->where($visible)],
        ];
    }
}
