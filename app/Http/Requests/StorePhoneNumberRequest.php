<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class StorePhoneNumberRequest extends FormRequest
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
            'destination_number' => [
                'required',
                'phone:US',
                Rule::unique('App\Models\Destinations', 'destination_number')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            'destination_prefix' => [
                'required',
                Rule::in('1')
            ],
            'destination_number_regex' => [
                'required',
            ],
            'destination_caller_id_name' => [
                'nullable',
                'string',
            ],
            'destination_caller_id_number' => [
                'nullable',
                'phone:US',
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
    protected function failedValidation(Validator $validator): void
    {
        // Get the original error messages from the validator
        $errors = $validator->errors();

        $responseData = array('errors' => $errors);

        throw new HttpResponseException(response()->json($responseData, 422));
    }

    public function messages(): array
    {
        return [
            'destination_prefix.required' => 'Country code is required',
            'destination_number.required' => 'Phone number is required',
            'destination_number.phone' => 'Should be valid US phone number',
            'destination_number.unique' => 'This phone number is already used',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        try {
            $destination_number_regex = (new PhoneNumber(
                $this->get('destination_number'),
                "US"
            ))->formatE164();
        } catch (NumberParseException $e) {
            $destination_number_regex = '';
        }
        $destination_number_regex = str_replace('+1', '', $destination_number_regex);
        try {
            $destination_caller_id_number = (new PhoneNumber(
                $this->get('destination_number'),
                "US"
            ))->formatE164();
        } catch (NumberParseException $e) {
            $destination_caller_id_number = '';
        }
        $this->merge([
            'destination_number' => $destination_number_regex,
            'destination_number_regex' => '^('.$destination_number_regex.')$',
            'destination_caller_id_number' => $destination_caller_id_number
        ]);
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
