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
            'string' => __('The :attribute must be a string.'),
            'domain_description.required' => __('Please enter a domain label.'),
            'domain_description.max'      => __('The domain label may not be greater than 255 characters.'),

            'domain_name.required'        => __('Please enter a domain name.'),
            'domain_name.unique'          => __('This domain name is already in use.'),
            'domain_name.max'             => __('The domain name may not be greater than 255 characters.'),

            'domain_enabled.boolean'      => __('Invalid value for the domain status.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'domain_description' => __('Domain Label'),
            'domain_name' => __('Domain Name'),
            'domain_enabled' => __('Status'),
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
