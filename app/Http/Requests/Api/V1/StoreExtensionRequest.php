<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use App\Rules\UniqueExtension;

class StoreExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        return [
            // extension number is required and must be unique
            'extension' => ['required', 'numeric', new UniqueExtension(null, (string) $this->route('domain_uuid')),],

            'directory_first_name' => ['required', 'string', 'max:60'],
            'directory_last_name'  => ['nullable', 'string', 'max:60'],

            'voicemail_mail_to' => ['nullable', 'email', 'max:255'],
            'voicemail_sms_to'  => ['nullable', 'string'],

            'description' => ['nullable', 'string'],
            'suspended'   => ['nullable', 'boolean'],

            'do_not_disturb' => ['sometimes',  'boolean'],
            'enabled'        => ['sometimes', 'boolean'],
            'call_timeout'   => ['sometimes', 'string'],

            'directory_visible'       => ['sometimes', 'boolean'],
            'directory_exten_visible' => ['sometimes', 'boolean'],

            'user_record' => ['nullable', 'boolean'],

            'call_screen_enabled' => ['sometimes', 'boolean'],

            'max_registrations' => ['nullable', 'string'],
            'limit_max'         => ['nullable', 'string'],
            'limit_destination' => ['nullable', 'string'],
            'toll_allow'        => ['nullable', 'string'],
            'call_group'        => ['nullable', 'string'],
            'hold_music'        => ['nullable', 'string'],
            'cidr'              => ['nullable', 'string'],

            'sip_force_contact' => ['nullable', 'string'],
            'sip_force_expires' => ['nullable', 'string'],
            'sip_bypass_media'  => ['nullable', 'string'],

            'mwi_account'             => ['nullable', 'string'],
            'absolute_codec_string'   => ['nullable', 'string'],
            'dial_string'             => ['nullable', 'string'],
            'force_ping'              => ['nullable', 'string'],

            'auth_acl' => ['nullable', 'string'],

            'outbound_caller_id_number'   => ['nullable', 'string'],
            'emergency_caller_id_number'  => ['nullable', 'string'],
            'outbound_caller_id_name'     => ['nullable', 'string'],
            'emergency_caller_id_name'    => ['nullable', 'string'],

            // Voicemail fields
            'voicemail_enabled' => ['required', 'boolean'],

            'voicemail_id' => ['sometimes'],

            'voicemail_file' => ['nullable'],

            'voicemail_local_after_email'        => ['sometimes', 'boolean'],
            'voicemail_transcription_enabled'    => ['sometimes', 'boolean'],
            'voicemail_description'              => ['nullable', 'string'],
            'voicemail_destinations'             => ['nullable', 'array'],
            'voicemail_password'                 => ['nullable', 'numeric'],
            'voicemail_tutorial'                 => ['sometimes', 'boolean'],
            'voicemail_recording_instructions'   => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation()
    {
        if (!$this->has('suspended')) {
            $this->merge(['suspended' => false]);
        }
    }

    public function bodyParameters(): array
    {
        return [
            // --- Core identity ---
            'extension' => [
                'description' => 'Numeric extension number.',
                'example' => '1001',
            ],
            'directory_first_name' => [
                'description' => 'First name for directory display.',
                'example' => 'Front',
            ],
            'directory_last_name' => [
                'description' => 'Last name for directory display.',
                'example' => 'Desk',
            ],

            // --- Labels / status ---
            'description' => [
                'description' => 'Optional description/label.',
                'example' => 'Main reception phone',
            ],
            'suspended' => [
                'description' => 'Whether the extension is suspended. Defaults to false if omitted.',
                'example' => 'false',
            ],
            'do_not_disturb' => [
                'description' => 'Enable Do Not Disturb for this extension. Defaults to false if omitted.',
                'example' => 'false',
            ],
            'enabled' => [
                'description' => 'Whether the extension is enabled. Defaults to true if omitted.',
                'example' => 'true',
            ],
            'call_timeout' => [
                'description' => 'Ring timeout in seconds. Defaults to 25',
                'example' => '25',
            ],

            // --- Directory visibility ---
            'directory_visible' => [
                'description' => 'Whether this user is visible in the directory (dial-by-name). Defaults to true if omitted.',
                'example' => 'true',
            ],
            'directory_exten_visible' => [
                'description' => 'Whether the extension number is visible in the directory (dial-by-name). Defaults to true if omitted.',
                'example' => 'true',
            ],

            // --- Recording / screening ---
            'user_record' => [
                'description' => 'Whether call recording is enabled. Defaults to null if omitted.',
                'example' => 'true',
            ],
            'call_screen_enabled' => [
                'description' => 'Whether call screening is enabled. Defaults to false if omitted.',
                'example' => 'false',
            ],

            // --- Limits / registrations ---
            'max_registrations' => [
                'description' => 'Maximum allowed SIP registrations for this extension. Defaults to null if omitted.',
                'example' => '1',
            ],
            'limit_max' => [
                'description' => 'Max number of allowed outgoing calls. Defaults to 5 if omitted.',
                'example' => '5',
            ],
            'limit_destination' => [
                'description' => 'Limit destination. Defaults to !USER_BUSY if omitted.',
                'example' => '!USER_BUSY',
            ],

            // --- Dial permissions / groups ---
            'toll_allow' => [
                'description' => 'Toll allow string (e.g., domestic,international,local). Defaults to null if omitted.',
                'example' => 'domestic',
            ],
            'call_group' => [
                'description' => 'Call group string. A user in a call group can perform a call pickup (or an intercept) of a ringing phone belonging to another user who is also in the call group. Defaults to null if omitted.',
                'example' => 'sales',
            ],

            // --- Music on hold ---
            'hold_music' => [
                'description' => 'Music on hold source/category. Defaults to null if omitted.',
                'example' => 'local_stream://default',
            ],

            // --- Network / security ---
            'cidr' => [
                'description' => 'CIDR/network restriction (if used by your security/dialplan rules). Defaults to null if omitted.',
                'example' => '192.168.1.0/24',
            ],
            'auth_acl' => [
                'description' => 'Auth ACL name (if used for registration/auth restrictions). Defaults to null if omitted.',
                'example' => 'localnet',
            ],

            // --- SIP knobs ---
            'sip_force_contact' => [
                'description' => 'SIP force-contact setting.',
                'example' => 'NDLB-connectile-dysfunction',
            ],
            'sip_force_expires' => [
                'description' => 'SIP force-expires setting.',
                'example' => '300',
            ],
            'sip_bypass_media' => [
                'description' => 'SIP bypass-media setting. Defaults to null if omitted.',
                'example' => 'false',
            ],
            'mwi_account' => [
                'description' => 'MWI account string.',
                'example' => '1001@your-domain.com',
            ],
            'absolute_codec_string' => [
                'description' => 'Absolute codec string.',
                'example' => 'PCMU,PCMA,OPUS',
            ],
            'dial_string' => [
                'description' => 'Custom dial string.',
                'example' => '{sip_invite_domain=${domain_name}}${sofia_contact(${dialed_user}@${dialed_domain})}',
            ],
            'force_ping' => [
                'description' => 'Force ping setting. Defaults to false if omitted.',
                'example' => 'true',
            ],

            // --- Caller ID ---
            'outbound_caller_id_number' => [
                'description' => 'Outbound caller ID number (E.164 recommended).',
                'example' => '+12135551212',
            ],
            'outbound_caller_id_name' => [
                'description' => 'Outbound caller ID name.',
                'example' => 'Front Desk',
            ],
            'emergency_caller_id_number' => [
                'description' => 'Emergency caller ID number (E.164 recommended)',
                'example' => '+12135559876',
            ],
            'emergency_caller_id_name' => [
                'description' => 'Emergency caller ID name.',
                'example' => 'Front Desk',
            ],

            // --- Voicemail ---
            'voicemail_enabled' => [
                'description' => 'Whether voicemail should be created/enabled. If true, a voicemail box will be created. If false, no voicemail box is created.',
                'example' => 'true',
            ],
            'voicemail_password' => [
                'description' => 'Voicemail PIN/password. If omitted, defaults to the extension number unless password complexity is enabled (then a random PIN is generated).',
                'example' => '1001',
            ],
            // --- Notifications / contact ---
            'voicemail_mail_to' => [
                'description' => 'Email address for voicemail-to-email notifications.',
                'example' => 'frontdesk@example.com',
            ],
            'voicemail_sms_to' => [
                'description' => 'Optional SMS destination for voicemail alerts. ',
                'example' => '+12135551212',
            ],
            'voicemail_file' => [
                'description' => 'Voicemail delivery mode (e.g., attach). Defaults to "attach" if omitted.',
                'example' => 'attach',
            ],
            'voicemail_local_after_email' => [
                'description' => 'Whether to keep voicemail local after emailing it. Defaults to true if omitted.',
                'example' => 'true',
            ],
            'voicemail_transcription_enabled' => [
                'description' => 'Whether voicemail transcription is enabled. Defaults to true if omitted.',
                'example' => 'true',
            ],
            'voicemail_tutorial' => [
                'description' => 'Whether voicemail tutorial is enabled. Defaults to true if omitted.',
                'example' => 'true',
            ],
            'voicemail_recording_instructions' => [
                'description' => 'Whether voicemail recording instructions are enabled. Defaults to true if omitted.',
                'example' => 'true',
            ],
            'voicemail_description' => [
                'description' => 'Optional voicemail description/label.',
                'example' => 'Front Desk Voicemail',
            ],
        ];
    }
}
