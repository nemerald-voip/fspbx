<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateVirtualReceptionistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('ivr_menu_add');
    }

    public function rules(): array
    {
        return ['ivr_menu_uuid' => ['required', 'uuid']];
    }
}
