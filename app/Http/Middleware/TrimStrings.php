<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function transform($key, $value)
    {
        // Literal spaces are meaningful in number-translation expressions and replacements.
        if (request()->is('api/system-settings/number-translations*')
            && preg_match('/^rules\.\d+\.(regex|replace)$/', $key)) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
