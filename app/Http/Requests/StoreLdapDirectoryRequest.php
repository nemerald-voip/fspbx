<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLdapDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->isMethod('post') ? 'ldap_directory_create' : 'ldap_directory_update');
    }

    public function rules(): array
    {
        $directoryUuid = $this->route('directory');
        $passwordRule = $this->isMethod('post') ? ['required'] : ['nullable'];

        return [
            'type' => ['required', Rule::in(['active_directory', 'ldap'])],
            'name' => ['required', 'string', 'max:255', Rule::unique('ldap_directories', 'name')
                ->where(fn ($query) => $query->where('domain_uuid', session('domain_uuid')))
                ->ignore($directoryUuid, 'directory_uuid')],
            'enabled' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:10000'],
            'sync_interval_minutes' => ['required', Rule::in([15, 30, 60, 360, 720, 1440])],
            'secure_connection' => ['required', Rule::in(['none', 'starttls', 'ldaps'])],
            'hosts' => ['required', 'string', 'max:2000'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'bind_username' => ['required', 'string', 'max:255'],
            'bind_password' => [...$passwordRule, 'string', 'max:4096'],
            'ad_domain' => ['required', 'string', 'max:255'],
            'base_dn' => ['required', 'string', 'max:2000'],
            'create_missing_extensions' => ['required', Rule::in(['none', 'default'])],
            'manage_groups_locally' => ['required', 'boolean'],
            'common_name_attribute' => ['required', 'string', 'max:128'],
            'description_attribute' => ['required', 'string', 'max:128'],
            'unique_identifier_attribute' => ['required', 'string', 'max:128'],
            'user_dn' => ['nullable', 'string', 'max:2000'],
            'user_object_class' => ['required', 'string', 'max:128'],
            'user_object_filter' => ['required', 'string', 'max:4000'],
            'user_name_attribute' => ['required', 'string', 'max:128'],
            'user_first_name_attribute' => ['required', 'string', 'max:128'],
            'user_last_name_attribute' => ['required', 'string', 'max:128'],
            'user_display_name_attribute' => ['required', 'string', 'max:128'],
            'user_group_attribute' => ['required', 'string', 'max:128'],
            'user_email_attribute' => ['present', 'string', 'max:128'],
            'user_title_attribute' => ['nullable', 'string', 'max:128'],
            'user_company_attribute' => ['nullable', 'string', 'max:128'],
            'user_department_attribute' => ['nullable', 'string', 'max:128'],
            'user_home_phone_attribute' => ['nullable', 'string', 'max:128'],
            'user_work_phone_attribute' => ['nullable', 'string', 'max:128'],
            'user_cell_phone_attribute' => ['nullable', 'string', 'max:128'],
            'user_fax_attribute' => ['nullable', 'string', 'max:128'],
            'user_extension_attribute' => ['nullable', 'string', 'max:128'],
            'group_dn' => ['nullable', 'string', 'max:2000'],
            'group_object_class' => ['required', 'string', 'max:128'],
            'group_object_filter' => ['required', 'string', 'max:4000'],
            'group_members_attribute' => ['required', 'string', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN),
            'manage_groups_locally' => filter_var($this->input('manage_groups_locally'), FILTER_VALIDATE_BOOLEAN),
            // The database column predates optional email imports and is not
            // nullable. An empty string intentionally disables the mapping.
            'user_email_attribute' => trim((string) $this->input('user_email_attribute', '')),
        ]);
    }
}
