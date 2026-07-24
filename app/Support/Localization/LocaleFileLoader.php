<?php

namespace App\Support\Localization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

/**
 * Merges a locale's JSON translations over its LocaleRegistry::chain() so a
 * regional dialect (e.g. es-mx) only has to define the strings that differ
 * from its parent (es), inheriting everything else from es and finally
 * en-us. Only the JSON ("*"/"*" group) path is affected; namespaced/PHP
 * array translations still resolve exactly as Laravel does by default.
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
            $merged = array_merge($merged, parent::loadJsonPaths($chainLocale));
        }

        return $merged;
    }
}
