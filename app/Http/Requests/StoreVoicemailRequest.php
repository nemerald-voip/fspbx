<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreVoicemailRequest extends FormRequest
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
            'voicemail_id' => [
                'required',
                'numeric',
                new UniqueExtension,
            ],
            'voicemail_password' => 'numeric|digits_between:3,10',
            'voicemail_mail_to' => 'nullable|email:rfc,dns',
            'voicemail_enabled' => 'present',
            'voicemail_tutorial' => 'present',
            'voicemail_alternate_greet_id' => 'nullable|numeric',
            'voicemail_description' => 'nullable|string|max:100',
            'voicemail_transcription_enabled' => 'present',
            // 'voicemail_attach_file' => 'present',
            'voicemail_file' => 'present',
            'voicemail_local_after_email' => 'present',
            'extension' => "uuid",
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
    }

        /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'voicemail_id' => 'voicemail extension',
            'voicemail_password' => 'voicemail password',
            'greeting_id' => 'extension number',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
        ];
    }
}
