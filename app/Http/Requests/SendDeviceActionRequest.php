<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SendDeviceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'devices' => ['required', 'array', 'min:1'],
            'devices.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('v_devices', 'device_uuid'),
            ],
        ];
    }
}
