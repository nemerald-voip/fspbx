<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
                Rule::unique('App\Models\Destinations', 'destination_number')
                    ->ignore($this->get('destination_uuid'), 'destination_uuid')
            ],
            'destination_prefix' => [
                'nullable'
            ],
            'destination_accountcode' => [
                'nullable',
                'string',
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
            'destination_conditions.*.condition_target.targetValue' => [
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
                'nullable',
                'string',
            ],
            'destination_record' => [
                'nullable',
                'string',
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']),
                Rule::exists('v_domains', 'domain_uuid')
            ],
            'destination_context' => [
                'nullable',
                'string',
            ],
            'routing_options' => [
                'nullable',
                'array',
            ],
            'destination_type_fax' => 'present',
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
                $customMessages[$field][] = "Please use valid US phone number on condition " . ($index + 1);
            }
            if (preg_match('/destination_conditions\.(\d+)\.condition_target.targetValue/', $field, $matches)) {
                $index = (int) $matches[1]; // Add 1 to make it 1-indexed
                $customMessages[$field][] = "Please select action on condition " . ($index + 1);
            }
        }

        $errors = array_merge($errors, $customMessages);

        $responseData = array('errors' => $errors);

        throw new HttpResponseException(response()->json($responseData, 422));
    }

    public function messages(): array
    {
        return [
            'destination_number.required' => 'Phone number is required',
            'destination_number.unique' => 'This phone number is already used',
            'destination_conditions.*.condition_expression' => 'Please use valid US phone number on condition',
            'destination_conditions.*.condition_target.targetValue' => 'Please select action on condition',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        $phone = $this->get('destination_number');
        $prefix = $this->get('destination_prefix');

        try {
            $this->merge([
                'destination_number' => str_replace('+1', '', (new PhoneNumber($phone, "US"))->formatE164()),
            ]);
        } catch (NumberParseException $e) {
            $this->merge([
                'destination_number' => $phone
            ]);
        }

        if ($this->has('destination_conditions')) {
            $destinationConditions = [];
            foreach ($this->get('destination_conditions') as $condition) {
                try {
                    $condition['condition_expression'] = (new PhoneNumber(
                        $condition['condition_expression'],
                        "US"
                    ))->formatE164();
                } catch (NumberParseException $e) {
                    $condition['condition_expression'] = null;
                }
                $condition['condition_expression'] = str_replace('+1', '', $condition['condition_expression']);
                $destinationConditions[] = $condition;
            }
            $this->merge(['destination_conditions' => $destinationConditions]);
        }

        if ($this->has('destination_enabled')) {
            $this->merge([
                'destination_enabled' => $this->destination_enabled ? 'true' : 'false',
            ]);
        }

        if ($this->has('destination_record')) {
            $this->merge([
                'destination_record' => $this->destination_record ? 'true' : 'false',
            ]);
        }

        if ($this->has('destination_type_fax')) {
            $this->merge([
                'destination_type_fax' => $this->destination_type_fax ? 1 : null,
            ]);
        }

        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        // Sanitize description
        if ($this->has('destination_description') && $this->destination_description) {
            $sanitizedDescription = $this->sanitizeInput($this->destination_description);
            $this->merge(['destination_description' => $sanitizedDescription]);
        }
    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }
}
