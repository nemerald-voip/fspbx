<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkUpdateDeviceRequest extends FormRequest
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
            'items' => [
                'required',
                'array'
            ],
            'device_profile_uuid' => [
                'nullable',
                Rule::when(
                    function ($input) {
                        // Check if the value is not the literal string "NULL"
                        return $input['device_profile_uuid'] !== 'NULL';
                    },
                    Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid'),                
                    )
            ],
            'device_template' => [
                'nullable',
                'string',
            ],
            'extension' => [
                'nullable',
            ],
            'domain_uuid' => [
                'nullable',
                Rule::notIn(['NULL']), // Ensures 'domain_uuid' is not 'NULL'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'No items selected to update',
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        logger($this);

    }
}
