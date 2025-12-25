<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\StoreExtensionRequest as InternalStoreExtensionRequest;

class StoreExtensionRequest extends InternalStoreExtensionRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function bodyParameters(): array
    {
        return [
            'extension' => [
                'description' => 'Extension number.',
                'example' => '1001',
            ],
            'directory_first_name' => [
                'description' => 'Directory first name.',
                'example' => 'Front',
            ],
            'directory_last_name' => [
                'description' => 'Directory last name.',
                'example' => 'Desk',
            ],
            'description' => [
                'description' => 'Optional label/description for the extension.',
                'example' => 'Main reception phone',
            ],
            'voicemail_mail_to' => [
                'description' => 'Optional voicemail-to-email address.',
                'example' => 'frontdesk@example.com',
            ],

            // Optional parameters
            
            'directory_visible' => [
                'description' => 'Whether the user appears in the directory. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'directory_exten_visible' => [
                'description' => 'Whether the extension number is visible in the directory. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_enabled' => [
                'description' => 'Whether voicemail is enabled. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_transcription_enabled' => [
                'description' => 'Whether voicemail transcription is enabled. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_recording_instructions' => [
                'description' => 'Whether voicemail recording instructions are enabled. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_file' => [
                'description' => 'Voicemail delivery mode/file type (e.g. attach).',
                'example' => 'attach',
            ],
            'voicemail_local_after_email' => [
                'description' => 'Whether to keep voicemail local after emailing. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_tutorial' => [
                'description' => 'Whether voicemail tutorial is enabled. Accepts true/false/1/0.',
                'example' => 'true',
            ],
            'voicemail_id' => [
                'description' => 'Voicemail box ID (often same as extension).',
                'example' => '1001',
            ],
            'voicemail_password' => [
                'description' => 'Voicemail PIN/password.',
                'example' => '1001',
            ],
        ];
    }
}
