<?php

namespace App\Support\Localization;

final class DateTimeNotation
{
    public const EXAMPLES = [
        'date_format' => ['us' => 'Jan 31, 2026', 'eu' => '31/01/2026', 'iso' => '2026-01-31'],
        'time_format' => ['12h' => '3:04 PM', '24h' => '15:04'],
    ];

    public const DEFAULTS = ['date_format' => 'us', 'time_format' => '12h'];

    public static function normalize(string $key, ?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return isset(self::EXAMPLES[$key][$value]) ? $value : self::DEFAULTS[$key];
    }

    public static function date(string $notation, bool $long = false): string
    {
        return match ($notation) {
            'eu' => $long ? 'j F Y' : 'd/m/Y',
            'iso' => 'Y-m-d',
            default => $long ? 'F j, Y' : 'M d, Y',
        };
    }

    public static function time(string $notation, bool $seconds = true): string
    {
        if ($notation === '24h') {
            return $seconds ? 'H:i:s' : 'H:i';
        }

        return $seconds ? 'g:i:s A' : 'g:i A';
    }
}
