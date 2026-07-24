<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Used whenever a domain (or the global default) has no language setting,
    | or the configured value doesn't match a locale registered below.
    |
    */

    'default' => 'en-us',

    /*
    |--------------------------------------------------------------------------
    | Registered Locales
    |--------------------------------------------------------------------------
    |
    | Every locale the app can serve. Adding a community-contributed language
    | or regional dialect is just a new entry here plus a matching
    | resources/lang/{code}.json file -- no other code change is required.
    |
    | 'fallback' names the parent whose strings are inherited for any key the
    | child doesn't define, so a dialect (e.g. es-mx) only has to override the
    | handful of words that differ from its parent (es-es) instead of shipping
    | a full retranslation. See App\Support\Localization\LocaleRegistry::chain().
    |
    | Locale codes here are Crowdin's own locale IDs, lowercased, so they line
    | up directly with crowdin.yml's languages_mapping and with the
    | resources/lang/{code}.json file Crowdin downloads translations into --
    | e.g. Crowdin's generic "Spanish" is `es-ES`, not a bare `es`.
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
            'fallback' => 'en-us',
        ],
        'es-419' => [
            'name' => 'Spanish (Latin America)',
            'native' => 'Español (Latinoamérica)',
            'fallback' => 'es-es',
        ],
        'es-mx' => [
            'name' => 'Spanish (Mexico)',
            'native' => 'Español (México)',
            'fallback' => 'es-419',
        ],
        'af' => [
            'name' => 'Afrikaans',
            'native' => 'Afrikaans',
            'fallback' => 'en-us',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'fallback' => 'en-us',
        ],
        'ca' => [
            'name' => 'Catalan',
            'native' => 'Català',
            'fallback' => 'en-us',
        ],
        'zh-cn' => [
            'name' => 'Chinese (Simplified)',
            'native' => '简体中文',
            'fallback' => 'en-us',
        ],
        'zh-tw' => [
            'name' => 'Chinese (Traditional)',
            'native' => '繁體中文',
            'fallback' => 'en-us',
        ],
        'cs' => [
            'name' => 'Czech',
            'native' => 'Čeština',
            'fallback' => 'en-us',
        ],
        'da' => [
            'name' => 'Danish',
            'native' => 'Dansk',
            'fallback' => 'en-us',
        ],
        'nl' => [
            'name' => 'Dutch',
            'native' => 'Nederlands',
            'fallback' => 'en-us',
        ],
        'fi' => [
            'name' => 'Finnish',
            'native' => 'Suomi',
            'fallback' => 'en-us',
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'fallback' => 'en-us',
        ],
        'de' => [
            'name' => 'German',
            'native' => 'Deutsch',
            'fallback' => 'en-us',
        ],
        'el' => [
            'name' => 'Greek',
            'native' => 'Ελληνικά',
            'fallback' => 'en-us',
        ],
        'he' => [
            'name' => 'Hebrew',
            'native' => 'עברית',
            'fallback' => 'en-us',
        ],
        'hu' => [
            'name' => 'Hungarian',
            'native' => 'Magyar',
            'fallback' => 'en-us',
        ],
        'it' => [
            'name' => 'Italian',
            'native' => 'Italiano',
            'fallback' => 'en-us',
        ],
        'ja' => [
            'name' => 'Japanese',
            'native' => '日本語',
            'fallback' => 'en-us',
        ],
        'ko' => [
            'name' => 'Korean',
            'native' => '한국어',
            'fallback' => 'en-us',
        ],
        'no' => [
            'name' => 'Norwegian',
            'native' => 'Norsk',
            'fallback' => 'en-us',
        ],
        'pl' => [
            'name' => 'Polish',
            'native' => 'Polski',
            'fallback' => 'en-us',
        ],
        'pt-pt' => [
            'name' => 'Portuguese',
            'native' => 'Português',
            'fallback' => 'en-us',
        ],
        'pt-br' => [
            'name' => 'Portuguese (Brazil)',
            'native' => 'Português (Brasil)',
            'fallback' => 'en-us',
        ],
        'ro' => [
            'name' => 'Romanian',
            'native' => 'Română',
            'fallback' => 'en-us',
        ],
        'ru' => [
            'name' => 'Russian',
            'native' => 'Русский',
            'fallback' => 'en-us',
        ],
        'sr' => [
            'name' => 'Serbian (Cyrillic)',
            'native' => 'Српски',
            'fallback' => 'en-us',
        ],
        'sv-se' => [
            'name' => 'Swedish',
            'native' => 'Svenska',
            'fallback' => 'en-us',
        ],
        'tr' => [
            'name' => 'Turkish',
            'native' => 'Türkçe',
            'fallback' => 'en-us',
        ],
        'uk' => [
            'name' => 'Ukrainian',
            'native' => 'Українська',
            'fallback' => 'en-us',
        ],
        'vi' => [
            'name' => 'Vietnamese',
            'native' => 'Tiếng Việt',
            'fallback' => 'en-us',
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
