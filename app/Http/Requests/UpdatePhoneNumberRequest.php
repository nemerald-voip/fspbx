<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\NumberParseException as libNumberParseException;
use Propaganistas\LaravelPhone\Exceptions\NumberFormatException;
use Propaganistas\LaravelPhone\PhoneNumber;

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
            'destination_actions.*.destination_app' => [
                'nullable',
                Rule::in('transfer')
            ],
            'destination_actions.*.destination_data' => [
                'nullable',
                'string'
            ],
            'destination_conditions' => [
                'nullable',
                'array',
            ],
            'destination_conditions.*.condition_app' => [
                'nullable',
                Rule::in('transfer')
            ],
            'destination_conditions.*.condition_field' => [
                'nullable',
                'string'
            ],
            'destination_conditions.*.condition_expression' => [
                'nullable',
                'string'
            ],
            'destination_conditions.*.condition_data' => [
                'required_if:destination_conditions.*.condition_expression,!=,""',
                'string'
            ],
            'destination_cid_name_prefix' => [
                'nullable',
                'string',
            ],
            'destination_caller_id_name' => [
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
            'destination_enabled' => [
                Rule::in([true, false]),
            ],
            'destination_record' => [
                Rule::in([true, false]),
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
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        $destination_actions = [];
        if($this->has('destination_actions')) {
            foreach($this->get('destination_actions') as $action) {
                $destination_actions[] = [
                    'destination_app' => 'transfer',
                    'destination_data' => $action['value']['value'] ?? $action['destination_data'] ?? '',
                ];
            }
        }
        $destination_conditions = [];
        if($this->has('destination_conditions')) {
            foreach($this->get('destination_conditions') as $action) {
                $destination_conditions[] = [
                    'condition_field' => $action['condition_field']['value'] ?? $action['condition_field'] ?? '',
                    'condition_expression' => $action['condition_expression'] ?? null,
                    'condition_app' => 'transfer',
                    'condition_data' => $action['condition_data'][0]['value']['value'] ?? $action['condition_data'] ?? ''
                ];
            }
        }
        $this->merge([
            'destination_actions' => $destination_actions,
            'destination_conditions' => $destination_conditions
        ]);
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}

// [{"condition_app":"transfer","condition_field":"caller_id_number","condition_expression":"2038567463","condition_data":"152 XML api.us.nemerald.net"}]
// [{"condition_field":"caller_id_number","condition_expression":"2038567463","condition_app":"transfer","condition_data":"200 XML api.us.nemerald.net"}]
