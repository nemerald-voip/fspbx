<?php

namespace App\Services\Messaging\Providers\Concerns;

trait ExtractsMediaFilename
{
    protected function extractFilenameFromContentDisposition(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $header, $matches)) {
            return rawurldecode(trim($matches[1], '"'));
        }

        if (preg_match('/filename="?([^"]+)"?/i', $header, $matches)) {
            return trim($matches[1], '"');
        }

        return null;
    }
}