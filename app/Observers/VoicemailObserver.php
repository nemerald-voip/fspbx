<?php

namespace App\Observers;

use App\Models\VoicemailDestinations;
use App\Models\Voicemails;

class VoicemailObserver
{
    public function deleting(Voicemails $voicemail): void
    {
        // Shared by mailbox and extension deletion, including their API actions.
        VoicemailDestinations::query()
            ->where('domain_uuid', $voicemail->domain_uuid)
            ->where(function ($query) use ($voicemail) {
                $query->where('voicemail_uuid', $voicemail->voicemail_uuid)
                    ->orWhere('voicemail_uuid_copy', $voicemail->voicemail_uuid);
            })
            ->delete();
    }
}
