<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteVirtualReceptionistKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('ivr_menu_edit');
    }

    public function rules(): array
    {
        return ['ivr_menu_option_uuid' => ['required', 'uuid']];
    }
}
