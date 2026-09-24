<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StreamLocation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['shout', 'shouts'], true)
            || empty($parts['host']) || preg_match('/[\s\x00-\x1f\x7f]/', $value)
            || isset($parts['fragment']) || str_contains(substr($value, strpos($value, '://') + 3), '://')) {
            $fail(__('Enter shout://host[:port]/path for HTTP or shouts://host[:port]/path for HTTPS. Use the direct MP3 audio address, with spaces encoded as %20.'));
            return;
        }

        if (preg_match('/\.(m3u8?|pls|asx|aac|ogg|opus)(?:$)/i', $parts['path'] ?? '')) {
            $fail(__('Use the direct MP3 audio endpoint. Playlist, HLS, AAC, Ogg, and Opus addresses are not supported by this stream player.'));
        }
    }
}
