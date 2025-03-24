<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class UpdateAccountSettingsRequest extends FormRequest
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
            'domain_uuid' => [
                'required',
                'uuid',
            ],
            'domain_name' => [
                'required',
                'string',
            ],
            'domain_description' => [
                'required',
                'string'
            ],
            'domain_enabled' => [
                'present',
                'boolean',
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
            'destination_conditions.*.condition_expression' => 'Please use valid US phone number on condition',
            'destination_conditions.*.condition_target.targetValue' => 'Please select action on condition',
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
        if ($this->has('domain_description') && $this->domain_description) {
            $sanitizedDescription = $this->sanitizeInput($this->domain_description);
            $this->merge(['domain_description' => $sanitizedDescription]);
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
