<?php

namespace App\Support\Localization;

final class DateTimeNotation
{
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
