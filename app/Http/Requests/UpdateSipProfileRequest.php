<?php

namespace App\Http\Requests;

class UpdateSipProfileRequest extends StoreSipProfileRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('sip_profile_edit');
    }

    public function rules(): array
    {
        $profile = $this->route('sip_profile');

        return $this->rulesForProfile($profile?->sip_profile_uuid);
    }
}
