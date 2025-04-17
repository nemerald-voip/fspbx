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
            'ring_group_uuid'   => ['required', 'uuid', 'exists:v_ring_groups,ring_group_uuid'],
            'name'              => ['required', 'string', 'max:75'],

            'extension' => [
                'required',
                'numeric',
                new UniqueExtension($this->get('ring_group_uuid')),
            ],
            'greeting' => [
                'nullable',
            ],
            'call_distribution' => [
                'required',
                Rule::in([
                    'enterprise',
                    'simultaneous',
                    'sequential',
                    'random',
                    'rollover',
                    // …add any other strategies you support
                ]),
            ],

            // members may be omitted or empty
            'members'               => ['nullable', 'array'],

            // only validate each sub‑field if members was provided
            'members.*.uuid'        => ['required_with:members', 'uuid'],
            'members.*.destination' => ['required_with:members', 'numeric'],
            'members.*.delay'       => ['required_with:members', 'numeric', 'min:0'],
            'members.*.timeout'     => ['required_with:members', 'numeric', 'min:0'],
            'members.*.prompt'      => ['required_with:members', 'boolean'],
            'members.*.enabled'     => ['required_with:members', 'boolean'],

            // Fail‑back logic: action + optional target
            'failback_action' => [
                'required',
            ],

            'failback_target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('failback_action');

                    // if an action *needs* a target (i.e. it is NOT one of these),
                    // then failback_target cannot be empty
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
            'name_prefix'   => ['nullable', 'string', 'max:20'],
            'number_prefix' => ['nullable', 'string', 'max:20'],
            'description'   => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'failback_action.required' => 'The no answer action is required.',

        ];
    }

    public function prepareForValidation()
    {

        logger($this);
        if ($this->get('ring_group_greeting') == 'disabled') {
            $this->merge([
                'ring_group_greeting' => null
            ]);
        }
        if ($this->get('ring_group_missed_call_category') == 'disabled') {
            $this->merge([
                'ring_group_missed_call_data' => null
            ]);
        }
        if ($this->get('timeout_category') == 'disabled') {
            $this->merge([
                'ring_group_timeout_data' => null
            ]);
        } else {
            $this->merge([
                'ring_group_timeout_data' => $this->get('timeout_action_' . $this->get('timeout_category'))
            ]);
        }
    }
}
