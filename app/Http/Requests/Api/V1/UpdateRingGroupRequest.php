<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        return [
            'ring_group_name' => ['sometimes', 'required', 'string', 'max:75'],

            'ring_group_extension' => [
                'sometimes',
                'required',
                'numeric',
                new UniqueExtension((string) $this->route('ring_group_uuid'), (string) $this->route('domain_uuid')),
            ],

            'ring_group_greeting' => ['nullable'],

            'ring_group_strategy' => [
                'sometimes',
                Rule::in(['enterprise', 'simultaneous', 'sequence', 'random', 'rollover']),
            ],

            'timeout_action' => [
                'sometimes',
                Rule::in(['extensions', 'ring_groups', 'ivrs', 'business_hours', 'contact_centers', 'faxes', 'conferences', 'call_flows', 'voicemails', 'recordings', 'check_voicemail', 'company_directory', 'hangup']),
            ],
            'timeout_target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('timeout_action');

                    // if an action *needs* a target (i.e. it is NOT one of these),
                    // then timeout_target cannot be empty
                    if (
                        $action
                        && ! in_array($action, [
                            'company_directory',
                            'check_voicemail',
                            'hangup',
                        ], true)
                        && empty($value)
                    ) {
                        $fail('A target must be provided when action is selected.');
                    }
                },
            ],

            'ring_group_cid_name_prefix' => ['nullable', 'string', 'max:20'],
            'ring_group_cid_number_prefix' => ['nullable', 'string', 'max:20'],
            'ring_group_description' => ['nullable', 'string', 'max:150'],

            'ring_group_forward_enabled' => ['sometimes', 'boolean'],

            'forward_action' => [
                'required_if:ring_group_forward_enabled,true',
                Rule::in(['extensions', 'ring_groups', 'ivrs', 'business_hours', 'contact_centers', 'faxes', 'conferences', 'call_flows', 'voicemails', 'external']),
            ],

            'forward_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('ring_group_forward_enabled');
                    $action = $this->input('forward_action');

                    if ($enabled && $action && $action !== 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            'forward_external_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('ring_group_forward_enabled');
                    $action = $this->input('forward_action');

                    if ($enabled && $action === 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            'ring_group_caller_id_name' => ['nullable', 'string', 'max:20'],
            'ring_group_caller_id_number' => ['nullable', 'string'],

            'ring_group_distinctive_ring' => ['nullable', 'string', 'max:30'],

            'ring_group_ringback' => ['sometimes','string'],

            'ring_group_call_forward_enabled' => ['sometimes', 'boolean'],
            'ring_group_follow_me_enabled' => ['sometimes', 'boolean'],

            'members' => ['nullable', 'array'],
            'members.*.destination_number' => ['required_with:members', 'regex:/^\+?\d+$/'],
            'members.*.destination_delay' => ['required_with:members', 'numeric', 'min:0'],
            'members.*.destination_timeout' => ['required_with:members', 'numeric', 'min:0'],
            'members.*.destination_prompt' => ['required_with:members', 'boolean'],
            'members.*.destination_enabled' => ['required_with:members', 'boolean'],

        ];
    }

    public function messages(): array
    {
        return [
            'fallback_action.required' => 'The no answer action is required.',
            'members.*.delay.required_with' => 'The member setting is required',
            'members.*.timeout.required_with' => 'The member setting is required',
            'forward_action.required_if' => 'The action is required when call forwarding is enabled.',
            'ring_group_missed_call_data.required_if' => 'The notifcation email is required',
        ];
    }

    public function prepareForValidation(): void
    {

        $members = $this->input('members', []);
        if (is_array($members) && count($members)) {
            foreach ($members as $i => $m) {
                if (isset($m['destination_number']) && $m['destination_number'] !== '') {
                    $v = preg_replace('/[^\d+]+/', '', (string) $m['destination_number']);
                    if ($v !== '') {
                        $hadPlus = strpos($v, '+') !== false;
                        $v = str_replace('+', '', $v);
                        if ($hadPlus) $v = '+' . $v;
                    }
                    $members[$i]['destination_number'] = $v;
                }
            }
            $this->merge(['members' => $members]);
        }

        // Normalize forward_external_target
        if ($this->has('forward_external_target')) {
            $v = preg_replace('/[^\d+]+/', '', (string) $this->input('forward_external_target'));
            if ($v !== '') {
                $hadPlus = strpos($v, '+') !== false;
                $v = str_replace('+', '', $v);
                if ($hadPlus) $v = '+' . $v;
            }
            $this->merge(['forward_external_target' => $v]);
        }

        // delay defaulting (sequence/random/rollover)
        $input = $this->all();
        $strategy = $input['ring_group_strategy'] ?? null;

        if (isset($input['members']) && is_array($input['members'])) {
            foreach ($input['members'] as $index => $member) {
                if (
                    in_array($strategy, ['sequence', 'random', 'rollover'], true)
                    && (!isset($member['destination_delay']) || $member['destination_delay'] === null)
                ) {
                    $input['members'][$index]['destination_delay'] = $index * 5;
                }

                if (!isset($input['members'][$index]['destination_delay'])) {
                    $input['members'][$index]['destination_delay'] = 0;
                }
            }
        }

        $this->replace($input);
    }


    public function bodyParameters(): array
    {
        return [
            // --- Core identity ---
            'ring_group_name' => [
                'description' => 'Ring group display name.',
                'example' => 'Sales Ring Group',
            ],
            'ring_group_extension' => [
                'description' => 'Numeric ring group extension number (must be unique within the domain).',
                'example' => '9000',
            ],

            // --- Description / greeting ---
            'ring_group_description' => [
                'description' => 'Optional ring group description/label.',
                'example' => 'Main sales queue (ring group)',
            ],
            'ring_group_greeting' => [
                'description' => 'Optional greeting recording reference/filename for the ring group.',
                'example' => 'sales-greeting.wav',
            ],

            // --- Ring strategy / tones ---
            'ring_group_strategy' => [
                'description' => 'Ring strategy. Default: enterprise.',
                'example' => 'sequence',
            ],
            'ring_group_ringback' => [
                'description' => 'Ringback tone or tone variable. Default: ${us-ring}',
                'example' => '${us-ring}',
            ],

            // --- Members ---
            'members' => [
                'description' => 'Optional list of ring group members. If provided, destinations are created in this order.',
                'example' => [
                    [
                        'destination_number' => '100',
                        'destination_delay' => 0,
                        'destination_timeout' => 25,
                        'destination_prompt' => false,
                        'destination_enabled' => true,
                    ],
                ],
            ],
            'members.*.uuid' => [
                'description' => 'Client-side UUID placeholder (present for UI consistency). Not required to be a valid UUID.',
                'example' => null,
            ],
            'members.*.destination' => [
                'description' => 'Member destination number (extension or external digits). May include a single leading +.',
                'example' => '100',
            ],
            'members.*.delay' => [
                'description' => 'Delay in seconds before this member rings.',
                'example' => 0,
            ],
            'members.*.timeout' => [
                'description' => 'Timeout in seconds to ring this member.',
                'example' => 25,
            ],
            'members.*.prompt' => [
                'description' => 'Whether to play a prompt to the called party before connecting.',
                'example' => false,
            ],
            'members.*.enabled' => [
                'description' => 'Whether this member is enabled.',
                'example' => true,
            ],

            // --- No-answer / failover (timeout action) ---
            'timeout_action' => [
                'description' => 'Optional no-answer action.',
                'example' => 'voicemails',
            ],
            'timeout_target' => [
                'description' => 'Optional no-answer target (required for many fallback_action values). For voicemails, this is the mailbox.',
                'example' => '101',
            ],

            // --- Forwarding ---
            'ring_group_forward_enabled' => [
                'description' => 'Whether call forwarding is active for the ring group. Defaults to false if omitted.',
                'example' => false,
            ],
            'forward_action' => [
                'description' => 'Forward action.',
                'example' => 'ring_groups',
            ],
            'forward_target' => [
                'description' => 'Forward target for non-external forward_action values (e.g., an extension).',
                'example' => '456',
            ],
            'forward_external_target' => [
                'description' => 'Forward target when forward_action=external (digits, may include a single leading +).',
                'example' => '+12135551212',
            ],

            // --- Caller ID / prefixes ---
            'ring_group_cid_name_prefix' => [
                'description' => 'Optional caller ID name prefix applied to calls coming from this ring group.',
                'example' => 'Sales',
            ],
            'ring_group_cid_number_prefix' => [
                'description' => 'Optional caller ID number prefix applied to calls coming from this ring group.',
                'example' => '9',
            ],
            'ring_group_caller_id_name' => [
                'description' => 'Optional forced caller ID name for outbound calls from this ring group.',
                'example' => 'ABC Corporation',
            ],
            'ring_group_caller_id_number' => [
                'description' => 'Optional forced caller ID number for outbound calls from this ring group.',
                'example' => '+12135550100',
            ],
            'ring_group_distinctive_ring' => [
                'description' => 'Optional distinctive ring to apply.',
                'example' => 'ring4',
            ],

            // --- Honor member settings ---
            'ring_group_call_forward_enabled' => [
                'description' => 'Whether to honor member call-forward settings. Default to the domain setting if omitted.',
                'example' => 'true',
            ],
            'ring_group_follow_me_enabled' => [
                'description' => 'Whether to honor member follow-me settings. Defaults to the domain setting if omitted.',
                'example' => 'true',
            ],

        ];
    }
}
