<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $directoryLink = null;

        if ($user && Schema::hasTable('ldap_directory_users')) {
            $directoryLink = DB::table('ldap_directory_users')
                ->where('domain_uuid', $user->domain_uuid)
                ->where('user_uuid', $user->user_uuid)
                ->first(['email', 'extension']);
        }

        $directoryManaged = $directoryLink !== null;
        $emailManaged = $directoryManaged && filter_var($directoryLink->email, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'first_name'   => $directoryManaged ? ['prohibited'] : ['required', 'string', 'max:255'],
            'last_name'    => $directoryManaged ? ['prohibited'] : ['nullable', 'string', 'max:255'],
            'user_email'   => $directoryManaged
                ? ($emailManaged
                    ? ['prohibited']
                    : ['nullable', 'email', "unique:v_users,user_email,{$user->user_uuid},user_uuid"])
                : ['required', 'email', "unique:v_users,user_email,{$user->user_uuid},user_uuid"],
            'groups'       => $directoryManaged ? ['sometimes', 'array'] : ['sometimes', 'required', 'array'],
            'groups.*'     => 'uuid|exists:v_groups,group_uuid',
            'accounts'       => 'sometimes|array',
            'accounts.*'     => 'uuid|exists:v_domains,domain_uuid',
            'account_groups'       => 'sometimes|array',
            'account_groups.*'     => 'uuid|exists:domain_groups,domain_group_uuid',
            'extension_uuid' => $directoryManaged && filled($directoryLink->extension)
                ? ['prohibited']
                : ['nullable', 'uuid'],
            'language'     => 'nullable|string|max:10',
            'time_zone'    => 'nullable|string|max:50',
            'user_enabled' => $directoryManaged ? ['prohibited'] : ['sometimes', 'required', 'string'],
            'locations' => 'nullable|array'
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
        if ($this->has('user_email')) {
            $this->merge([
                'user_email' => $this->user_email ? strtolower($this->user_email) : null,
            ]);
        }
    }
}
