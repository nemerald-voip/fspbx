<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // you can tighten this to a policy or permission check
        return userCheckPermission('user_edit');
    }

    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');

        return [
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'user_email'   => [
                'required','email',
                // exclude current user by UUID
                "unique:v_users,user_email,{$user->user_uuid},user_uuid",
            ],
            'groups'       => 'sometimes|required|array',
            'groups.*'     => 'uuid|exists:v_groups,group_uuid',
            'accounts'       => 'sometimes|array',
            'accounts.*'     => 'uuid|exists:v_domains,domain_uuid',
            'account_groups'       => 'sometimes|array',
            'account_groups.*'     => 'uuid|exists:domain_groups,domain_group_uuid',
            'extension_uuid' => 'nullable|uuid',
            'language'     => 'nullable|string|max:10',
            'time_zone'    => 'nullable|string|max:50',
            'user_enabled' => 'sometimes|required|string',
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
        $this->merge([
            'user_email' => $this->user_email ? strtolower($this->user_email) : null,
        ]);
    }
}
