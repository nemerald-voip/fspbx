<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\UniqueExtension;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoicemailRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        return [
            // voicemail box number is required and must be unique within the domain
            'voicemail_id' => [
                'required',
                'numeric',
                new UniqueExtension(null, (string) $this->route('domain_uuid')),
            ],

            'voicemail_password' => ['nullable', 'numeric', new ValidVoicemailPassword],

            // Contact / notifications
            'voicemail_mail_to' => ['nullable', 'email', 'max:255'],
            'voicemail_sms_to'  => ['nullable', 'string'],

            // Status
            'voicemail_enabled' => ['sometimes', 'boolean'],

            // Behavior / delivery
            'voicemail_file' => ['nullable', 'string'],
            'voicemail_local_after_email'      => ['sometimes', 'boolean'],
            'voicemail_transcription_enabled'  => ['sometimes', 'boolean'],
            'voicemail_tutorial'               => ['sometimes', 'boolean'],
            'voicemail_recording_instructions' => ['sometimes', 'boolean'],

            // Labels / misc
            'voicemail_description' => ['nullable', 'string'],
            'greeting_id' => ['nullable', 'numeric'],
            'voicemail_alternate_greet_id' => ['nullable', 'numeric'],

        ];
    }

    public function bodyParameters(): array
    {
        return [
            // --- Core identity ---
            'voicemail_id' => [
                'description' => 'Numeric voicemail box number.',
                'example' => '1001',
            ],
            'voicemail_password' => [
                'description' => 'Voicemail PIN/password. If omitted, the server may default it (e.g., to the voicemail_id) or generate a random PIN when password complexity is enabled.',
                'example' => '1001',
            ],

            // --- Notifications / contact ---
            'voicemail_mail_to' => [
                'description' => 'Email address for voicemail-to-email notifications.',
                'example' => 'frontdesk@example.com',
            ],
            'voicemail_sms_to' => [
                'description' => 'Optional SMS destination for voicemail alerts.',
                'example' => '+12135551212',
            ],

            // --- Status ---
            'voicemail_enabled' => [
                'description' => 'Whether the voicemail box is enabled. Defaults to true if omitted.',
                'example' => 'true',
            ],

            // --- Delivery / behavior ---
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

            // --- Labels / greetings ---
            'voicemail_description' => [
                'description' => 'Optional voicemail description/label.',
                'example' => 'Front Desk Voicemail',
            ],
            'greeting_id' => [
                'description' => 'Optional greeting ID to use for this mailbox.',
                'example' => '3',
            ],
            'voicemail_alternate_greet_id' => [
                'description' => 'Optional alternate greeting ID. It will be used with default voicemail greeting.',
                'example' => '200',
            ],

        ];
    }
}
