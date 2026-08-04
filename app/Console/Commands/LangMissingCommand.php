<?php

namespace App\Console\Commands;

use App\Support\Localization\LocaleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Reports (or scaffolds) the keys a locale file is missing relative to the
 * source manifest -- the gap a community translator needs to fill in.
 *
 * Crowdin used to surface this as an "untranslated strings" queue in its web
 * UI. Now that translation happens via plain PRs against
 * resources/lang/{locale}.json (see docs/translations.md), there's no UI
 * that shows a translator what's left to do, so this fills that role.
 */
class LangMissingCommand extends Command
{
    protected $signature = 'lang:missing
        {locale : Locale code to check, e.g. es-es}
        {--stub : Append the missing keys to the locale file, using the English source text as a placeholder value to translate}';

    protected $description = "List (or scaffold) the keys a locale file is missing relative to the source manifest";

    public function handle(LocaleRegistry $registry): int
    {
        $locale = strtolower((string) $this->argument('locale'));
        $default = $registry->default();

        if ($locale === $default) {
            $this->error("{$locale} is the source locale itself -- run lang:sync instead.");

            return self::FAILURE;
        }

        $source = $this->loadJson(lang_path("{$default}.json"));

        if ($source === []) {
            $this->error("Source manifest missing or empty: " . lang_path("{$default}.json"));

            return self::FAILURE;
        }

        $path = lang_path("{$locale}.json");
        $own = $this->loadJson($path);

        // Absent (a file predating lang:sync's auto-propagation) and
        // present-but-blank (the normal "not translated yet" state lang:sync
        // seeds every locale file with -- see LocaleFileLoader) both count
        // as missing.
        $missing = [];
        foreach (array_keys($source) as $key) {
            if (! array_key_exists($key, $own) || $own[$key] === '') {
                $missing[] = $key;
            }
        }
        sort($missing, SORT_STRING);

        if ($missing === []) {
            $this->info("{$locale}.json already has every key from {$default}.json.");

            return self::SUCCESS;
        }

        if (! $this->option('stub')) {
            $this->line(sprintf(
                '%s is missing %d of %d key(s) from %s.json:',
                $locale,
                count($missing),
                count($source),
                $default
            ));

            foreach ($missing as $key) {
                $this->line("  - {$key}");
            }

            $this->line('');
            $this->line('Rerun with --stub to append these to the file with English placeholder text to translate.');

            return self::SUCCESS;
        }

        foreach ($missing as $key) {
            $own[$key] = $source[$key];
        }

        ksort($own, SORT_STRING);

        File::put(
            $path,
            json_encode($own, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $this->info(sprintf(
            '%s: stubbed %d key(s) with English placeholder text.',
            $path,
            count($missing)
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function loadJson(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
