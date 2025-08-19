<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'serial_number' => [
                'nullable',
                'string',
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
                'nullable',
                'string',
            ],
            'device_keys' => [
                'nullable',
                'array'
            ],
            // Required fields for each key:
            'device_keys.*.line_type_id' => ['required', 'string'],
            'device_keys.*.auth_id' => ['required', 'string'],
            'device_keys.*.line_number' => ['required', 'numeric'],

            // These fields can be null/empty:
            'device_keys.*.display_name' => ['nullable'],
            'device_keys.*.server_address' => ['nullable'],
            'device_keys.*.server_address_primary' => ['nullable'],
            'device_keys.*.server_address_secondary' => ['nullable'],
            'device_keys.*.sip_port' => ['nullable'],
            'device_keys.*.sip_transport' => ['nullable'],
            'device_keys.*.register_expires' => ['nullable'],
            'device_keys.*.device_line_uuid' => ['nullable'],
            
            'device_provisioning' => [
                'boolean'
            ],
            'domain_uuid' => [
                'required',
            ],
            'device_description' => [
                'nullable',
            ],
            'device_enabled' => [
                'nullable',
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        // Get the original error messages from the validator
        $errors = $validator->errors();

        // Check if the specific error for device_address_modified.unique exists
        if ($errors->has('device_address_modified')) {
            // Add the error to the device_address field instead
            $errors->add('device_address', $errors->first('device_address_modified'));

            // Optionally, remove the error from device_address_modified if it should only be reported under device_address
            $errors->forget('device_address_modified');
        }

        $responseData = array('errors' => $errors);

        throw new HttpResponseException(response()->json($responseData, 422));
    }

    public function messages(): array
    {
        return [
            'device_address.required' => 'MAC address is required',
            'device_address.mac_address' => 'MAC address is invalid',
            'device_profile_uuid.required' => 'Profile is required',
            'device_template.required' => 'Template is required',
            'device_address_modified.unique' => 'Duplicate MAC address has been found',
        ];
    }

    public function prepareForValidation(): void
    {
        $macAddress = strtolower(trim(tokenizeMacAddress($this->get('device_address') ?? '')));
        $this->merge([
            'device_address' => formatMacAddress($macAddress),
            'device_address_modified' => $macAddress
        ]);

        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        if (!$this->has('device_enabled')) {
            $this->merge(['device_enabled' => 'true']);
        }

        $serialInput = $this->get('serial_number');
        if ($serialInput !== null && $serialInput !== '') {
            // keep only [a–z0–9], lowercased
            $serialNorm = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$serialInput));
            // if becomes empty after normalization, store null
            $this->merge(['serial_number' => $serialNorm !== '' ? $serialNorm : null]);
        }
    }
}
