<?php

namespace App\Http\Requests;

use App\Models\Domain;
use App\Models\Faxes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class BulkUpdatePhoneNumberRequest extends FormRequest
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
                Rule::when(
                    function ($input) {
                        // Check if the value is not the literal string "NULL"
                        return $input['fax_uuid'] !== 'NULL';
                    },
                    Rule::exists(Faxes::class, 'fax_uuid'),
                )
            ],
            'destination_enabled' => [
                Rule::in([null, true, false]),
            ],
            'destination_record' => [
                Rule::in([null, true, false]),
            ],
            'domain_uuid' => [
                'nullable',
                Rule::notIn(['NULL']),
                Rule::exists(Domain::class, 'domain_uuid')
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  Validator  $validator
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        // Get the original error messages from the validator
        $errors = $validator->errors()->toArray();
        $customMessages = [];
        foreach ($errors as $field => $message) {
            if (preg_match('/destination_conditions\.(\d+)\.condition_expression/', $field, $matches)) {
                $index = (int) $matches[1]; // Add 1 to make it 1-indexed
                $customMessages[$field][] = "Please use valid US phone number on condition ".($index + 1);
            }
            if (preg_match('/destination_conditions\.(\d+)\.value.value/', $field, $matches)) {
                $index = (int) $matches[1]; // Add 1 to make it 1-indexed
                $customMessages[$field][] = "Please select action on condition ".($index + 1);
            }
        }

        $errors = array_merge($errors, $customMessages);

        $responseData = array('errors' => $errors);

        throw new HttpResponseException(response()->json($responseData, 422));
    }

    public function messages(): array
    {
        return [
            'destination_conditions.*.condition_expression' => 'Please use valid US phone number on condition',
            'destination_conditions.*.value.value' => 'Please select action on condition',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('destination_conditions')) {
            $destinationConditions = [];
            foreach ($this->get('destination_conditions') as $condition) {
                try {
                    $condition['condition_expression'] = (new PhoneNumber($condition['condition_expression'], "US"))->formatE164();
                } catch (NumberParseException $e) {
                    //
                }
                $condition['condition_expression'] = str_replace('+1', '', $condition['condition_expression']);
                $destinationConditions[] = $condition;
            }
            $this->merge(['destination_conditions' => $destinationConditions]);
        }
    }
}
