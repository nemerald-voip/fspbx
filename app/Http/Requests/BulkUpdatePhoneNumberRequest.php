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
            'items' => 'required',

            'routing_options' => [
                'nullable',
                'array',
            ],
            
            'destination_accountcode' => [
                'nullable',
                'string',
            ],
            'destination_actions' => [
                'nullable',
                'array',
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
                'nullable',
                'string',
            ],
            'destination_record' => [
                'nullable',
                'string',
            ],
            'domain_uuid' => [
                'sometimes',
                'required',
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
            'domain_uuid.required' => 'Acccount must be selected.'

        ];
    }

}
