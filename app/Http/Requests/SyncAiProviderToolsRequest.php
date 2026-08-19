<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncAiProviderToolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isSuperAdmin() && userCheckPermission('ai_agent_manage_integration');
    }

    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('force')) {
            $this->merge([
                'force' => filter_var($this->input('force'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
