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
            'domain_description.required' => 'Please enter a domain label.',
            'domain_description.max'      => 'The domain label may not be greater than 255 characters.',

            'domain_name.required'        => 'Please enter a domain name.',
            'domain_name.max'             => 'The domain name may not be greater than 255 characters.',

            'domain_enabled.required'     => 'Please specify whether the domain is enabled.',
            'domain_enabled.boolean'      => 'Invalid value for the domain status.',
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
