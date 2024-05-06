<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
                    ->ignore($this->device_address_modified, 'device_address')
                    ->where('domain_uuid', Session::get('domain_uuid')),
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
            ],
            'extension' => [
                'nullable',
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']), // Ensures 'domain_uuid' is not 'NULL'
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
            'domain_uuid.not_in' => 'Company must be selected.'
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
