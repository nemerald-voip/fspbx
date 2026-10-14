<?php

namespace App\Http\Requests;

use App\Models\PagingGroup;
use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('paging_groups_create');
    }

    protected function groupUuid(): ?string
    {
        return null;
    }

    public function rules(): array
    {
        $domainUuid = session('domain_uuid');
        $extensionExists = Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', $domainUuid);

        return [
            'name' => ['required', 'string', 'max:255'],
            'extension' => ['required', 'regex:/^\d{1,15}$/', new UniqueExtension($this->groupUuid())],
            'busy_mode' => ['required', Rule::in([PagingGroup::BUSY_SKIP, PagingGroup::BUSY_FORCE])],
            'mode' => ['required', Rule::in([PagingGroup::MODE_LIVE, PagingGroup::MODE_RECORDED])],
            'intro_beep' => ['boolean'],
            'intro_recording' => [
                'nullable',
                'string',
                Rule::exists('v_recordings', 'recording_filename')->where('domain_uuid', $domainUuid),
            ],
            'enabled' => ['boolean'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['uuid', $extensionExists],
            'allowed_callers' => ['nullable', 'array'],
            'allowed_callers.*' => ['uuid', $extensionExists],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'extension.regex' => __('Use digits only.'),
            'members.required' => __('Choose at least one extension to page.'),
            'members.min' => __('Choose at least one extension to page.'),
        ];
    }
}
