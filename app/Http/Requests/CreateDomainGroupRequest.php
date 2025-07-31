<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDomainGroupRequest extends FormRequest
{
    public function authorize()
    {
        // adjust permission key as needed
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

