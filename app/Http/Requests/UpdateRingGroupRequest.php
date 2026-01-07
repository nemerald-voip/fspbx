<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
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
            'ring_group_uuid'   => ['sometimes', 'required', 'uuid', 'exists:v_ring_groups,ring_group_uuid'],
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
            'members.*.ring_group_destination_uuid' => ['nullable', 'uuid'], // optional on create
            'members.*.destination_number'          => ['required_with:members', 'regex:/^\+?\d+$/'],
            'members.*.destination_delay'           => ['required_with:members', 'numeric', 'min:0'],
            'members.*.destination_timeout'         => ['required_with:members', 'numeric', 'min:0'],
            'members.*.destination_prompt'          => ['required_with:members', 'boolean'],
            'members.*.destination_enabled'         => ['required_with:members', 'boolean'],

            // timeout logic: action + optional target
            'timeout_action' => [
                'sometimes',
                'required',
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
            // Optional prefixes & description
            'ring_group_cid_name_prefix'   => ['nullable', 'string', 'max:20'],
            'ring_group_cid_number_prefix' => ['nullable', 'string', 'max:20'],
            'ring_group_description'   => ['nullable', 'string', 'max:150'],

            'ring_group_forward_enabled' => ['sometimes', Rule::in(['true', 'false'])],


            // Forward logic: action + optional target
            // only required when ring_group_forward_enabled === true
            'forward_action'        => ['required_if:ring_group_forward_enabled,true'],

            'forward_external_target' => [
                'required_if:forward_action,external',
                'regex:/^\+?\d+$/',
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
            'ring_group_call_forward_enabled' => ['sometimes', Rule::in(['true', 'false'])],
            'ring_group_follow_me_enabled'     => ['sometimes', Rule::in(['true', 'false'])],

            'missed_call_notifications' => ['boolean'],
            'ring_group_missed_call_data' => [
                'required_if:missed_call_notifications,true',
                'string',
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
            'timeout_action.required' => 'The no answer action is required.',
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
        // --- Normalize free-typed numbers before rules run ---
        // Normalize members.*.destination: keep digits, allow a single leading '+'
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

        // If this request includes a forward external target, normalize it too
        if ($this->has('forward_external_target')) {
            $raw = (string) $this->input('forward_external_target');

            // Only normalize if there is at least one digit somewhere.
            if (preg_match('/\d/', $raw)) {
                $v = preg_replace('/[^\d+]+/', '', $raw);

                // allow only ONE leading '+'
                $v = ltrim($v, '+');
                if (str_contains($raw, '+')) {
                    $v = '+' . $v;
                }

                $this->merge(['forward_external_target' => $v]);
            }
            // else: leave the raw value untouched so validation can say "invalid format"
        }

        foreach (
            [
                'ring_group_forward_enabled',
                'ring_group_call_forward_enabled',
                'ring_group_follow_me_enabled',
            ] as $key
        ) {
            if ($this->has($key)) {
                $this->merge([
                    $key => $this->boolean($key) ? 'true' : 'false',
                ]);
            }
        }

        // Keep your existing delay-defaulting logic
        $input = $this->all();
        $callDistribution = $input['ring_group_strategy'] ?? null;

        if (isset($input['members']) && is_array($input['members'])) {
            foreach ($input['members'] as $index => $member) {
                if (
                    in_array($callDistribution, ['sequence', 'random', 'rollover'], true)
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
}
