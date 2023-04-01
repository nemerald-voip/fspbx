<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_mac_address' => [
                'required',
                'mac_address'
            ],
            'device_profile' => [
                'required',
                Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            'device_template' => 'required',
            'extension_uuid' => [
                'required',
                Rule::exists('App\Models\Extensions', 'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
        ];
    }
}
