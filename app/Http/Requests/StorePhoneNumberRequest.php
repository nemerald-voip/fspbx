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
                'phone:US'
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
            'destination_prefix.required' => 'Country code is required',
            'destination_number.required' => 'Should be valid US phone number',
            'destination_number.phone' => 'Should be valid US phone number',
            'destination_number.unique' => 'This phone number is already used',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        $phone = $this->get('destination_number');
        $prefix = $this->get('destination_prefix');
        $phone = preg_replace("/[^0-9]/", "", $prefix.$phone);
        try {
            $destination_number_regex = (new PhoneNumber(
                $phone,
                "US"
            ))->formatE164();
        } catch (NumberParseException $e) {
            $destination_number_regex = '';
        }
        $destination_number_regex = str_replace('+1', '', $destination_number_regex);
        try {
            $destination_caller_id_number = (new PhoneNumber(
                $phone,
                "US"
            ))->formatE164();
        } catch (NumberParseException $e) {
            $destination_caller_id_number = '';
        }

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
                    'condition_expression' => $action['condition_expression'] ?? '',
                    'condition_app' => 'transfer',
                    'condition_data' => $action['condition_data'][0]['value']['value'] ?? $action['condition_data'] ?? ''
                ];
            }
        }
        $this->merge([
            'destination_actions' => $destination_actions,
            'destination_conditions' => $destination_conditions,
            'destination_number' => $destination_number_regex,
            'destination_number_regex' => '^\+?'.$this->get('destination_prefix').'?('.$destination_number_regex.')$',
            'destination_caller_id_number' => $destination_caller_id_number
        ]);
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
