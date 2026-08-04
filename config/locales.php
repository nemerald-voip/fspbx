<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Used whenever a domain (or the global default) has no language setting,
    | the configured value doesn't match a locale registered below, or a
    | registered locale's own file doesn't define a given key.
    |
    */

    'default' => 'en-us',

    /*
    |--------------------------------------------------------------------------
    | Registered Locales
    |--------------------------------------------------------------------------
    |
    | Every locale the app can serve. Adding a community-contributed language
    | is just a new entry here plus a matching resources/lang/{code}.json
    | file -- `php artisan lang:sync` populates it with every translatable
    | key (blank, ready to fill in) the next time it runs.
    |
    | Every locale falls back straight to the default (en-us) for any key it
    | doesn't define -- there's no dialect-to-dialect inheritance (e.g. a
    | Spanish variant does not inherit from another Spanish variant). Each
    | locale's file is fully independent: what you see in resources/lang/
    | {code}.json is exactly what that locale shows, nothing implied from a
    | sibling file. This was a deliberate simplification -- an earlier
    | version chained regional variants (es-mx -> es-419 -> es-es -> en-us)
    | to avoid full retranslation, but that meant a translator had to
    | understand which of several files to edit for a given string, which
    | was confusing enough to not be worth the savings. If that's ever worth
    | revisiting, see `LocaleRegistry::chain()` -- it already walks a
    | `fallback` key per locale here, so reintroducing chaining is just
    | adding that key back, not new code.
    |
    | Locale codes here are standard lowercased regional identifiers, not a
    | bare language code -- e.g. `es-es`, not `es`.
    |
    */

    'locales' => [
        'en-us' => [
            'name' => 'English',
            'native' => 'English',
        ],
        'es-es' => [
            'name' => 'Spanish',
            'native' => 'Español',
        ],
        'es-419' => [
            'name' => 'Spanish (Latin America)',
            'native' => 'Español (Latinoamérica)',
        ],
        'es-mx' => [
            'name' => 'Spanish (Mexico)',
            'native' => 'Español (México)',
        ],
        'af' => [
            'name' => 'Afrikaans',
            'native' => 'Afrikaans',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
        ],
        'ca' => [
            'name' => 'Catalan',
            'native' => 'Català',
        ],
        'zh-cn' => [
            'name' => 'Chinese (Simplified)',
            'native' => '简体中文',
        ],
        'zh-tw' => [
            'name' => 'Chinese (Traditional)',
            'native' => '繁體中文',
        ],
        'cs' => [
            'name' => 'Czech',
            'native' => 'Čeština',
        ],
        'da' => [
            'name' => 'Danish',
            'native' => 'Dansk',
        ],
        'nl' => [
            'name' => 'Dutch',
            'native' => 'Nederlands',
        ],
        'fi' => [
            'name' => 'Finnish',
            'native' => 'Suomi',
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
        ],
        'de' => [
            'name' => 'German',
            'native' => 'Deutsch',
        ],
        'el' => [
            'name' => 'Greek',
            'native' => 'Ελληνικά',
        ],
        'he' => [
            'name' => 'Hebrew',
            'native' => 'עברית',
        ],
        'hu' => [
            'name' => 'Hungarian',
            'native' => 'Magyar',
        ],
        'it' => [
            'name' => 'Italian',
            'native' => 'Italiano',
        ],
        'ja' => [
            'name' => 'Japanese',
            'native' => '日本語',
        ],
        'ko' => [
            'name' => 'Korean',
            'native' => '한국어',
        ],
        'no' => [
            'name' => 'Norwegian',
            'native' => 'Norsk',
        ],
        'pl' => [
            'name' => 'Polish',
            'native' => 'Polski',
        ],
        'pt-pt' => [
            'name' => 'Portuguese',
            'native' => 'Português',
        ],
        'pt-br' => [
            'name' => 'Portuguese (Brazil)',
            'native' => 'Português (Brasil)',
        ],
        'ro' => [
            'name' => 'Romanian',
            'native' => 'Română',
        ],
        'ru' => [
            'name' => 'Russian',
            'native' => 'Русский',
        ],
        'sr' => [
            'name' => 'Serbian (Cyrillic)',
            'native' => 'Српски',
        ],
        'sv-se' => [
            'name' => 'Swedish',
            'native' => 'Svenska',
        ],
        'tr' => [
            'name' => 'Turkish',
            'native' => 'Türkçe',
        ],
        'uk' => [
            'name' => 'Ukrainian',
            'native' => 'Українська',
        ],
        'vi' => [
            'name' => 'Vietnamese',
            'native' => 'Tiếng Việt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Completion To Offer
    |--------------------------------------------------------------------------
    |
    | Share (0-1) of the default locale's own string keys a locale must define
    | before it's considered "ready" to offer on the Domain Settings language
    | picker. Partial community translations can still exist and be worked on
    | below this bar; they just aren't surfaced to tenants yet.
    |
    */

    'minimum_completion' => 0.6,

];
