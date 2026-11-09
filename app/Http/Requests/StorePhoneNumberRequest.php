<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Requests\Concerns\NormalizesPhoneNumberConditions;
use App\Rules\ValidPhoneNumber;
use App\Services\PhoneNumberService;

class StorePhoneNumberRequest extends FormRequest
{
    use NormalizesPhoneNumberConditions;

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
                new ValidPhoneNumber($this->input('domain_uuid')),
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
            'hold_music' => ['nullable', 'string'],
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
                $customMessages[$field][] = "Please use a valid phone number on condition " . ($index + 1);
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
            'destination_number.required' => __('Phone number is required'),
            'destination_number.unique' => __('This phone number already exists'),
            'destination_conditions.*.condition_expression' => __('Please use a valid phone number on condition'),
            'destination_conditions.*.condition_target.targetValue' => __('Please select action on condition'),
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
        if (is_string($this->input('destination_number'))) {
            $this->merge(app(PhoneNumberService::class)->destinationParts(
                $this->input('destination_number'), $this->input('destination_prefix'), $this->input('domain_uuid')
            ));
        }
        $this->normalizePhoneNumberConditions();

        if ($this->has('destination_type_fax')) {
            $this->merge([
                'destination_type_fax' => $this->destination_type_fax == '1' ? 1 : null,
            ]);
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
