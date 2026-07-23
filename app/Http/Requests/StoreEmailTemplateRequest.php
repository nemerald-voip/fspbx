<?php

namespace App\Http\Requests;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && userCheckPermission('email_templates_create');
    }

    public function rules(): array
    {
        return [
            'domain_uuid' => ['nullable', 'uuid', 'exists:v_domains,domain_uuid'],
            // Custom templates may only override a default template that the
            // application actually sends, so a base default is required when
            // creating. Category/subcategory are derived from it, never free text.
            'base_template_uuid' => [
                $this->isCreate() ? 'required' : 'nullable',
                'uuid',
                Rule::exists('email_templates', 'email_template_uuid')->where('template_type', 'default'),
            ],
            'template_language' => ['required', 'string', 'max:20'],
            'template_category' => ['nullable', 'string', 'max:255'],
            'template_subcategory' => ['nullable', 'string', 'max:255'],
            'template_subject' => ['required', 'string', 'max:500'],
            'template_html' => ['required', 'string'],
            'template_text' => ['required', 'string'],
            'template_enabled' => ['required', 'boolean'],
            'template_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function isCreate(): bool
    {
        return ! ($this->route('email_template') instanceof EmailTemplate);
    }

    protected function baseDefault(): ?EmailTemplate
    {
        $uuid = $this->input('base_template_uuid');

        if (! $uuid) {
            return null;
        }

        return EmailTemplate::query()
            ->whereKey($uuid)
            ->where('template_type', 'default')
            ->first();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $domainUuid = $this->input('domain_uuid');

            if (! $domainUuid) {
                if (! userCheckPermission('email_templates_manage_global')) {
                    $validator->errors()->add('domain_uuid', 'Global overrides require global-template access.');
                }
            } elseif ($domainUuid !== session('domain_uuid')) {
                $validator->errors()->add('domain_uuid', 'Templates can only be created for the current account.');
            }

            $this->validateTemplateUniqueness($validator);
            $this->validateSafeContent($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        // The override target (category/subcategory) is authoritative and never
        // taken from client input: on update it stays locked to the existing
        // record; on create it comes from the selected base default template.
        $existing = $this->route('email_template');
        $base = $this->baseDefault();

        if ($existing instanceof EmailTemplate) {
            $category = $existing->template_category;
            $subcategory = $existing->template_subcategory;
        } elseif ($base) {
            $category = $base->template_category;
            $subcategory = $base->template_subcategory;
        } else {
            $category = $this->input('template_category');
            $subcategory = $this->input('template_subcategory');
        }

        $this->merge([
            'domain_uuid' => $this->input('domain_uuid') === '__global__' ? null : $this->input('domain_uuid'),
            'template_language' => strtolower(trim((string) $this->input('template_language'))),
            'template_category' => $category !== null ? strtolower(trim((string) $category)) : null,
            'template_subcategory' => $subcategory !== null ? strtolower(trim((string) $subcategory)) : null,
            'template_subject' => trim((string) $this->input('template_subject')),
            'template_enabled' => filter_var($this->input('template_enabled', true), FILTER_VALIDATE_BOOL),
            'template_description' => $this->filled('template_description')
                ? trim((string) $this->input('template_description'))
                : null,
        ]);
    }

    private function validateTemplateUniqueness($validator): void
    {
        $existing = EmailTemplate::query()
            ->where('template_type', 'custom')
            ->where('template_language', $this->input('template_language'))
            ->where('template_category', $this->input('template_category'))
            ->where('template_subcategory', $this->input('template_subcategory'))
            ->where(function ($query) {
                $domainUuid = $this->input('domain_uuid');
                $domainUuid ? $query->where('domain_uuid', $domainUuid) : $query->whereNull('domain_uuid');
            });

        $current = $this->route('email_template');
        if ($current instanceof EmailTemplate) {
            $existing->where($current->getKeyName(), '<>', $current->getKey());
        }

        if ($existing->exists()) {
            $validator->errors()->add(
                'template_subcategory',
                'A custom template already overrides this category, subcategory, language, and account.'
            );
        }
    }

    private function validateSafeContent($validator): void
    {
        $service = app(EmailTemplateService::class);
        foreach (['template_subject', 'template_html', 'template_text'] as $field) {
            if ($service->containsExecutableSyntax($this->input($field))) {
                $validator->errors()->add($field, 'Blade is supported, but @php, raw PHP tags, and scripts are not allowed.');
            }
        }
    }

}
