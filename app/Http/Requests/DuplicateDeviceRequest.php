<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class DuplicateDeviceRequest extends FormRequest
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
            'uuid' => [
                'required',
                'uuid',
                Rule::exists('v_devices', 'device_uuid'),
            ],
            'new_mac_address' => [
                'required',
                'mac_address',
            ],
            'new_mac_address_modified' => [
                'nullable',
                Rule::unique('v_devices', 'device_address'),
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
        $errors = $validator->errors();

        if ($errors->has('new_mac_address_modified')) {
            $errors->add('new_mac_address', $errors->first('new_mac_address_modified'));
            $errors->forget('new_mac_address_modified');
        }

        throw new HttpResponseException(response()->json(['errors' => $errors], 422));
    }

    public function messages(): array
    {
        return [
            'uuid.required' => 'Device is required',
            'uuid.uuid' => 'Device is invalid',
            'uuid.exists' => 'Device was not found',
            'new_mac_address.required' => 'MAC address is required',
            'new_mac_address.mac_address' => 'MAC address is invalid',
            'new_mac_address_modified.unique' => 'Duplicate MAC address has been found',
        ];
    }

    public function prepareForValidation(): void
    {
        $macAddress = strtolower(trim(tokenizeMacAddress($this->input('new_mac_address') ?? '')));

        $this->merge([
            'new_mac_address' => formatMacAddress($macAddress),
            'new_mac_address_modified' => $macAddress,
        ]);
    }
}
