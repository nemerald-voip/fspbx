<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Keeps resources/lang/{default}.json (the source-string manifest every other
 * locale is translated against) in sync with the literal strings actually
 * passed to __()/trans()/@lang() in PHP and Blade and to $t()/$tChoice()/
 * trans() in Vue/JS. Since the source locale's "translation" of a key is just
 * the key itself, running this after adding new UI copy is enough to make it
 * translatable -- no key naming or namespacing required.
 *
 * Also propagates the resulting key set to every other registered locale
 * file (see syncLocales()) so translators never have to notice or chase a
 * key manually -- this is the automation that replaced Crowdin's own
 * source-upload step.
 */
class LangSyncCommand extends Command
{
    protected $signature = 'lang:sync
        {--prune : Also remove keys from the source file itself that no longer appear in scanned source (orphan keys are always removed from every other locale file, with or without this flag)}';

    protected $description = 'Extract translatable strings into resources/lang/{default}.json and propagate the key set to every other locale file';

    private const PHP_DIRECTORIES = ['app', 'resources/views', 'routes'];

    private const JS_DIRECTORIES = ['resources/js'];

    private const EXCLUDED_PATHS = [
        'resources/views/emails', // owned by the email template system, not this catalog
    ];

    public function handle(): int
    {
        $locale = (string) config('locales.default', 'en-us');
        $path = lang_path("{$locale}.json");

        $existing = File::exists($path)
            ? (json_decode(File::get($path), true) ?: [])
            : [];

        $found = array_unique(array_merge(
            $this->extract(self::PHP_DIRECTORIES, ['php'], $this->phpPatterns()),
            $this->extract(self::JS_DIRECTORIES, ['js', 'vue'], $this->jsPatterns()),
        ));

        $added = array_values(array_diff($found, array_keys($existing)));
        $missing = array_values(array_diff(array_keys($existing), $found));

        $merged = $existing;
        foreach ($added as $key) {
            $merged[$key] = $key;
        }

        if ($this->option('prune')) {
            foreach ($missing as $key) {
                unset($merged[$key]);
            }
        }

        ksort($merged, SORT_STRING);

        File::put(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $this->info(sprintf('%s: %d keys total, %d added.', $path, count($merged), count($added)));

        if ($missing !== []) {
            $verb = $this->option('prune') ? 'Removed' : 'Not found in this scan (kept; rerun with --prune to remove)';
            $this->line("{$verb}: " . count($missing) . ' key(s).');
            foreach ($missing as $key) {
                $this->line("  - {$key}");
            }
        }

        $this->syncLocales($locale, array_keys($merged));

        return self::SUCCESS;
    }

    /**
     * Propagates the source manifest's current key set to every other
     * registered locale file, and clears out translations that can no
     * longer be trusted, so a translator only ever has to fill in a blank
     * -- never chase a key or clean up broken data by hand:
     *
     * - A newly added source key appears everywhere with an empty-string
     *   placeholder ("" means "not translated yet" -- see LocaleFileLoader,
     *   which treats it exactly like an absent key at runtime, so this is
     *   always safe to add even to a dialect that mostly inherits from a
     *   parent).
     * - A key that isn't in the source manifest at all -- whether it was
     *   removed from source, or (far more common in practice) never valid
     *   to begin with, e.g. a typo introduced by hand-editing a locale file
     *   -- is removed from every OTHER locale file unconditionally. This is
     *   always safe: an orphan key can never be looked up by the running
     *   app (nothing queries a key that isn't a literal source string), and
     *   validate-translations.php already hard-fails a PR over it, so
     *   leaving it in place has no upside. This is deliberately NOT gated
     *   behind --prune, unlike removing a key from the source file itself
     *   (still --prune-gated below), which is the bigger, more reversible
     *   call: a string temporarily unused mid-refactor is still valid,
     *   deleting it from the source manifest means retranslating from
     *   scratch if it comes back.
     * - An existing non-empty translation whose :placeholder tokens don't
     *   match the source string's is reset back to "" rather than left in
     *   place -- it's not translated correctly, so treating it as "not
     *   translated yet" (which re-surfaces it for a translator, and passes
     *   CI) is more useful than a silent, permanently-failing mismatch.
     *
     * @param array<int, string> $sourceKeys
     */
    private function syncLocales(string $defaultLocale, array $sourceKeys): void
    {
        $sourceKeySet = array_flip($sourceKeys);

        foreach (array_keys(config('locales.locales', [])) as $otherLocale) {
            if ($otherLocale === $defaultLocale) {
                continue;
            }

            $path = lang_path("{$otherLocale}.json");
            $own = File::exists($path)
                ? (json_decode(File::get($path), true) ?: [])
                : [];

            $added = 0;
            foreach ($sourceKeys as $key) {
                if (! array_key_exists($key, $own)) {
                    $own[$key] = '';
                    $added++;
                }
            }

            $removed = 0;
            foreach (array_keys($own) as $key) {
                if (! isset($sourceKeySet[$key])) {
                    unset($own[$key]);
                    $removed++;
                }
            }

            $reset = 0;
            foreach ($own as $key => $value) {
                if ($value === '' || ! isset($sourceKeySet[$key])) {
                    continue;
                }

                if ($this->placeholders($key) !== $this->placeholders($value)) {
                    $own[$key] = '';
                    $reset++;
                }
            }

            if ($added === 0 && $removed === 0 && $reset === 0) {
                continue;
            }

            ksort($own, SORT_STRING);

            File::put(
                $path,
                json_encode($own, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
            );

            $this->line(sprintf(
                '%s: %d key(s) added, %d removed, %d reset to blank (broken :placeholder).',
                $path,
                $added,
                $removed,
                $reset
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-zA-Z0-9_]+/', $text, $matches);

        $tokens = array_unique($matches[0]);
        sort($tokens);

        return $tokens;
    }

    /**
     * @param array<int, string> $directories
     * @param array<int, string> $extensions
     * @param array<int, string> $patterns
     * @return array<int, string>
     */
    private function extract(array $directories, array $extensions, array $patterns): array
    {
        $directories = array_filter(
            array_map(fn (string $dir) => base_path($dir), $directories),
            fn (string $dir) => File::isDirectory($dir)
        );

        if ($directories === []) {
            return [];
        }

        $finder = (new Finder())->files()->in($directories);

        foreach ($extensions as $extension) {
            $finder->name("*.{$extension}");
        }

        foreach (self::EXCLUDED_PATHS as $excluded) {
            $finder->notPath(str_replace(base_path() . '/', '', base_path($excluded)));
        }

        $keys = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[2] as $raw) {
                        $key = $this->unescape($raw);

                        if (! $this->looksLikeNamespacedKey($key)) {
                            $keys[] = $key;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function phpPatterns(): array
    {
        return [
            '/\b(?:__|trans|trans_choice)\(\s*(\'|")((?:\\\\.|(?!\1).)*)\1/s',
            '/@lang\(\s*(\'|")((?:\\\\.|(?!\1).)*)\1/s',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function jsPatterns(): array
    {
        // (?<![\w$]) rather than \b: "$" isn't a word character, so \b never
        // matches between a preceding space and a leading "$" and would
        // silently skip every $t(...)/$tChoice(...) call.
        return [
            '/(?<![\w$])(?:\$t|\$tChoice|trans)\(\s*(\'|")((?:\\\\.|(?!\1).)*)\1/s',
        ];
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $value);
    }

    /**
     * __('pagination.next') and friends address a resources/lang/{locale}/*.php
     * group.item pair, not a literal source string -- Laravel resolves those
     * through the ordinary PHP-array loader, so they must not be captured
     * into the flat JSON catalog (a literal "pagination.next" key there would
     * shadow the real translation for the active locale). Genuine UI copy
     * almost never looks like a bare, spaceless, dotted lowercase path, so
     * that shape is a reliable signal to exclude.
     */
    private function looksLikeNamespacedKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $key);
    }
}
