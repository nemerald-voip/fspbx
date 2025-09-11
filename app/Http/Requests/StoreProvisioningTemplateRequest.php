<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProvisioningTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // adjust to your actual permission
        // return userCheckPermission('provisioning_templates_create');
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor'        => 'nullable|string',
            'name'          => [
                'required',
                'string',
                'max:255',
                // unique per (vendor, domain_uuid) so different domains can reuse names
                Rule::unique('provisioning_templates', 'name')
                    ->where(fn($q) => $q->where('vendor', $this->input('vendor'))
                                        ->where('domain_uuid', $this->input('domain_uuid'))),
            ],
            'content'       => ['nullable', 'string'],

            'type'          => ['required', Rule::in(['default', 'custom'])],

            // Only relevant when you're basing a new template off a default one
            'base_template' => [
                'nullable',
                'string',
            ],

            // Allow SemVer-ish values like 1.0.0, 2.3, 2025.08.01, etc. Make stricter if needed.
            'base_version'  => ['nullable', 'string', 'max:50', 'regex:/^[0-9]+(\.[0-9]+){0,2}(-[0-9A-Za-z\.-]+)?$/'],

            'domain_uuid'   => [
                'nullable',
                'uuid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor.required'         => 'Choose a vendor.',
            'name.required'           => 'Template name is required.',
            'name.unique'             => 'A template with this name already exists for this vendor in this scope.',
            'content.required'        => 'Template content is required.',
            'type.required'           => 'Template type is required.',
            'type.in'                 => 'Type must be either default or custom.',
            'base_template.exists'    => 'Base template must be an existing default template for the selected vendor.',
            'domain_uuid.required'    => 'Custom templates must be associated with a domain.',
            'domain_uuid.uuid'        => 'Invalid domain UUID.',
            'base_version.regex'      => 'Base version must be a valid version string (e.g., 1.0.0).',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize strings and set domain scoping rules
        $vendor = $this->input('vendor');
        $type   = $this->input('type');

        // If custom not global then set current domain_uuid
        if ($type === 'custom' && !$this->input('global')) {
            $domainUuid = session('domain_uuid');
        } else {
            $domainUuid = null;
        }

        // If default, force domain to null (global visibility)
        if ($type === 'default') {
            $domainUuid = null;
        }

        $this->merge([
            'vendor'        => is_string($vendor) ? strtolower(trim($vendor)) : $vendor,
            'name'          => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'content'       => $this->input('content'),
            'type'          => is_string($type) ? strtolower(trim($type)) : $type,
            'base_template' => is_string($this->input('base_template')) ? trim($this->input('base_template')) : $this->input('base_template'),
            'base_version'  => $this->input('base_version') ?: null,
            'domain_uuid'   => $domainUuid,
        ]);
    }
}
