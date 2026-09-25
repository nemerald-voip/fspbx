<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissions and domain access are checked by the API route middleware.
        $this->validateDomain();

        return $this->user() !== null;
    }

    protected function validateDomain(): void
    {
        $uuid = (string) $this->route('domain_uuid');
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! Domain::whereKey($uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
    }

    public function rules(): array
    {
        $domainUuid = (string) $this->route('domain_uuid');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', Rule::unique('v_users', 'user_email')],
            'user_enabled' => ['sometimes', 'boolean'],
            'extension_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', $domainUuid)],
            'groups' => ['sometimes', 'required', 'array', 'min:1'],
            'groups.*' => ['required', 'uuid', 'distinct', Rule::exists('v_groups', 'group_uuid')],
            'time_zone' => ['sometimes', 'nullable', 'timezone'],
            'accounts' => ['sometimes', 'array'],
            'accounts.*' => ['required', 'uuid', 'distinct', Rule::exists('v_domains', 'domain_uuid')],
            'account_groups' => ['sometimes', 'array'],
            'account_groups.*' => ['required', 'uuid', 'distinct', Rule::exists('domain_groups', 'domain_group_uuid')],
            'locations' => ['sometimes', 'nullable', 'array'],
            'locations.*' => ['required', 'uuid', 'distinct', Rule::exists('locations', 'location_uuid')->where('domain_uuid', $domainUuid)],
        ];
    }

    public function messages(): array
    {
        return [
            'groups.required' => 'You need to select at least one role.',
            'groups.min' => 'You need to select at least one role.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('user_email'))) {
            $this->merge(['user_email' => mb_strtolower(trim($this->input('user_email')))]);
        }
        if ($this->has('user_enabled') && $this->input('user_enabled') !== null) {
            $value = filter_var($this->input('user_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null && $this->input('user_enabled') !== '') {
                $this->merge(['user_enabled' => $value]);
            }
        }
    }

    public function bodyParameters(): array
    {
        return [
            'first_name' => ['description' => 'First name. Required when creating a user.', 'example' => 'Jane'],
            'last_name' => ['description' => 'Optional last name. May be null.', 'example' => 'Smith'],
            'user_email' => ['description' => 'Email address, unique across all accounts. Stored in lowercase.', 'example' => 'jane.smith@example.com'],
            'user_enabled' => ['description' => 'Whether the user is enabled. Defaults to true on creation. Changing status requires user_status.', 'example' => true],
            'extension_uuid' => ['description' => 'Optional assigned extension from this domain. Omit on update to preserve it; send null to clear it.', 'example' => 'd2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b'],
            'groups' => ['description' => 'Role UUIDs. When supplied, must contain at least one role that exists in the database. Omit on update to preserve roles. Changes require user_group_edit. Directory-managed roles are preserved.', 'example' => ['6fdb722a-3f2f-430d-ab05-46a75de8c587']],
            'groups.*' => ['description' => 'A global or domain role UUID the caller is allowed to assign.', 'example' => '6fdb722a-3f2f-430d-ab05-46a75de8c587'],
            'time_zone' => ['description' => 'Optional user timezone. On creation, omit or send null to use the account default, falling back to the system setting or UTC. On update, omit to preserve the current value; send null to clear it.', 'example' => 'America/New_York'],
            'accounts' => ['description' => 'Optional managed account UUIDs. Changes require user_update_managed_accounts and access to every assigned account. Omit to preserve; send an empty array to clear.', 'example' => []],
            'accounts.*' => ['description' => 'An accessible domain UUID.', 'example' => '4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b'],
            'account_groups' => ['description' => 'Optional managed account-group UUIDs. Changes require user_update_managed_account_groups and authority over the assigned account groups. Omit to preserve; send an empty array to clear.', 'example' => []],
            'account_groups.*' => ['description' => 'An accessible account-group UUID.', 'example' => '5f3a947e-b6ca-4fa0-846c-44db68b6aac5'],
            'locations' => ['description' => 'Optional location UUIDs from this domain. Omit to preserve; send an empty array to clear.', 'example' => []],
            'locations.*' => ['description' => 'A location UUID in this domain.', 'example' => 'ed5909bc-dc59-4a4e-88ae-c1242a22ff88'],
        ];
    }
}
