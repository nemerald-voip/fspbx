<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_mac_address' => 'required',
            'device_label' => 'required',
            'device_vendor' => 'required',
            'device_template' => 'nullable',
            'device_description' => 'nullable',
        ];
    }
}
