<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteProvisioningTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('provisioning_templates_delete');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
