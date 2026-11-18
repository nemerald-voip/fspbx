<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhoneNumberItemOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->input('mode') === 'create' ? 'destination_add' : 'destination_edit');
    }

    protected function prepareForValidation(): void
    {
        $uuid = $this->input('item_uuid') ?? $this->input('itemUuid');
        $this->merge([
            'item_uuid' => $uuid,
            'mode' => $this->input('mode') ?: ($uuid ? 'update' : 'create'),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_uuid' => ['nullable', 'uuid', 'required_if:mode,update', Rule::prohibitedIf($this->input('mode') !== 'update')],
            'itemUuid' => ['nullable', 'uuid', 'same:item_uuid'],
            'mode' => ['required', 'string', Rule::in(['create', 'update', 'bulk_update'])],
            'domain_uuid' => ['bail', 'sometimes', 'required', 'uuid', Rule::exists('v_domains', 'domain_uuid')],
        ];
    }
}
