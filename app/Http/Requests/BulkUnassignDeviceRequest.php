<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUnassignDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('extension_device_unassign');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
            'extension_uuid' => ['required', 'uuid'],
        ];
    }
}
