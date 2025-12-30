<?php

// app/Http/Requests/StoreUserRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('user_add');
    }

    public function rules(): array
    {
        return [
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'user_email'   => 'required|email|unique:v_users,user_email',
            'groups'       => 'sometimes|required|array|min:1',
            'groups.*'     => 'uuid|exists:v_groups,group_uuid',
            'accounts'       => 'sometimes|array',
            'accounts.*'     => 'uuid|exists:v_domains,domain_uuid',
            'account_groups'       => 'sometimes|array',
            'account_groups.*'     => 'uuid|exists:domain_groups,domain_group_uuid',
            'extension_uuid' => 'nullable|uuid',
            'language'     => 'nullable|string|max:10',
            'time_zone'    => 'nullable|string|max:50',
            'user_enabled' => 'required|string',
            'domain_uuid' => 'present',
            // domain_uuid is filled via mutator but you can also accept it explicitly
        ];
    }

    public function messages(): array
    {
        return [
            'groups.required' => 'You need to select at least one role.',
            'groups.min'      => 'You need to select at least one role.',
        ];
    }

    protected function prepareForValidation()
    {
        $email = $this->input('user_email');

        if (is_string($email)) {
            $this->merge([
                'user_email' => mb_strtolower(trim($email)),
            ]);
        }
    }
}
