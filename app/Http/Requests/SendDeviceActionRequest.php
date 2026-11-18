<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SendDeviceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && userCheckPermission('device_edit');
    }

    public function rules(): array
    {
        return [
            'devices' => ['required', 'array', 'min:1'],
            'devices.*' => [
                'required',
                'uuid',
                'distinct',
            ],
        ];
    }
}
