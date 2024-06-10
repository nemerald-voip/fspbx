<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdatePhoneNumberRequest extends FormRequest
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
            'destination_accountcode' => [
                'nullable',
                'string',
            ],
            'destination_actions' => [
                'nullable',
                'array',
            ],
            'destination_actions.*.value.value' => [
                'nullable',
                'string'
            ],
            'destination_conditions' => [
                'nullable',
                'array',
            ],
            'destination_conditions.*.condition_field' => [
                'nullable',
                Rule::in('caller_id_number')
            ],
            'destination_conditions.*.condition_expression' => [
                'required_if:destination_conditions.*.condition_field,!=,""',
                'phone:US'
            ],
            'destination_conditions.*.value.value' => [
                'required_if:destination_conditions.*.condition_field,!=,""',
                'string',
            ],
            'destination_cid_name_prefix' => [
                'nullable',
                'string',
            ],
            'destination_caller_id_name' => [
                'nullable',
                'string',
            ],
            'destination_hold_music' => [
                'nullable',
                'string',
            ],
            'destination_description' => [
                'nullable',
                'string',
            ],
            'destination_distinctive_ring' => [
                'nullable',
                'string',
            ],
            'fax_uuid' => [
                'nullable',
                Rule::exists('v_fax', 'fax_uuid')
            ],
            'destination_enabled' => [
                Rule::in([true, false]),
            ],
            'destination_record' => [
                Rule::in([true, false]),
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']),
                Rule::exists('v_domains', 'domain_uuid')
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
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
            'destination_conditions.*.condition_expression' => 'Should be valid US phone number',
            'destination_conditions.*.value.value' => 'Please select condition action',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
