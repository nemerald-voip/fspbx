<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_uuid' => 'required',
            'line_number' => 'nullable',
        ];
    }
}
