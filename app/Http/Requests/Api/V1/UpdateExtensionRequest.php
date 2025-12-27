<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\UniqueExtension;

class UpdateExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        $domainUuid = (string) $this->route('domain_uuid');
        $currentUuid = (string) $this->route('extension_uuid');

        return [
            // allow updating the extension number, but it must remain unique within domain
            'extension' => ['sometimes', 'numeric', new UniqueExtension($currentUuid, $domainUuid)],

            'directory_first_name' => ['sometimes', 'string', 'max:60'],
            'directory_last_name'  => ['sometimes', 'nullable', 'string', 'max:60'],

            'voicemail_mail_to' => ['sometimes', 'nullable', 'email', 'max:255'],
            'voicemail_sms_to'  => ['sometimes', 'nullable', 'string'],

            'description' => ['sometimes', 'nullable', 'string'],
            'suspended'   => ['sometimes', 'nullable', 'boolean'],

            'do_not_disturb' => ['sometimes', 'boolean'],
            'enabled'        => ['sometimes', 'boolean'],
            'call_timeout'   => ['sometimes', 'string'],

            'directory_visible'       => ['sometimes', 'boolean'],
            'directory_exten_visible' => ['sometimes', 'boolean'],

            'user_record' => ['sometimes', 'nullable', 'boolean'],

            'call_screen_enabled' => ['sometimes', 'boolean'],

            'max_registrations' => ['sometimes', 'nullable', 'string'],
            'limit_max'         => ['sometimes', 'nullable', 'string'],
            'limit_destination' => ['sometimes', 'nullable', 'string'],
            'toll_allow'        => ['sometimes', 'nullable', 'string'],
            'call_group'        => ['sometimes', 'nullable', 'string'],
            'hold_music'        => ['sometimes', 'nullable', 'string'],
            'cidr'              => ['sometimes', 'nullable', 'string'],

            'sip_force_contact' => ['sometimes', 'nullable', 'string'],
            'sip_force_expires' => ['sometimes', 'nullable', 'string'],
            'sip_bypass_media'  => ['sometimes', 'nullable', 'string'],

            'mwi_account'           => ['sometimes', 'nullable', 'string'],
            'absolute_codec_string' => ['sometimes', 'nullable', 'string'],
            'dial_string'           => ['sometimes', 'nullable', 'string'],
            'force_ping'            => ['sometimes', 'nullable', 'string'],

            'auth_acl' => ['sometimes', 'nullable', 'string'],

            'outbound_caller_id_number'  => ['sometimes', 'nullable', 'string'],
            'emergency_caller_id_number' => ['sometimes', 'nullable', 'string'],
            'outbound_caller_id_name'    => ['sometimes', 'nullable', 'string'],
            'emergency_caller_id_name'   => ['sometimes', 'nullable', 'string'],

            // Voicemail control:
            // - If omitted: keep existing behavior (no change)
            // - If provided:
            //    - true => ensure voicemail exists (create/update)
            //    - false => disable/delete voicemail (you choose behavior)
            'voicemail_enabled' => ['sometimes', 'boolean'],

            'voicemail_id' => ['sometimes'],
            'voicemail_file' => ['sometimes', 'nullable'],

            'voicemail_local_after_email'      => ['sometimes', 'boolean'],
            'voicemail_transcription_enabled'  => ['sometimes', 'boolean'],
            'voicemail_description'            => ['sometimes', 'nullable', 'string'],
            'voicemail_password'               => ['sometimes', 'nullable', 'numeric'],
            'voicemail_tutorial'               => ['sometimes', 'boolean'],
            'voicemail_recording_instructions' => ['sometimes', 'boolean'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            // --- Core identity ---
            'extension' => [
                'description' => 'Numeric extension number. If omitted, value is unchanged.',
                'example' => '1001',
            ],
            'directory_first_name' => [
                'description' => 'First name for directory display. If omitted, value is unchanged.',
                'example' => 'Front',
            ],
            'directory_last_name' => [
                'description' => 'Last name for directory display. If omitted, value is unchanged.',
                'example' => 'Desk',
            ],

            // --- Labels / status ---
            'description' => [
                'description' => 'Optional description/label. If omitted, value is unchanged.',
                'example' => 'Main reception phone',
            ],
            'suspended' => [
                'description' => 'Whether the extension is suspended. If omitted, value is unchanged.',
                'example' => 'false',
            ],
            'enabled' => [
                'description' => 'Whether the extension is enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'do_not_disturb' => [
                'description' => 'Enable Do Not Disturb for this extension. If omitted, value is unchanged.',
                'example' => 'false',
            ],
            'call_timeout' => [
                'description' => 'Ring timeout in seconds. If omitted, value is unchanged.',
                'example' => '25',
            ],

            // --- Directory visibility ---
            'directory_visible' => [
                'description' => 'Whether this user is visible in the directory (dial-by-name). If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'directory_exten_visible' => [
                'description' => 'Whether the extension number is visible in the directory (dial-by-name). If omitted, value is unchanged.',
                'example' => 'true',
            ],

            // --- Recording / screening ---
            'user_record' => [
                'description' => 'Whether call recording is enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'call_screen_enabled' => [
                'description' => 'Whether call screening is enabled. If omitted, value is unchanged.',
                'example' => 'false',
            ],

            // --- Limits / registrations ---
            'max_registrations' => [
                'description' => 'Maximum allowed SIP registrations for this extension. If omitted, value is unchanged.',
                'example' => '1',
            ],
            'limit_max' => [
                'description' => 'Max number of allowed outgoing calls. If omitted, value is unchanged.',
                'example' => '5',
            ],
            'limit_destination' => [
                'description' => 'Limit destination. If omitted, value is unchanged.',
                'example' => '!USER_BUSY',
            ],

            // --- Dial permissions / groups ---
            'toll_allow' => [
                'description' => 'Toll allow string (e.g., domestic, international, local). If omitted, value is unchanged.',
                'example' => 'domestic',
            ],
            'call_group' => [
                'description' => 'Call group string. Users in the same call group can use call pickup/intercept. If omitted, value is unchanged.',
                'example' => 'sales',
            ],

            // --- Music on hold ---
            'hold_music' => [
                'description' => 'Music on hold source/category. If omitted, value is unchanged.',
                'example' => 'local_stream://default',
            ],

            // --- Network / security ---
            'cidr' => [
                'description' => 'CIDR/network restriction (if used by your security/dialplan rules). If omitted, value is unchanged.',
                'example' => '192.168.1.0/24',
            ],
            'auth_acl' => [
                'description' => 'Auth ACL name (if used for registration/auth restrictions). If omitted, value is unchanged.',
                'example' => 'localnet',
            ],

            // --- SIP knobs ---
            'sip_force_contact' => [
                'description' => 'SIP force-contact setting. If omitted, value is unchanged.',
                'example' => 'NDLB-connectile-dysfunction',
            ],
            'sip_force_expires' => [
                'description' => 'SIP force-expires setting. If omitted, value is unchanged.',
                'example' => '300',
            ],
            'sip_bypass_media' => [
                'description' => 'SIP bypass-media setting. If omitted, value is unchanged.',
                'example' => 'false',
            ],
            'mwi_account' => [
                'description' => 'MWI account string. If omitted, value is unchanged.',
                'example' => '1001@your-domain.com',
            ],
            'absolute_codec_string' => [
                'description' => 'Absolute codec string. If omitted, value is unchanged.',
                'example' => 'PCMU,PCMA,OPUS',
            ],
            'dial_string' => [
                'description' => 'Custom dial string. If omitted, value is unchanged.',
                'example' => '{sip_invite_domain=${domain_name}}${sofia_contact(${dialed_user}@${dialed_domain})}',
            ],
            'force_ping' => [
                'description' => 'Force ping setting. If omitted, value is unchanged.',
                'example' => 'true',
            ],

            // --- Caller ID ---
            'outbound_caller_id_number' => [
                'description' => 'Outbound caller ID number (E.164 recommended). If omitted, value is unchanged.',
                'example' => '+12135551212',
            ],
            'outbound_caller_id_name' => [
                'description' => 'Outbound caller ID name. If omitted, value is unchanged.',
                'example' => 'Front Desk',
            ],
            'emergency_caller_id_number' => [
                'description' => 'Emergency caller ID number (E.164 recommended). If omitted, value is unchanged.',
                'example' => '+12135559876',
            ],
            'emergency_caller_id_name' => [
                'description' => 'Emergency caller ID name. If omitted, value is unchanged.',
                'example' => 'Front Desk',
            ],

            // --- Voicemail ---
            'voicemail_enabled' => [
                'description' => 'Whether voicemail should be enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'voicemail_password' => [
                'description' => 'Voicemail PIN/password. If omitted, value is unchanged.',
                'example' => '1001',
            ],
            // --- Contact / notifications ---
            'voicemail_mail_to' => [
                'description' => 'Email address for voicemail-to-email notifications. If omitted, value is unchanged.',
                'example' => 'frontdesk@example.com',
            ],
            'voicemail_sms_to' => [
                'description' => 'Optional SMS destination for voicemail alerts. If omitted, value is unchanged.',
                'example' => '+12135551212',
            ],
            'voicemail_file' => [
                'description' => 'Voicemail delivery mode (e.g., attach). If omitted, value is unchanged.',
                'example' => 'attach',
            ],
            'voicemail_local_after_email' => [
                'description' => 'Whether to keep voicemail local after emailing it. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'voicemail_transcription_enabled' => [
                'description' => 'Whether voicemail transcription is enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'voicemail_tutorial' => [
                'description' => 'Whether voicemail tutorial is enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'voicemail_recording_instructions' => [
                'description' => 'Whether voicemail recording instructions are enabled. If omitted, value is unchanged.',
                'example' => 'true',
            ],
            'voicemail_description' => [
                'description' => 'Optional voicemail description/label. If omitted, value is unchanged.',
                'example' => 'Front Desk Voicemail',
            ],
            'voicemail_destinations' => [
                'description' => 'Optional voicemail destinations array (if used by your routing). If omitted, value is unchanged.',
                'example' => '[]',
            ],
        ];
    }
}
