<?php

namespace App\Support\Localization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

/**
 * Merges a locale's JSON translations over its LocaleRegistry::chain(). No
 * locale currently has a `fallback` other than the default in
 * config/locales.php (dialect-to-dialect inheritance, e.g. es-mx quietly
 * borrowing from es-es, was tried and dropped as too confusing for
 * translators -- see config/locales.php), so chain() only ever returns
 * `[en-us, locale]` today, but the merge itself stays chain-shaped: if a
 * `fallback` is ever added back to a locale, this needs no code change.
 * Laravel's auth/passwords/validation groups use their English PHP definitions
 * as a key-to-source-string map, then translate those strings through the same
 * JSON catalog. Translators do not need separate PHP files. Other groups and
 * package namespaces still resolve exactly as Laravel does by default.
 *
 * An empty-string value is treated as "not translated yet" rather than a
 * real translation -- `lang:sync` seeds every locale file with `""` for
 * keys nobody has translated, so a community translator can open the file
 * and see every available key with blanks to fill in. Filtering those out
 * before merging means a blank in a locale's own file correctly falls back
 * to en-us instead of rendering as empty text.
 */
class LocaleFileLoader extends FileLoader
{
    private const JSON_GROUPS = ['auth', 'passwords', 'validation'];

    private LocaleRegistry $locales;

    private array $jsonCatalogs = [];

    public function __construct(Filesystem $files, array|string $path, LocaleRegistry $locales)
    {
        parent::__construct($files, $path);

        $this->locales = $locales;
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        if (($namespace !== null && $namespace !== '*') || ! in_array($group, self::JSON_GROUPS, true)) {
            return $lines;
        }

        // Existing explicit locale PHP files retain Laravel's native behavior.
        // The default-locale files are internal source definitions, not overrides.
        if ($locale !== $this->locales->default() && $lines !== []) {
            return $lines;
        }

        $source = $locale === $this->locales->default()
            ? $lines
            : parent::load($this->locales->default(), $group);
        $catalog = $this->loadJsonPaths($locale);
        array_walk_recursive($source, function (&$line) use ($catalog) {
            if (is_string($line)) {
                $line = $catalog[$line] ?? $line;
            }
        });

        return $source;
    }

    /** Source strings for lang:sync, including nested rules and field labels. */
    public function jsonGroupSourceStrings(): array
    {
        $strings = [];
        foreach (self::JSON_GROUPS as $group) {
            $lines = parent::load($this->locales->default(), $group);
            array_walk_recursive($lines, function ($line) use (&$strings) {
                if (is_string($line) && $line !== '') {
                    $strings[$line] = true;
                }
            });
        }

        return array_keys($strings);
    }

    protected function loadJsonPaths($locale)
    {
        if (array_key_exists($locale, $this->jsonCatalogs)) {
            return $this->jsonCatalogs[$locale];
        }

        $merged = [];

        foreach ($this->locales->chain($locale) as $chainLocale) {
            $translated = array_filter(
                parent::loadJsonPaths($chainLocale),
                fn ($value) => $value !== ''
            );

            $merged = array_merge($merged, $translated);
        }

        return $this->jsonCatalogs[$locale] = $merged;
    }
}
