<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExtensionRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('extension_edit');
    }

    public function rules()
    {
        return [
            'directory_first_name'     => ['required', 'string', 'max:60'],
            'directory_last_name'      => ['nullable', 'string', 'max:60'],
            'effective_caller_id_name' => ['present'],
            'effective_caller_id_number' => ['present'],
            'extension' => [
                'required',
                'numeric',
                new UniqueExtension($this->input('extension_uuid') ?? null),
            ],
            'voicemail_mail_to'               => ['nullable', 'email', 'max:255'],
            'description'              => ['nullable', 'string'],
            'suspended'                => ['boolean'],
            'do_not_disturb'          => ['sometimes', 'required', 'in:true,false,1,0'],
            'enabled'                  => ['sometimes', 'required', 'in:true,false,1,0'],
            'call_timeout'              => ['sometimes', 'required', 'string'],
            'directory_visible'        => ['sometimes', 'required', 'in:true,false,1,0'],
            'directory_exten_visible'  => ['sometimes', 'required', 'in:true,false,1,0'],
            'user_record' => ['nullable', 'string'],
            'call_screen_enabled'  => ['sometimes', 'required', 'in:true,false,1,0'],
            'max_registrations' => ['nullable', 'string'],
            'limit_max' => ['nullable', 'string'],
            'limit_destination' => ['nullable', 'string'],
            'toll_allow' => ['nullable', 'string'],
            'call_group' => ['nullable', 'string'],
            'hold_music' => ['nullable', 'string'],
            'cidr' => ['nullable', 'string'],
            'sip_force_contact' => ['nullable', 'string'],
            'sip_force_expires' => ['nullable', 'string'],
            'sip_bypass_media' => ['nullable', 'string'],
            'mwi_account' => ['nullable', 'string'],
            'absolute_codec_string' => ['nullable', 'string'],
            'dial_string' => ['nullable', 'string'],
            'force_ping' => ['nullable', 'string'],
            'user_context' => ['sometimes', 'required', 'string'],
            'accountcode' => ['sometimes', 'required', 'string'],
            'exclude_from_ringotel_stale_users' => ['nullable', 'boolean'],
            'auth_acl' => ['nullable', 'string'],
            'outbound_caller_id_number' => ['nullable', 'string'],
            'emergency_caller_id_number' => ['nullable', 'string'],

            'forward_all_enabled' => [
                'nullable',
                'string'
            ],

            // Forward logic: action + optional target
            // only required when forward_all_enabled === true
            'forward_all_action'        => ['required_if:forward_all_enabled,true'],

            // // if you also want to validate the targets:
            // 'forward_all_external_target' => [
            //     'required_if:forward_action,external',
            //     'string', 
            // ],

            'forward_all_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_all_enabled');
                    $action = $this->input('forward_all_action');

                    if ($enabled && $action && $action !== 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            'forward_all_external_target' => [
                'sometimes',
                'numeric',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_all_enabled');
                    $action = $this->input('forward_all_action');

                    if ($enabled && $action === 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            // === BUSY ===
            'forward_busy_enabled' => [
                'nullable',
                'string',
            ],
            'forward_busy_action' => [
                'required_if:forward_busy_enabled,true',
            ],
            'forward_busy_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_busy_enabled');
                    $action = $this->input('forward_busy_action');
                    if ($enabled && $action && $action !== 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],
            'forward_busy_external_target' => [
                'sometimes',
                'numeric',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_busy_enabled');
                    $action = $this->input('forward_busy_action');
                    if ($enabled && $action === 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            // === NO ANSWER ===
            'forward_no_answer_enabled' => [
                'nullable',
                'string',
            ],
            'forward_no_answer_action' => [
                'required_if:forward_no_answer_enabled,true',
            ],
            'forward_no_answer_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_no_answer_enabled');
                    $action = $this->input('forward_no_answer_action');
                    if ($enabled && $action && $action !== 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],
            'forward_no_answer_external_target' => [
                'sometimes',
                'numeric',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_no_answer_enabled');
                    $action = $this->input('forward_no_answer_action');
                    if ($enabled && $action === 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            // === USER NOT REGISTERED ===
            'forward_user_not_registered_enabled' => [
                'nullable',
                'string',
            ],
            'forward_user_not_registered_action' => [
                'required_if:forward_user_not_registered_enabled,true',
            ],
            'forward_user_not_registered_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_user_not_registered_enabled');
                    $action = $this->input('forward_user_not_registered_action');
                    if ($enabled && $action && $action !== 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],
            'forward_user_not_registered_external_target' => [
                'sometimes',
                'numeric',
                function ($attribute, $value, $fail) {
                    $enabled = $this->boolean('forward_user_not_registered_enabled');
                    $action = $this->input('forward_user_not_registered_action');
                    if ($enabled && $action === 'external' && empty($value)) {
                        $fail('The forward target is required');
                    }
                },
            ],

            'follow_me_enabled' => ['sometimes', 'string'],
            'follow_me_ring_my_phone_timeout' => ['nullable', 'numeric', 'min:0'],
            // follow_me_destinations may be omitted 
            'follow_me_destinations' => ['array'],

            // only validate each sub‑field if members was provided
            'follow_me_destinations.*.destination' => ['required_with:follow_me_destinations', 'numeric'],
            'follow_me_destinations.*.delay'       => ['required_with:follow_me_destinations', 'numeric', 'min:0'],
            'follow_me_destinations.*.timeout'     => ['required_with:follow_me_destinations', 'numeric', 'min:0'],
            'follow_me_destinations.*.prompt'      => ['required_with:follow_me_destinations', 'boolean'],


            'voicemail_enabled' => ['required', 'in:true,false,1,0'],
            'voicemail_id' => ['sometimes', 'required'],
            'voicemail_file'        => ['nullable'],
            'voicemail_local_after_email'        => ['sometimes', 'required', 'in:true,false,1,0'],
            'voicemail_transcription_enabled' => ['sometimes', 'required', 'in:true,false,1,0'],
            'voicemail_description' => ['nullable', 'string'],
            'voicemail_destinations' => ['nullable', 'array'],
            'voicemail_password' => ['numeric'],
            'voicemail_tutorial'        => ['sometimes', 'required', 'in:true,false,1,0'],
            'voicemail_recording_instructions' => ['sometimes', 'required', 'in:true,false,1,0'],


        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('follow_me_enabled') === 'true' && empty($this->input('follow_me_destinations'))) {
                $validator->errors()->add('follow_me_enabled', 'You must add at least one backup number or contact when Call Sequence is enabled.');
            }
        });
    }

    public function messages()
    {
        return [
            'directory_first_name.required' => 'The first name field is required.',
            'forward_all_action.required_if' => 'The action field is required when unconditional forwarding is enabled.',
            'forward_busy_action.required_if' => 'The action field is required when busy forwarding is enabled.',
            'forward_no_answer_action.required_if' => 'The action field is required when no answer forwarding is enabled.',
            'forward_user_not_registered_action.required_if' => 'The action field is required when forwarding for unregistered users is enabled.',
            'follow_me_destinations.*.delay.required_with' =>  'The destination setting is required',
            'follow_me_destinations.*.timeout.required_with' =>  'The destination setting is required',
            'forward_all_external_target.numeric' => 'The forward target must contain only digits.',
            'forward_busy_external_target.numeric' => 'The forward target must contain only digits.',
            'forward_no_answer_external_target.numeric' => 'The forward target must contain only digits.',
            'forward_not_registered_external_target.numeric' => 'The forward target must contain only digits.',
            // You can add more custom messages here as needed
        ];
    }

    public function prepareForValidation()
    {
        $first = $this->input('directory_first_name', '');
        $last = $this->input('directory_last_name', '');

        $fullName = trim($first . ' ' . $last); // Will work even if $last is empty

        $this->merge([
            'effective_caller_id_name' => $fullName,
        ]);

        $this->merge([
            'effective_caller_id_number' => $this->extension,
        ]);

        $this->merge([
            'voicemail_mail_to' => $this->voicemail_mail_to ? strtolower($this->voicemail_mail_to) : null,
        ]);        

        // List of all forwarding external target keys
        $forwardingTargets = [
            'forward_all_external_target',
            'forward_busy_external_target',
            'forward_no_answer_external_target',
            'forward_user_not_registered_external_target',
        ];

        foreach ($forwardingTargets as $key) {
            $value = $this->input($key);

            if (!empty($value)) {
                // Convert to E.164 format
                $formatted = formatPhoneNumber($value, 'US', \libphonenumber\PhoneNumberFormat::E164);
                $this->merge([$key => $formatted]);
            }
        }

        // Format each follow_me_destinations[n][destination] to E.164
        $followMe = $this->input('follow_me_destinations', []);
        if (is_array($followMe) && count($followMe)) {
            foreach ($followMe as $i => $destination) {
                if (!empty($destination['destination'])) {
                    $formatted = formatPhoneNumber($destination['destination'], 'US', \libphonenumber\PhoneNumberFormat::E164);
                    $followMe[$i]['destination'] = $formatted;
                }
            }
            // Update the merged input with formatted destinations
            $this->merge(['follow_me_destinations' => $followMe]);
        }
    }
}
