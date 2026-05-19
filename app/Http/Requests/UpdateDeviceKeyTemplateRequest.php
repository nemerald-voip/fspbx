<?php

namespace App\Http\Requests;

class UpdateDeviceKeyTemplateRequest extends StoreDeviceKeyTemplateRequest
{
    public function authorize(): bool
    {
        return auth()->check() && userCheckPermission('device_key_template_update');
    }
}
