<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDeviceRequest extends FormRequest
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

            'items' => 'required',
            
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
                'nullable',
                'string',
            ],
            // 'device_keys' => [
            //     'nullable',
            //     'array'
            // ],
            // // Required fields for each key:
            // 'device_keys.*.line_type_id' => ['required', 'string'],
            // 'device_keys.*.auth_id' => ['required', 'string'],
            // 'device_keys.*.line_number' => ['required', 'numeric'],

            // // These fields can be null/empty:
            // 'device_keys.*.display_name' => ['nullable'],
            // 'device_keys.*.server_address' => ['nullable'],
            // 'device_keys.*.server_address_primary' => ['nullable'],
            // 'device_keys.*.server_address_secondary' => ['nullable'],
            // 'device_keys.*.sip_port' => ['nullable'],
            // 'device_keys.*.sip_transport' => ['nullable'],
            // 'device_keys.*.register_expires' => ['nullable'],
            // 'device_keys.*.domain_uuid' => ['nullable'],
            // 'device_keys.*.device_line_uuid' => ['nullable'],
            // 'device_keys.*.user_id' => ['nullable'],
            
            'domain_uuid' => [
                'nullable',
            ],
            'device_description' => [
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'No items selected to update',
            'domain_uuid.required' => 'Acccount must be selected.',
        ];
    }

    public function prepareForValidation(): void
    {
        // logger($this);

    }
}
