<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'menu_item_parent_uuid' => $this->input('menu_item_parent_uuid') ?: null,
            'menu_item_link' => $this->input('menu_item_link') ?: null,
            'menu_item_icon' => $this->input('menu_item_icon') ?: null,
            'menu_item_description' => $this->input('menu_item_description') ?: null,
            'menu_item_order' => $this->input('menu_item_order') === '' ? null : $this->input('menu_item_order'),
            'group_uuids' => $this->input('group_uuids', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'menu_item_title' => ['required', 'string', 'max:255'],
            'menu_item_link' => ['nullable', 'string', 'max:2048', 'regex:/^[a-zA-Z0-9_:\-.&=?\/]+$/'],
            'menu_item_icon' => ['nullable', 'string', 'max:255', 'regex:/^fa-[a-z0-9-]+$/'],
            'menu_item_parent_uuid' => ['nullable', 'uuid'],
            'menu_item_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'menu_item_description' => ['nullable', 'string', 'max:255'],
            'group_uuids' => ['array'],
            'group_uuids.*' => ['uuid', 'distinct'],
        ];
    }
}
