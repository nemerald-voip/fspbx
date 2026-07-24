<?php

/**
 * Validates every resources/lang/*.json translation file without requiring a
 * full Laravel bootstrap (config/locales.php is a plain array file, so it can
 * just be included), which keeps this usable both in CI and locally by
 * contributors before opening a PR.
 *
 * Hard failures (non-zero exit): invalid JSON, a key that doesn't exist in
 * the source locale (usually a typo or a stale key left behind after the
 * source string changed), or a translation whose :placeholder tokens don't
 * match the source string's -- that's a functional bug (a lost or invented
 * variable), not a translation quality issue.
 *
 * A locale being incomplete is not a failure: partial community
 * translations, especially thin dialect overlays, are expected. Completion
 * is only reported, as a courtesy summary for the PR.
 */

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

    if ($locale === $sourceLocale) {
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

    foreach ($translations as $key => $value) {
        if (in_array($key, $orphans, true)) {
            continue;
        }

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

    $own = count($translations);
    $covered = count(array_intersect(array_keys($translations), $sourceKeys));
    $summary[] = sprintf(
        '%-10s %3d%% (%d/%d source keys, %d own key%s)',
        $locale,
        $sourceKeys === [] ? 100 : (int) round(100 * $covered / count($sourceKeys)),
        $covered,
        count($sourceKeys),
        $own,
        $own === 1 ? '' : 's'
    );
}

echo "Translation completion:\n";
foreach ($summary as $line) {
    echo "  {$line}\n";
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
