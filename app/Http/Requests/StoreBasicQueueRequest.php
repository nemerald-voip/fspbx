<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StoreBasicQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_center_queue_add');
    }

    public function rules(): array
    {
        return [
            'queue_name' => ['required', 'string', 'max:255'],
            'queue_extension' => ['required', 'string', 'max:255', new UniqueExtension($this->queueUuid())],
            'queue_strategy' => ['required', 'string', 'max:255'],
            'queue_greeting' => [
                'nullable',
                Rule::exists('v_recordings', 'recording_filename')
                    ->where('domain_uuid', session('domain_uuid')),
            ],
            'queue_moh_sound' => ['nullable', 'string', 'max:1024'],
            'queue_max_wait_time' => ['nullable', 'integer', 'min:0'],
            'queue_max_wait_time_with_no_agent' => ['nullable', 'integer', 'min:0'],
            'queue_tier_rules_apply' => ['required', 'in:true,false'],
            'queue_cid_prefix' => ['nullable', 'string', 'max:255'],
            'queue_timeout_action' => ['nullable', 'string', 'max:1024'],
            'timeout_action' => ['nullable', 'string', 'max:255'],
            'timeout_target' => [
                'nullable',
                'string',
                'max:1024',
                function ($attribute, $value, $fail) {
                    $action = $this->input('timeout_action');

                    if (
                        $action
                        && ! in_array($action, [
                            'company_directory',
                            'check_voicemail',
                            'hangup',
                        ], true)
                        && blank($value)
                    ) {
                        $fail(__('A target must be provided when action is selected.'));
                    }
                },
            ],
            'queue_description' => ['nullable', 'string', 'max:255'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.call_center_tier_uuid' => ['nullable', 'uuid'],
            'tiers.*.call_center_agent_uuid' => ['nullable', 'uuid'],
            'tiers.*.tier_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'tiers.*.tier_position' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'queue_strategy' => $this->input('queue_strategy', 'ring-all'),
            'queue_greeting' => $this->input('queue_greeting') === 'disabled' ? null : $this->input('queue_greeting'),
            'queue_moh_sound' => blank($this->input('queue_moh_sound')) ? 'local_stream://default' : $this->input('queue_moh_sound'),
            'queue_max_wait_time' => $this->input('queue_max_wait_time', 0),
            'queue_max_wait_time_with_no_agent' => $this->input('queue_max_wait_time_with_no_agent', 90),
            'queue_tier_rules_apply' => $this->input('queue_tier_rules_apply', 'false'),
        ]);
    }

    protected function queueUuid(): ?string
    {
        return null;
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'queue_name' => __('Name'),
            'queue_extension' => __('Extension'),
            'queue_strategy' => __('Strategy'),
            'queue_greeting' => __('Greeting'),
            'queue_moh_sound' => __('Music on Hold'),
            'queue_max_wait_time' => __('Max Wait Time'),
            'queue_max_wait_time_with_no_agent' => __('Max Wait Time with No Agent'),
            'queue_tier_rules_apply' => __('Tier Rules Apply'),
            'queue_cid_prefix' => __('Caller ID Prefix'),
            'queue_timeout_action' => __('Timeout Action'),
            'timeout_action' => __('Timeout Action'),
            'timeout_target' => __('Target'),
            'queue_description' => __('Description'),
            'tiers' => __('Agents'),
            'tiers.*.call_center_tier_uuid' => __('Tier'),
            'tiers.*.call_center_agent_uuid' => __('Agent'),
            'tiers.*.tier_level' => __('Level'),
            'tiers.*.tier_position' => __('Position'),
        ];
    }
}
