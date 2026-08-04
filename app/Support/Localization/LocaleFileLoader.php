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
 * Only the JSON ("*"/"*" group) path is affected; namespaced/PHP array
 * translations still resolve exactly as Laravel does by default.
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
    private LocaleRegistry $locales;

    public function __construct(Filesystem $files, array|string $path, LocaleRegistry $locales)
    {
        parent::__construct($files, $path);

        $this->locales = $locales;
    }

    protected function loadJsonPaths($locale)
    {
        $merged = [];

        foreach ($this->locales->chain($locale) as $chainLocale) {
            $translated = array_filter(
                parent::loadJsonPaths($chainLocale),
                fn ($value) => $value !== ''
            );

            $merged = array_merge($merged, $translated);
        }

        return $merged;
    }
}
