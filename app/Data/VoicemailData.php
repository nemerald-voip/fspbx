<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class VoicemailData extends Data
{
    public function __construct(
        public string $voicemail_uuid,
        public string $voicemail_id,
        public ?string $voicemail_password,
        public ?int $greeting_id,
        public ?int $voicemail_alternate_greet_id,
        public ?string $voicemail_mail_to,
        public ?string $voicemail_transcription_enabled,
        public ?string $voicemail_file,
        public ?string $voicemail_local_after_email,
        public ?string $voicemail_enabled,
        public ?string $voicemail_description,
        public ?string $voicemail_tutorial,
        public ?string $voicemail_recording_instructions,
        public ?array $voicemail_destinations = null,
    ) {}
}

