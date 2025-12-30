<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class VoicemailData extends Data
{
    public function __construct(
        public ?string $voicemail_uuid,
        public string $object,
        public string $domain_uuid,
        public ?string $voicemail_id,
        public ?string $voicemail_password,
        public ?string $greeting_id,
        public ?int $voicemail_alternate_greet_id,
        public ?string $voicemail_mail_to,
        public ?string $voicemail_sms_to,
        public ?bool $voicemail_transcription_enabled,
        public ?string $voicemail_file,
        public ?bool $voicemail_local_after_email,
        public ?string $voicemail_description,
        public ?bool $voicemail_tutorial,
        public ?bool $voicemail_recording_instructions,
        public ?bool $voicemail_enabled,
    ) {}
}

