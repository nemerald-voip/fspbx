<?php

/**
 * Validates every resources/lang/*.json translation file without requiring a
 * full Laravel bootstrap (config/locales.php is a plain array file, so it can
 * just be included), which keeps this usable both in CI and locally by
 * contributors before opening a PR.
 *
 * Hard failures (non-zero exit): invalid JSON, a key that doesn't exist in
 * the source locale (usually a typo or a stale key left behind after the
 * source string changed), or a *non-empty* translation whose :placeholder
 * tokens don't match the source string's -- that's a functional bug (a lost
 * or invented variable), not a translation quality issue. An empty string is
 * not checked for placeholders -- `lang:sync` seeds every locale file with
 * "" for keys nobody has translated yet (see LocaleFileLoader), so it's a
 * deliberate "not translated" marker, not a translation attempt.
 *
 * A locale being incomplete is not a failure: partial community
 * translations, especially thin dialect overlays, are expected. Completion
 * is only reported, as a courtesy summary for the PR.
 *
 * Pass --missing=<locale> (e.g. --missing=es-es) to also print that locale's
 * missing keys -- the "what's left to translate" list Crowdin used to show
 * in its web UI. This needs nothing beyond the `php` CLI itself (no
 * `composer install`), unlike the equivalent `php artisan lang:missing`.
 */

$missingLocale = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--missing=')) {
        $missingLocale = strtolower(substr($arg, strlen('--missing=')));
    }
}

$root = dirname(__DIR__, 2);
$langPath = $root . '/resources/lang';
$locales = include $root . '/config/locales.php';
$sourceLocale = $locales['default'] ?? 'en-us';

$sourceFile = "{$langPath}/{$sourceLocale}.json";
$source = decodeJsonFile($sourceFile);

if ($source === null) {
    fwrite(STDERR, "Source locale file missing or invalid: {$sourceFile}\n");
    exit(1);
}

$sourceKeys = array_keys($source);
$failures = [];
$summary = [];

foreach (glob("{$langPath}/*.json") as $file) {
    $locale = basename($file, '.json');

    // laravel-vue-i18n generates php_*.json bundles from Laravel's PHP
    // translation files during a Vite build. They are build artifacts with
    // dot-namespaced framework keys, not locale catalogs based on en-us.json.
    if ($locale === $sourceLocale || str_starts_with($locale, 'php_')) {
        continue;
    }

    $translations = decodeJsonFile($file);

    if ($translations === null) {
        $failures[] = "{$locale}: invalid JSON in " . basename($file);
        continue;
    }

    $orphans = array_diff(array_keys($translations), $sourceKeys);
    foreach ($orphans as $orphan) {
        $failures[] = "{$locale}: key not found in {$sourceLocale}.json (typo or stale key): " . jsonKey($orphan);
    }

    $translated = 0;
    foreach ($translations as $key => $value) {
        if (in_array($key, $orphans, true)) {
            continue;
        }

        if ($value === '') {
            continue;
        }

        $translated++;

        $sourceTokens = placeholders($key);
        $translatedTokens = placeholders($value);

        if ($sourceTokens !== $translatedTokens) {
            $failures[] = sprintf(
                '%s: placeholder mismatch for %s (expected [%s], got [%s])',
                $locale,
                jsonKey($key),
                implode(', ', $sourceTokens),
                implode(', ', $translatedTokens)
            );
        }
    }

    $summary[] = sprintf(
        '%-10s %3d%% (%d/%d translated)',
        $locale,
        $sourceKeys === [] ? 100 : (int) round(100 * $translated / count($sourceKeys)),
        $translated,
        count($sourceKeys)
    );
}

echo "Translation completion:\n";
foreach ($summary as $line) {
    echo "  {$line}\n";
}

if ($missingLocale !== null) {
    $missingFile = "{$langPath}/{$missingLocale}.json";
    $own = decodeJsonFile($missingFile) ?? [];
    $missing = [];
    foreach ($sourceKeys as $key) {
        // Absent entirely (file predates lang:sync's auto-propagation, or
        // this is a brand new locale) counts as missing same as present-
        // but-blank (the normal "not translated yet" state after sync).
        if (! array_key_exists($key, $own) || $own[$key] === '') {
            $missing[] = $key;
        }
    }
    sort($missing, SORT_STRING);

    echo "\n{$missingLocale} is missing " . count($missing) . ' of ' . count($sourceKeys) . " key(s):\n";
    foreach ($missing as $key) {
        echo '  - ' . jsonKey($key) . "\n";
    }
}

if ($failures !== []) {
    echo "\nFailures:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}

echo "\nAll translation files valid.\n";
exit(0);

function decodeJsonFile(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }

    $decoded = json_decode(file_get_contents($path), true);

    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
}

/**
 * @return array<int, string>
 */
function placeholders(string $text): array
{
    preg_match_all('/:[a-zA-Z0-9_]+/', $text, $matches);

    $tokens = array_unique($matches[0]);
    sort($tokens);

    return $tokens;
}

function jsonKey(string $key): string
{
    return json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
