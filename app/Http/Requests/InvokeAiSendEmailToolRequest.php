<?php

namespace App\Http\Requests;

use App\Models\AiProviderIntegration;
use App\Services\AiTools\AiProviderToolCatalog;
use App\Services\AiTools\RetellSignatureValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvokeAiSendEmailToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $integration = AiProviderIntegration::query()->find('retell');

        return $integration?->enabled === true
            && $integration->hasApiKey()
            && app(RetellSignatureValidator::class)->valid(
                $this->getContent(),
                $this->header('X-Retell-Signature'),
                $integration->api_key,
            );
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::in([AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME])],
            'call' => ['required', 'array'],
            'call.call_id' => ['required', 'string', 'max:255'],
            'call.agent_id' => ['required', 'string', 'max:255'],
            'call.custom_sip_headers' => ['required', 'array'],
            'args' => ['required', 'array'],
            'args.recipient' => [
                'required',
                'email:rfc',
                'max:254',
                Rule::notIn([AiProviderToolCatalog::SEND_EMAIL_RECIPIENT_PLACEHOLDER]),
            ],
            'args.subject' => ['required', 'string', 'max:255'],
            'args.fields' => ['required', 'array', 'min:1', 'max:25'],
            'args.fields.*' => ['required', 'array'],
            'args.fields.*.label' => ['required', 'string', 'max:100'],
            'args.fields.*.value' => ['required', 'string', 'max:2000'],
            'args.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
