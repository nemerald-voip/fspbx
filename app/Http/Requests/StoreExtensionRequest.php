<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class StoreExtensionRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('extension_edit');
    }

    public function rules()
    {
        return [
            'extension' => [
                'required',
                'numeric',
                new UniqueExtension($this->input('extension_uuid') ?? null),
            ],
            'directory_first_name'     => ['required', 'string', 'max:60'],
            'directory_last_name'      => ['nullable', 'string', 'max:60'],
            'effective_caller_id_name' => ['present'],
            'effective_caller_id_number' => ['present'],
            'voicemail_mail_to'               => ['nullable', 'email', 'max:255'],
            'description'              => ['nullable', 'string', 'max:255'],
            // Dynamic/account/session-based fields
            'user_context'    => ['required', 'string', 'max:255'],
            'accountcode'     => ['nullable', 'string', 'max:255'],
            'domain_uuid'     => ['required', 'uuid'],
            'password'        => ['required', 'string', 'min:6', 'max:255'],
            'directory_visible' => ['required', 'in:true,false,1,0'],
            'directory_exten_visible' => ['required', 'in:true,false,1,0'],


            // Voicemail fields
            'voicemail_enabled'               => ['required', 'in:true,false,1,0'],
            'voicemail_transcription_enabled' => ['required', 'in:true,false,1,0'],
            'voicemail_recording_instructions' => ['required', 'in:true,false,1,0'],
            'voicemail_file'                  => ['required', 'string', 'max:16'],
            'voicemail_local_after_email'     => ['required', 'in:true,false,1,0'],
            'voicemail_tutorial'              => ['required', 'in:true,false,1,0'],
            'voicemail_id'                    => ['required', 'string', 'max:60'],
            'voicemail_password'              => ['required', 'string', 'max:20'],

        ];
    }

    public function messages()
    {
        return [];
    }

    public function prepareForValidation()
    {
        $first = $this->input('directory_first_name', '');
        $last = $this->input('directory_last_name', '');

        $fullName = trim($first . ' ' . $last); // Will work even if $last is empty

        $voicemailPassword = $this->extension;
        if (get_domain_setting('password_complexity')) {
            $voicemailPassword = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        $this->merge([
            'effective_caller_id_name' => $fullName,
            'effective_caller_id_number' => $this->extension,
            'user_context' => $this->input('user_context', session('domain_name')),
            'accountcode' => $this->input('accountcode', session('domain_name')),
            'domain_uuid' => $this->input('domain_uuid', session('domain_uuid')),
            'password' => generate_password(),
            'directory_visible' => 'true',
            'directory_exten_visible' => 'true',

            // Voicemail defaults
            'voicemail_enabled' => 'true',
            'voicemail_transcription_enabled' => 'true',
            'voicemail_recording_instructions' => 'true',
            'voicemail_file' => 'attach',
            'voicemail_local_after_email' => 'true',
            'voicemail_tutorial' => 'true',
            'voicemail_id' => $this->extension,
            'voicemail_password' => $voicemailPassword,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $domain_uuid = $this->input('domain_uuid', session('domain_uuid'));
            $maxExtensions = get_limit_setting('extensions', $domain_uuid);

            if ($maxExtensions !== null) {
                $limit_error = get_domain_setting('extension_limit_error', $domain_uuid) ?? 'You have reached the maximum number of extensions allowed (%d).';
                $currentCount = \App\Models\Extensions::where('domain_uuid', $domain_uuid)->count();

                if ($currentCount >= $maxExtensions) {
                    $validator->errors()->add(
                        'extension',
                        [sprintf($limit_error, $maxExtensions)]
                    );
                }
            }
        });
    }
}
