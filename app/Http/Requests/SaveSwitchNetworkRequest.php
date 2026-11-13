<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSwitchNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('var_view') && userCheckPermission('var_add') && userCheckPermission('var_edit');
    }

    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'layout' => ['required', Rule::in(['direct', 'nat', 'private'])],
            'local_ip' => ['required', 'ipv4'],
            'public_ip' => ['exclude_unless:layout,nat', 'required', 'ipv4'],
            'separate_media' => ['required', 'boolean'],
            'media_ip' => ['exclude_unless:layout,nat', 'exclude_unless:separate_media,true', 'required', 'ipv4'],
            'review_token' => ['nullable', 'string', 'size:64'],
        ];
    }
}
