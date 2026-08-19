<?php

namespace App\Http\Requests;

use App\Services\AccessControlService;
use App\Services\AiProviderIntegrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiProviderIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_manage_integration');
    }

    public function rules(): array
    {
        return [
            'api_key' => [
                Rule::requiredIf(! app(AiProviderIntegrationService::class)->retell()->hasApiKey()),
                'nullable',
                'string',
                'max:4096',
            ],
            'public_sip_host' => ['required', 'string', 'max:255', 'regex:/^(?!https?:\/\/)[A-Za-z0-9][A-Za-z0-9.-]*$/'],
            'provider_cidrs' => ['required', 'array', 'min:1'],
            'provider_cidrs.*' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! app(AccessControlService::class)->normalizeCidr($value)) {
                        $fail(__('The provider IP range must be a valid IP address or CIDR.'));
                    }
                },
            ],
            'enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $providerCidrs = $this->input('provider_cidrs', []);

        if (is_array($providerCidrs)) {
            $providerCidrs = collect($providerCidrs)
                ->map(fn ($item) => is_array($item) ? ($item['node_cidr'] ?? null) : $item)
                ->filter(fn ($item) => filled($item))
                ->map(fn ($item) => trim((string) $item))
                ->values()
                ->all();
        } else {
            $providerCidrs = collect(preg_split('/[\r\n,]+/', (string) $providerCidrs) ?: [])
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'api_key' => blank($this->input('api_key')) ? null : $this->input('api_key'),
            'provider_cidrs' => $providerCidrs,
            'enabled' => filter_var($this->input('enabled', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }
}
