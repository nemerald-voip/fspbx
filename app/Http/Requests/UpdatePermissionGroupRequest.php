<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionGroupRequest extends FormRequest
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
            'domain_uuid'       => 'nullable|uuid',
            'group_level'       => 'required|integer|in:10,20,30,40,50,60,70',
            'group_protected'   => 'required|string',
            'group_description' => 'nullable|string',
        ];
    }
}

