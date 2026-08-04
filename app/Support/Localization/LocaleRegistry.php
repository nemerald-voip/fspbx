<?php

namespace App\Support\Localization;

use Illuminate\Support\Facades\File;

/**
 * Reads config/locales.php to answer which locales this app knows how to
 * serve, how a dialect falls back to its parent, and how complete a
 * community translation is -- the single source of truth both the
 * SetApplicationLocale middleware and the LocaleFileLoader build on.
 */
class LocaleRegistry
{
    public function default(): string
    {
        return (string) config('locales.default', 'en-us');
    }

    /**
     * @return array<string, array{name?: string, native?: string, fallback?: string}>
     */
    public function locales(): array
    {
        return config('locales.locales', []);
    }

    public function isSupported(string $locale): bool
    {
        return isset($this->locales()[strtolower($locale)]);
    }

    /**
     * Resolve a requested locale code (e.g. a domain's "language" setting) to
     * one this app actually has a registry entry for, falling back to the
     * configured default when it's blank or unrecognized.
     */
    public function resolve(?string $requested): string
    {
        $requested = strtolower(trim((string) $requested));

        if ($requested !== '' && $this->isSupported($requested)) {
            return $requested;
        }

        return $this->default();
    }

    /**
     * Ordered list of locale codes to merge translations from, base first, so
     * a dialect only needs to define the strings that differ from its parent
     * (e.g. es-mx overrides only a handful of words from es before falling
     * back to es, then en-us).
     *
     * @return array<int, string>
     */
    public function chain(string $locale): array
    {
        $locale = strtolower($locale);
        $locales = $this->locales();
        $chain = [];
        $seen = [];

        while ($locale !== '' && ! isset($seen[$locale])) {
            array_unshift($chain, $locale);
            $seen[$locale] = true;
            $locale = strtolower((string) ($locales[$locale]['fallback'] ?? ''));
        }

        if (($chain[0] ?? null) !== $this->default()) {
            array_unshift($chain, $this->default());
        }

        return $chain;
    }

    /**
     * Locales worth offering in a picker, each annotated with completion
     * against the default locale's own keys so partial community
     * translations aren't exposed to tenants until they clear
     * config('locales.minimum_completion').
     *
     * @return array<int, array{code: string, name: string, native: string, completion: float, ready: bool}>
     */
    public function available(): array
    {
        $source = $this->ownKeys($this->default());
        $minimum = (float) config('locales.minimum_completion', 0.6);

        $rows = [];

        foreach ($this->locales() as $code => $meta) {
            $completion = $this->completion($code, $source);

            $rows[] = [
                'code' => $code,
                'name' => $meta['name'] ?? $code,
                'native' => $meta['native'] ?? $meta['name'] ?? $code,
                'completion' => $completion,
                'ready' => $code === $this->default() || $completion >= $minimum,
            ];
        }

        return $rows;
    }

    /**
     * Every registered locale as a {value, label} row for admin dropdowns
     * (e.g. the email-template language picker), so a template's language
     * code is drawn from the same vocabulary as the domain "language"
     * setting that selects it at send time. Unlike available(), this is not
     * gated by UI-translation completeness -- an email template can be
     * authored in a language before the UI itself is translated. Order
     * follows config/locales.php (default and its dialects first).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        $rows = [];

        foreach ($this->locales() as $code => $meta) {
            $rows[] = [
                'value' => $code,
                'label' => sprintf('%s (%s)', $meta['name'] ?? $code, $code),
            ];
        }

        return $rows;
    }

    /**
     * Keys actually translated directly in a locale's own file (ignoring
     * inherited fallback keys) -- i.e. present with a non-empty value. Used
     * only to measure translation completion.
     *
     * `lang:sync` seeds every registered locale file with every source key,
     * using "" as a "not translated yet" placeholder (see
     * LocaleFileLoader), so a bare `array_keys()` here would count every
     * locale as 100% complete regardless of how much is actually
     * translated -- excluding blanks is what keeps `available()`'s
     * completion gate meaningful.
     *
     * @return array<int, string>
     */
    public function ownKeys(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_keys(array_filter($decoded, fn ($value) => $value !== ''));
    }

    /**
     * @param array<int, string> $sourceKeys
     */
    private function completion(string $locale, array $sourceKeys): float
    {
        if ($sourceKeys === []) {
            return 1.0;
        }

        $own = $this->ownKeys($locale);

        return round(count(array_intersect($own, $sourceKeys)) / count($sourceKeys), 2);
    }
}
