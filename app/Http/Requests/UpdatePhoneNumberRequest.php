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
            'destination_conditions.*.condition_data' => 'Please select condition action',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        $destination_actions = null;
        if($this->filled('destination_actions') && is_array($this->filled('destination_actions'))) {
            foreach($this->get('destination_actions') as $action) {
                $destination_actions[] = [
                    'destination_app' => 'transfer',
                    'destination_data' => $action['value']['value'] ?? $action['destination_data'] ?? '',
                ];
            }
        }
        $destination_conditions = null;
        if($this->filled('destination_conditions') && is_array($this->get('destination_conditions'))) {
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
