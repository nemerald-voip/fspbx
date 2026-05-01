<?php

namespace App\Services;

use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class VoicemailMessageUrlService
{
    public function downloadUrlForMessage(VoicemailMessages $message, int $ttlSeconds = 604800): ?string
    {
        if (!$this->resolveMessageFile($message)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'voicemails.messages.public-download',
            now()->addSeconds($ttlSeconds),
            ['message_uuid' => $message->voicemail_message_uuid]
        );
    }

    public function resolveMessageFile(VoicemailMessages $message): ?array
    {
        $message->loadMissing([
            'domain:domain_uuid,domain_name',
            'voicemail:voicemail_uuid,voicemail_id',
        ]);

        $domainName = $message->domain?->domain_name;
        $voicemailId = $message->voicemail?->voicemail_id;

        if (!$domainName || !$voicemailId) {
            return null;
        }

        $basePath = $domainName . '/' . $voicemailId . '/msg_' . $message->voicemail_message_uuid;
        $disk = Storage::disk('voicemail');

        foreach (['wav', 'mp3'] as $extension) {
            $path = $basePath . '.' . $extension;

            if ($disk->exists($path)) {
                return [
                    'path' => $path,
                    'filename' => 'msg_' . $message->voicemail_message_uuid . '.' . $extension,
                    'mime' => $extension === 'mp3' ? 'audio/mpeg' : 'audio/wav',
                ];
            }
        }

        return null;
    }
}
