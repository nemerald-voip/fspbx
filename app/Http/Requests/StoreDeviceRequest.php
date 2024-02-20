<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

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
            ],
            'device_profile_uuid' => [
                'nullable',
                Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            'device_template' => [
                'required',
                'string',
            ],
            'extension_uuid' => [
                'required',
                Rule::exists('App\Models\Extensions', 'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'device_address.required' => 'MAC address is required',
            'device_address.mac_address' => 'MAC address is invalid',
            'device_address_modified.unique' => 'This MAC address is already used',
            'device_profile_uuid.required' => 'Profile is required',
            'device_template.required' => 'Template is required',
            'extension_uuid.required' => 'Extension is required'
        ];
    }

    public function prepareForValidation(): void
    {
        $macAddress = tokenizeMacAddress($this->get('device_address'));
        $this->merge([
            'device_address' => normalizeMacAddress($macAddress),
            'device_address_modified' => $macAddress
        ]);
    }
}
