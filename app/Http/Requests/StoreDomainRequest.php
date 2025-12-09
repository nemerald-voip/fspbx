<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return userCheckPermission('domain_add');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'domain_description' => ['required', 'string', 'max:255'],

            'domain_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('v_domains', 'domain_name'),
            ],
            'domain_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain_description.required' => 'Please enter a domain label.',
            'domain_description.max'      => 'The domain label may not be greater than 255 characters.',

            'domain_name.required'        => 'Please enter a domain name.',
            'domain_name.unique'          => 'This domain name is already in use.',
            'domain_name.max'             => 'The domain name may not be greater than 255 characters.',

            'domain_enabled.boolean'      => 'Invalid value for the domain status.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('domain_name');

        $this->merge([
            'domain_name'    => $name !== null ? strtolower(trim($name)) : null,
            'domain_enabled' => filter_var(
                $this->input('domain_enabled', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }
}
