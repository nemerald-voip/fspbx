<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\UniqueExtension;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoicemailRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        $domainUuid = (string) $this->route('domain_uuid');
        $voicemailUuid = (string) $this->route('voicemail_uuid');

        return [
            // Identity
            'voicemail_id' => [
                'sometimes',
                'numeric',
                new UniqueExtension($voicemailUuid, $domainUuid),
            ],

            // Credentials / contact
            'voicemail_password' => ['sometimes', 'numeric', new ValidVoicemailPassword],
            'voicemail_mail_to'  => ['sometimes', 'nullable', 'email', 'max:255'],
            'voicemail_sms_to'   => ['sometimes', 'nullable', 'string', 'max:255'],

            // Status / behavior flags
            'voicemail_transcription_enabled' => ['sometimes', 'boolean'],
            'voicemail_local_after_email'     => ['sometimes', 'boolean'],
            'voicemail_tutorial'              => ['sometimes', 'boolean'],
            'voicemail_recording_instructions' => ['sometimes', 'boolean'],

            // Delivery / greeting / label
            'voicemail_file'               => ['sometimes', 'nullable', 'string', 'max:50'],
            'voicemail_description'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'greeting_id'                  => ['sometimes', 'nullable', 'integer'],
            'voicemail_alternate_greet_id' => ['sometimes', 'nullable', 'integer'],

            'voicemail_enabled'               => ['sometimes', 'boolean'],

        ];
    }

    public function bodyParameters(): array
    {
        return [
            'voicemail_id' => [
                'description' => 'Numeric voicemail box number. Must be unique within the domain.',
                'example' => '1001',
            ],

            'voicemail_password' => [
                'description' => 'Voicemail PIN/password. If omitted, the current PIN is unchanged.',
                'example' => '1001',
            ],

            'voicemail_mail_to' => [
                'description' => 'Email address for voicemail-to-email notifications. If omitted, the current value is unchanged. Use null to clear.',
                'example' => 'frontdesk@example.com',
            ],

            'voicemail_sms_to' => [
                'description' => 'Optional SMS destination for voicemail alerts. If omitted, unchanged. Use null to clear.',
                'example' => '+12135551212',
            ],

            'voicemail_enabled' => [
                'description' => 'Whether voicemail is enabled. If omitted, unchanged.',
                'example' => 'true',
            ],

            'voicemail_transcription_enabled' => [
                'description' => 'Whether voicemail transcription is enabled. If omitted, unchanged.',
                'example' => 'true',
            ],

            'voicemail_local_after_email' => [
                'description' => 'Whether to keep voicemail local after emailing it. If omitted, unchanged.',
                'example' => 'true',
            ],

            'voicemail_tutorial' => [
                'description' => 'Whether voicemail tutorial is enabled. If omitted, unchanged.',
                'example' => 'true',
            ],

            'voicemail_recording_instructions' => [
                'description' => 'Whether voicemail recording instructions are enabled. If omitted, unchanged.',
                'example' => 'true',
            ],

            'voicemail_file' => [
                'description' => 'Voicemail delivery mode (e.g., attach). If omitted, unchanged.',
                'example' => 'attach',
            ],

            'voicemail_description' => [
                'description' => 'Optional voicemail description/label. If omitted, unchanged. Use null to clear.',
                'example' => 'Front Desk Voicemail (updated)',
            ],

            'greeting_id' => [
                'description' => 'Greeting ID to use for this voicemail (if applicable). If omitted, unchanged. Use null to clear.',
                'example' => '3',
            ],

            'voicemail_alternate_greet_id' => [
                'description' => 'Alternate greeting ID (if applicable). If omitted, unchanged. Use null to clear.',
                'example' => '200',
            ],

        ];
    }
}
