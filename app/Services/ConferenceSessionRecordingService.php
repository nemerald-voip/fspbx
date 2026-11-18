<?php

namespace App\Services;

use App\Models\ConferenceSession;

class ConferenceSessionRecordingService
{
    public function path(ConferenceSession $session): ?string
    {
        if ($session->domain_uuid !== session('domain_uuid') || blank($session->recording) || blank(session('domain_name'))) {
            return null;
        }

        $base = session('switch.recordings.dir') ?: config('filesystems.disks.recordings.root');
        $root = realpath(rtrim((string) $base, '/') . '/' . session('domain_name'));
        if ($root === false) {
            return null;
        }

        $recording = str_starts_with($session->recording, '/')
            ? $session->recording
            : $root . '/' . $session->recording;

        // Older rows store the recording name without its audio extension.
        foreach ([$recording, $recording . '.wav', $recording . '.mp3'] as $candidate) {
            $path = realpath($candidate);
            if ($path !== false
                && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
                && is_file($path)
                && is_readable($path)
                && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['wav', 'mp3', 'ogg', 'flac'], true)) {
                return $path;
            }
        }

        return null;
    }
}
