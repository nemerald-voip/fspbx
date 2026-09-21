<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return userCheckPermission('domain_edit');
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
                'sometimes',
                'string',
                'max:255',
            ],

            'domain_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => __('The :attribute must be a string.'),
            'domain_description.required' => __('Please enter a domain label.'),
            'domain_description.max'      => __('The domain label may not be greater than 255 characters.'),

            'domain_name.required'        => __('Please enter a domain name.'),
            'domain_name.max'             => __('The domain name may not be greater than 255 characters.'),

            'domain_enabled.required'     => __('Please specify whether the domain is enabled.'),
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

        if ($name !== null) {
            $this->merge([
                'domain_name'    => strtolower(trim($name))
            ]);
        }

        $this->merge([
            'domain_enabled' => filter_var(
                $this->input('domain_enabled', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }
}
