<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
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
                'string',
            ],
            'destination_cid_name_prefix' => [
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
            'destination_caller_id_name' => [
                'nullable',
                'string',
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']), // Ensures 'domain_uuid' is not 'NULL'
            ],
        ];
    }

    public function messages(): array
    {
        return [
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
