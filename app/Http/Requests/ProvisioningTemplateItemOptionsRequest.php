<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProvisioningTemplateItemOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->filled('item_uuid') || userCheckPermission('provisioning_templates_create');
    }

    public function rules(): array
    {
        return [
            'item_uuid' => ['nullable', 'uuid'],
        ];
    }
}
