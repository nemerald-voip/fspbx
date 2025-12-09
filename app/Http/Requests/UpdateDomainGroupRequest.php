<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDomainGroupRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('group_edit');
    }

    public function rules()
    {
        return [
            'group_name'        => 'required|string|max:255',
            'members' => 'nullable|array',
        ];
    }
}

