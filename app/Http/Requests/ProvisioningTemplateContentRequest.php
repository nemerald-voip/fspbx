<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProvisioningTemplateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('provisioning_templates_view');
    }

    public function rules(): array
    {
        return [
            'template_uuid' => ['required', 'uuid'],
        ];
    }
}
