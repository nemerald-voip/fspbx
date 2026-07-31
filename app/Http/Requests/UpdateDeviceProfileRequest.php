<?php

namespace App\Http\Requests;

class UpdateDeviceProfileRequest extends StoreDeviceProfileRequest
{
    public function authorize(): bool
    {
        return auth()->check() && userCheckPermission('device_profile_edit');
    }
}
