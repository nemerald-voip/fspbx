<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteVirtualReceptionistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('ivr_menu_delete');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
