<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'ring_group_uuid'   => ['sometimes','required', 'uuid', 'exists:v_ring_groups,ring_group_uuid'],
            'ring_group_name'              => ['sometimes', 'required', 'string', 'max:75'],

            'ring_group_extension' => [
                'sometimes',
                'required',
                'numeric',
                new UniqueExtension($this->get('ring_group_uuid')),
            ],
            'ring_group_greeting' => [
                'nullable',
            ],
            'ring_group_strategy' => [
                'sometimes',
                'required',
                Rule::in([
                    'enterprise',
                    'simultaneous',
                    'sequence',
                    'random',
                    'rollover',
                    // …add any other strategies you support
                ]),
            ],

            // members may be omitted or empty
            'members'               => ['nullable', 'array'],

            // only validate each sub‑field if members was provided
            'members.*.uuid'        => ['present'],
            'members.*.destination' => ['required_with:members', 'numeric'],
            'members.*.delay'       => ['required_with:members', 'numeric', 'min:0'],
            'members.*.timeout'     => ['required_with:members', 'numeric', 'min:0'],
            'members.*.prompt'      => ['required_with:members', 'boolean'],
            'members.*.enabled'     => ['required_with:members', 'boolean'],

            // Fail‑back logic: action + optional target
            'fallback_action' => [
                'sometimes',
                'required',
            ],

            'fallback_target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('fallback_action');

                    // if an action *needs* a target (i.e. it is NOT one of these),
                    // then fallback_target cannot be empty
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
            // Optional prefixes & description
            'ring_group_cid_name_prefix'   => ['nullable', 'string', 'max:20'],
            'ring_group_cid_number_prefix' => ['nullable', 'string', 'max:20'],
            'ring_group_description'   => ['nullable', 'string', 'max:150'],

            'ring_group_forward_enabled' => [
                'nullable',
                'boolean'
            ],

            // Forward logic: action + optional target
            // only required when ring_group_forward_enabled === true
            'forward_action'        => ['required_if:ring_group_forward_enabled,true'],

            // if you also want to validate the targets:
            'forward_external_target' => [
                'required_if:forward_action,external',
                'string', // or 'uuid' if it must be a UUID
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


            'ring_group_caller_id_name'             => ['nullable', 'string', 'max:20'],
            'ring_group_caller_id_number'           => [
                'nullable',
                'string',
            ],

            'ring_group_distinctive_ring'           => ['nullable', 'string', 'max:30'],
            'ring_group_ringback'                   => [
                'sometimes',
                'required',
                'string',
            ],
            'ring_group_call_forward_enabled' => ['boolean'],
            'ring_group_follow_me_enabled' => ['boolean'],

            'missed_call_notifications' => ['boolean'],
            'ring_group_missed_call_data' => [
                'required_if:missed_call_notifications,true',
                'string', // or 'uuid' if it must be a UUID
            ],

            'ring_group_forward_toll_allow'         => [
                'nullable',
            ],

            'ring_group_context'                    => [
                'sometimes',
                'required',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fallback_action.required' => 'The no answer action is required.',
            'members.*.delay.required_with' =>  'The member setting is required',
            'members.*.timeout.required_with' =>  'The member setting is required',
            'forward_action.required_if' =>  'The action is required when call forwarding is enabled.',
            'notification_email.required_if' => 'The notification email is required when missed call notifications are enabled.',
            'forward_target.required_unless' =>  'The forwarding target is required',
            'ring_group_missed_call_data.required_if' => 'The notifcation email is required',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $callDistribution = $input['ring_group_strategy'] ?? null;

        if (isset($input['members']) && is_array($input['members'])) {
            foreach ($input['members'] as $index => $member) {
                // If delay is missing AND strategy is sequence/random/rollover, calculate it
                if (
                    in_array($callDistribution, ['sequence', 'random', 'rollover'], true)
                    && (!isset($member['delay']) || $member['delay'] === null)
                ) {
                    $input['members'][$index]['delay'] = $index * 5;
                }

                // fallback delay default
                if (!isset($input['members'][$index]['delay'])) {
                    $input['members'][$index]['delay'] = 0;
                }
            }
        }

        $this->replace($input);
    }
}
