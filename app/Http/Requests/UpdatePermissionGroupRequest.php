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
            'group_description' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'group_name' => __('Name'),
            'domain_uuid' => __('Account'),
            'group_level' => __('Level'),
            'group_description' => __('Description'),
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'string' => __('The :attribute must be a string.'),
            'max.string' => __('The :attribute must not be greater than :max characters.'),
            'integer' => __('The :attribute must be an integer.'),
            'in' => __('The selected :attribute is invalid.'),
            'uuid' => __('The :attribute must be a valid UUID.'),
        ];
    }
}
