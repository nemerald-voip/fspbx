<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhoneNumberRoutingOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('destination_edit') || userCheckPermission('destination_add')
            || userCheckPermission('destination_import');
    }

    public function rules(): array
    {
        return [
            'item_uuid' => ['nullable', 'uuid'],
            'domain_uuid' => ['bail', 'sometimes', 'required', 'uuid', Rule::exists('v_domains', 'domain_uuid')],
            'category' => ['nullable', 'string'],
        ];
    }
}
