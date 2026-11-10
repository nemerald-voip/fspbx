<?php

namespace App\Services\Messaging\Providers\Concerns;

trait ExtractsMmsText
{
    /** Extract phone-originated MMS text parts before storing attachments or matching keywords. */
    protected function extractMmsText(string $text, array $mediaUrls): array
    {
        $attachments = [];
        foreach ($mediaUrls as $url) {
            if (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) === 'txt') {
                $part = $this->downloadMedia($url);
                if (str_starts_with(strtolower($part->mimeType ?? ''), 'text/plain')) {
                    if (strlen($part->binary) > 65536) throw new \RuntimeException('MMS text part is too large.');
                    $body = trim($part->binary);
                    if ($body !== '' && $body !== trim($text)) $text = trim($text."\n".$body);
                    continue;
                }
            }
            $attachments[] = $url;
        }

        return [$text, $attachments];
    }
}
