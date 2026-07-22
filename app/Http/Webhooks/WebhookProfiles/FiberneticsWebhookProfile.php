<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class FiberneticsWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $shouldProcess = filled($request->input('from'))
            && filled($request->input('to'))
            && ($request->has('message') || $request->has('binary'));

        if (! $shouldProcess) {
            return false;
        }

        $updates = [];
        $charset = strtoupper(trim((string) $request->input('charset', '')));
        $message = $request->input('message');

        if (is_string($message) && $message !== '' && ! mb_check_encoding($message, 'UTF-8')) {
            try {
                $updates['message'] = mb_convert_encoding($message, 'UTF-8', $charset ?: 'WINDOWS-1252');
                $updates['charset'] = 'UTF-8';
            } catch (\Throwable) {
                // Preserve the callback; binary fields are normalized below.
            }
        }

        foreach (['binary', 'udh', 'metadata'] as $field) {
            $value = $request->input($field);

            if (! is_string($value) || $value === '') {
                continue;
            }

            if ($field === 'binary' || ! mb_check_encoding($value, 'UTF-8')) {
                $updates[$field] = base64_encode($value);
                $updates[$field . '_encoding'] = 'base64';
            }
        }

        if ($updates !== []) {
            $request->merge($updates);
        }

        return true;
    }
}
