<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;
use App\Services\ProvisioningTemplateAccessService;

class StoreProvisioningTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('provisioning_templates_create') && $this->canSetSharing();
    }

    protected function canSetSharing(): bool
    {
        return ! $this->boolean('global') || userCheckPermission('provisioning_templates_manage_global');
    }

    public function rules(): array
    {
        $template = $this->route('provisioning_template');

        return [
            'vendor'        => 'nullable|string',
            'name'          => [
                'required',
                'string',
                'max:255',
                // unique per (vendor, domain_uuid) so different domains can reuse names
                Rule::unique('provisioning_templates', 'name')
                    ->where(fn($q) => $q->where('vendor', $this->input('vendor'))
                                        ->where('domain_uuid', $this->input('domain_uuid')))
                    ->ignore($template?->template_uuid, 'template_uuid'),
            ],
            'content'       => ['nullable', 'string'],

            'type'          => ['required', Rule::in(['custom'])],
            'global'        => ['required', 'boolean'],

            // Only relevant when you're basing a new template off a default one
            'base_template' => [
                'nullable',
                'string',
                Rule::exists('provisioning_templates', 'name')->where(fn ($query) => $query
                    ->where('vendor', $this->input('vendor'))->where('type', 'default')->whereNull('domain_uuid')),
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
            ...ValidationMessages::common(),
            'vendor.required'         => __('Choose a vendor.'),
            'name.required'           => __('Template name is required.'),
            'name.unique'             => __('A template with this name already exists for this vendor in this scope.'),
            'content.required'        => __('Template content is required.'),
            'type.required'           => __('Template type is required.'),
            'base_template.exists'    => __('Base template must be an existing default template for the selected vendor.'),
            'domain_uuid.required'    => __('Custom templates must be associated with a domain.'),
            'domain_uuid.uuid'        => __('Invalid domain UUID.'),
            'base_version.regex'      => __('Base version must be a valid version string (e.g., 1.0.0).'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $access = app(ProvisioningTemplateAccessService::class);
        $currentDomain = $access->currentDomain();
        $template = $this->route('provisioning_template');
        // Account ownership comes from the session, never a supplied account UUID.
        abort_if($this->filled('domain_uuid') && $this->input('domain_uuid') !== $currentDomain, 403);
        $global = $this->has('global') ? $this->input('global') : ($template && $template->domain_uuid === null);
        $this->merge(['global' => $global]);

        // Preserve shared custom ownership on updates unless explicitly changed.
        $vendor = $this->input('vendor');
        $type   = $this->input('type');
        $domainUuid = $this->boolean('global') ? null : $currentDomain;

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

    public function attributes(): array
    {
        return [
            'vendor' => __('Vendor'),
            'name' => __('Template Name'),
            'content' => __('Template content'),
            'type' => __('Type'),
            'base_template' => __('Base Template'),
            'base_version' => __('Base Version'),
            'domain_uuid' => __('Account'),
        ];
    }
}
