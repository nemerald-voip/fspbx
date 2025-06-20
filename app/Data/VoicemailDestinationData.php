<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class VoicemailDestinationData extends Data
{
    public function __construct(
        public string $voicemail_destination_uuid,
        public string $voicemail_uuid_copy
    ) {}
}
