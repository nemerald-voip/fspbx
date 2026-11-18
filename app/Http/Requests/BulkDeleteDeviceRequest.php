<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('device_delete');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
