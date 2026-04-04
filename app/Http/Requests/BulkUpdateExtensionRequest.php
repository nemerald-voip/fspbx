<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('extension_edit');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'],

            'directory_first_name' => ['nullable', 'string', 'max:60'],
            'directory_last_name' => ['nullable', 'string', 'max:60'],
            'voicemail_mail_to' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'suspended' => ['nullable', 'in:true,false,1,0'],
            'do_not_disturb' => ['sometimes', 'required', 'in:true,false,1,0'],
            'user_record' => ['nullable', 'in:all,local,outbound,inbound'],
            'call_timeout' => ['nullable', 'string'],
            'outbound_caller_id_number' => ['nullable', 'string'],
            'emergency_caller_id_number' => ['nullable', 'string'],

            'forward_all_enabled' => ['nullable', 'string'],
            'forward_all_action' => ['required_if:forward_all_enabled,true'],
            'forward_all_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_all_enabled') && $this->input('forward_all_action') !== 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],
            'forward_all_external_target' => [
                'sometimes',
                'regex:/^\+?\d+$/',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_all_enabled') && $this->input('forward_all_action') === 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],

            'forward_busy_enabled' => ['nullable', 'string'],
            'forward_busy_action' => ['required_if:forward_busy_enabled,true'],
            'forward_busy_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_busy_enabled') && $this->input('forward_busy_action') !== 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],
            'forward_busy_external_target' => [
                'sometimes',
                'regex:/^\+?\d+$/',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_busy_enabled') && $this->input('forward_busy_action') === 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],

            'forward_no_answer_enabled' => ['nullable', 'string'],
            'forward_no_answer_action' => ['required_if:forward_no_answer_enabled,true'],
            'forward_no_answer_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_no_answer_enabled') && $this->input('forward_no_answer_action') !== 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],
            'forward_no_answer_external_target' => [
                'sometimes',
                'regex:/^\+?\d+$/',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_no_answer_enabled') && $this->input('forward_no_answer_action') === 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],

            'forward_user_not_registered_enabled' => ['nullable', 'string'],
            'forward_user_not_registered_action' => ['required_if:forward_user_not_registered_enabled,true'],
            'forward_user_not_registered_target' => [
                'sometimes',
                'present',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_user_not_registered_enabled') && $this->input('forward_user_not_registered_action') !== 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],
            'forward_user_not_registered_external_target' => [
                'sometimes',
                'regex:/^\+?\d+$/',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('forward_user_not_registered_enabled') && $this->input('forward_user_not_registered_action') === 'external' && empty($value)) {
                        $fail('The forward target is required.');
                    }
                },
            ],

            'voicemail_enabled' => ['sometimes', 'in:true,false,1,0'],
            'voicemail_password' => ['nullable', 'numeric'],
            'voicemail_description' => ['nullable', 'string'],
            'voicemail_transcription_enabled' => ['sometimes', 'required', 'in:true,false,1,0'],
            'voicemail_file' => ['nullable'],
            'voicemail_local_after_email' => ['sometimes', 'required', 'in:true,false,1,0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'No extensions selected to update.',
        ];
    }

    public function prepareForValidation(): void
    {
        $normalizeBoolean = function ($value) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return $value;
        };

        foreach ([
            'suspended',
            'do_not_disturb',
            'forward_all_enabled',
            'forward_busy_enabled',
            'forward_no_answer_enabled',
            'forward_user_not_registered_enabled',
            'voicemail_enabled',
            'voicemail_transcription_enabled',
            'voicemail_local_after_email',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => $normalizeBoolean($this->input($field)),
                ]);
            }
        }

        if ($this->has('suspended') && ($this->input('suspended') === '' || $this->input('suspended') === null)) {
            $this->merge([
                'suspended' => 'false',
            ]);
        }

        if ($this->filled('voicemail_mail_to')) {
            $this->merge([
                'voicemail_mail_to' => strtolower((string) $this->input('voicemail_mail_to')),
            ]);
        }

        $normalizePhoneLoose = function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $value = preg_replace('/[^\d+]+/', '', $value);

            if ($value === '') {
                return $value;
            }

            $hadPlus = str_contains($value, '+');
            $value = str_replace('+', '', $value);

            return $hadPlus ? '+' . $value : $value;
        };

        foreach ([
            'forward_all_external_target',
            'forward_busy_external_target',
            'forward_no_answer_external_target',
            'forward_user_not_registered_external_target',
        ] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => $normalizePhoneLoose($this->input($field))]);
            }
        }
    }
}
