<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class AssignDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_address' => [
                'required',
                'mac_address',
            ],
            'device_address_modified' => [
                'nullable',
                Rule::unique('App\Models\Devices', 'device_address')
                    ->ignore($this->device_address_modified, 'device_address'),
            ],
            'device_profile_uuid' => [
                'nullable',
                Rule::when(
                    function ($input) {
                        // Check if the value is not the literal string "NULL"
                        return $input['device_profile_uuid'] !== 'NULL';
                    },
                    Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid'),                
                    )
            ],
            'device_template' => [
                'required',
                'string',
                Rule::notIn(['NULL']),
            ],
            'lines' => [
                'nullable',
                'array'
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']), // Ensures 'domain_uuid' is not 'NULL'
            ],
        ];
    }
}

