<?php

namespace App\Support;

use Illuminate\Support\Str;

class BridgeRuntimeDestination
{
    public const PREFIX = 'bridge_';

    public const SCRIPT = 'bridge.lua';

    public static function uuid(?string $target): ?string
    {
        $target = trim((string) $target);

        if (! str_starts_with(strtolower($target), self::PREFIX)) {
            return null;
        }

        $uuid = substr($target, strlen(self::PREFIX));

        return Str::isUuid($uuid) ? strtolower($uuid) : null;
    }

    public static function uuidFromScriptData(?string $scriptData): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $scriptData));

        if (count($parts) !== 2 || $parts[0] !== self::SCRIPT) {
            return null;
        }

        return Str::isUuid($parts[1]) ? strtolower($parts[1]) : null;
    }
}
