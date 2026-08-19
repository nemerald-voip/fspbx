<?php

namespace App\Http\Requests;

class UpdateAiAgentRequest extends StoreAiAgentRequest
{
    public function authorize(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_update');
    }

    protected function agentUuid(): ?string
    {
        return $this->route('ai_agent')?->ai_agent_uuid;
    }

    protected function restrictedDomainUuid(): ?string
    {
        return $this->route('ai_agent')?->domain_uuid ?? session('domain_uuid');
    }

    protected function restrictedProvider(): ?string
    {
        return $this->route('ai_agent')?->provider ?? $this->defaultProvider();
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['provider' => $this->restrictedProvider()]);
    }
}
