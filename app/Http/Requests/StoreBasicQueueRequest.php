<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

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
                        $fail('A target must be provided when action is selected.');
                    }
                },
            ],
            'queue_description' => ['nullable', 'string', 'max:255'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.call_center_tier_uuid' => ['nullable', 'uuid'],
            'tiers.*.call_center_agent_uuid' => ['nullable', 'uuid'],
            'tiers.*.tier_level' => ['nullable', 'integer', 'min:1'],
            'tiers.*.tier_position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'queue_strategy' => $this->input('queue_strategy', 'ring-all'),
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
}
