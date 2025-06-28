<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDeviceRequest extends FormRequest
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

            // These fields must be present, but can be null/empty:
            'device_keys.*.display_name' => ['present'],
            'device_keys.*.server_address' => ['present'],
            'device_keys.*.server_address_primary' => ['present'],
            'device_keys.*.server_address_secondary' => ['present'],
            'device_keys.*.sip_port' => ['present'],
            'device_keys.*.sip_transport' => ['present'],
            'device_keys.*.register_expires' => ['present'],
            'device_keys.*.domain_uuid' => ['present'],
            'device_keys.*.device_line_uuid' => ['present'],
            
            'device_provisioning' => [
                'boolean'
            ],
            'domain_uuid' => [
                'required',
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
            'device_template.required' => 'Template is required',
            'device_address_modified.unique' => 'Duplicate MAC address has been found',
            'domain_uuid.required' => 'Acccount must be selected.',
            'device_keys.*.line_type_id.required' => 'The key type is required for each device key.',
            'device_keys.*.auth_id.required' => 'The extension/number is required for each device key.',
            'device_keys.*.line_number.required' => 'Key is required.',
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
    }
}
