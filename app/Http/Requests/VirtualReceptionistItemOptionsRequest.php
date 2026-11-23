<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VirtualReceptionistItemOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->filled('item_uuid') ? 'ivr_menu_edit' : 'ivr_menu_add')
            && ($this->input('domain_uuid') === null || $this->input('domain_uuid') === session('domain_uuid'));
    }

    public function rules(): array
    {
        return ['item_uuid' => ['nullable', 'uuid']];
    }
}
