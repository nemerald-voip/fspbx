<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Keeps resources/lang/{default}.json (the source-string manifest every other
 * locale, including Crowdin, is translated against) in sync with the literal
 * strings actually passed to __()/trans()/@lang() in PHP and Blade and to
 * $t()/$tChoice()/trans() in Vue/JS. Since the source locale's "translation"
 * of a key is just the key itself, running this after adding new UI copy is
 * enough to make it translatable -- no key naming or namespacing required.
 */
class LangSyncCommand extends Command
{
    protected $signature = 'lang:sync
        {--prune : Remove keys from the source file that no longer appear in scanned source}';

    protected $description = 'Extract translatable strings into resources/lang/{default}.json';

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

        return self::SUCCESS;
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
