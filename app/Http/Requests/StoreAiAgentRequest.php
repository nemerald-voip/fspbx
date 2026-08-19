<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use App\Services\AiProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_create');
    }

    public function rules(): array
    {
        $domainUuid = (string) $this->input('domain_uuid');

        return [
            'domain_uuid' => ['required', 'uuid', Rule::exists('v_domains', 'domain_uuid')->where('domain_enabled', 'true')],
            'provider' => ['required', 'string', Rule::in(app(AiProviderRegistry::class)->names())],
            'name' => ['required', 'string', 'max:255'],
            'extension' => ['required', 'string', 'max:32', 'regex:/^[0-9*#]+$/', new UniqueExtension($this->agentUuid(), $domainUuid)],
            'inbound_agent_id' => ['required', 'string', 'max:255'],
            'inbound_agent_name' => ['nullable', 'string', 'max:255'],
            'outbound_agent_id' => ['nullable', 'string', 'max:255'],
            'outbound_agent_name' => ['nullable', 'string', 'max:255'],
            'recording_policy' => ['required', Rule::in(['inherit', 'always'])],
            'enabled' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $domainUuid = $this->canManageDomain()
            ? $this->input('domain_uuid')
            : $this->restrictedDomainUuid();
        $provider = $this->canManageProvider()
            ? $this->input('provider', $this->defaultProvider())
            : $this->restrictedProvider();

        $this->merge([
            'domain_uuid' => $domainUuid,
            'provider' => strtolower(trim((string) $provider)),
            'enabled' => filter_var($this->input('enabled', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'recording_policy' => $this->input('recording_policy', 'inherit'),
            'outbound_agent_id' => blank($this->input('outbound_agent_id')) ? null : $this->input('outbound_agent_id'),
            'outbound_agent_name' => blank($this->input('outbound_agent_name')) ? null : $this->input('outbound_agent_name'),
            'description' => blank($this->input('description')) ? null : $this->input('description'),
        ]);
    }

    protected function agentUuid(): ?string
    {
        return null;
    }

    protected function canManageDomain(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_manage_domain');
    }

    protected function restrictedDomainUuid(): ?string
    {
        return session('domain_uuid');
    }

    protected function canManageProvider(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_manage_provider');
    }

    protected function restrictedProvider(): ?string
    {
        return $this->defaultProvider();
    }

    protected function defaultProvider(): ?string
    {
        return app(AiProviderRegistry::class)->names()[0] ?? null;
    }
}
