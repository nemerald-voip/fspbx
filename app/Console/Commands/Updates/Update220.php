<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class Update220
{
    private const BUCKETS = ['logs', 'phoneconfigs', 'calls', 'directories', 'corefiles'];

    public function apply(): bool
    {
        if (! $this->hardenNginx()) {
            echo "WARNING: Legacy Polycom uploads may still be exposed. Continuing the application update.\n";
            echo "Resolve the warning, then retry: php artisan tinker --execute='(new \\App\\Console\\Commands\\Updates\\Update220)->hardenNginx();'\n";
        }

        // UpdateApp rebuilds the route cache after the update hooks. The old
        // Laravel routes/controller are removed from the shipped application.
        return true;
    }

    public function hardenNginx(): bool
    {
        $written = [];
        $changes = [];
        $reloadAttempted = false;
        try {
            // Discover loaded includes instead of assuming a site filename or
            // identical HTTP/HTTPS blocks on every installation. Do not print
            // the dump: custom configuration can contain credentials.
            $dump = $this->run(['nginx', '-T']);
            preg_match_all('/^# configuration file (.+):\r?$/m', $dump, $matches);
            $paths = [];
            foreach ($matches[1] as $path) {
                $real = realpath($path);
                if ($real === false || ! is_file($real)) {
                    throw new RuntimeException('Cannot read loaded Nginx file '.$path.'.');
                }
                $paths[$real] = $real;
            }
            if ($paths === []) {
                throw new RuntimeException('Nginx did not report any loaded configuration files.');
            }

            $complete = true;
            $locations = 0;
            foreach ($paths as $path) {
                $original = file_get_contents($path);
                if ($original === false) {
                    throw new RuntimeException('Cannot read '.$path.'.');
                }
                $patched = $this->hardenNginxConfig($original);
                foreach ($patched['warnings'] as $warning) {
                    echo "WARNING: {$path}: {$warning}\n";
                    $complete = false;
                }
                if ($patched['changed'] > 0) {
                    $changes[$path] = ['original' => $original, 'updated' => $patched['config']];
                    $locations += $patched['changed'];
                }
            }
            if ($changes === []) {
                echo $complete ? "No Nginx legacy Polycom locations need changing.\n" : "Custom legacy Polycom locations require manual review.\n";
                return $complete;
            }

            // Keep backups out of sites-enabled/conf.d wildcard includes.
            $backup = $this->backupRoot().'/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
            if (! mkdir($backup, 0700, true) && ! is_dir($backup)) {
                throw new RuntimeException('Cannot create Nginx backup directory '.$backup.'.');
            }
            foreach ($changes as $path => $change) {
                $backupPath = $backup.'/'.hash('sha256', $path).'.conf';
                if (file_put_contents($backupPath, $change['original'], LOCK_EX) !== strlen($change['original']) || ! chmod($backupPath, 0600)) {
                    throw new RuntimeException('Cannot back up '.$path.'.');
                }
                echo "Nginx backup: {$path} -> {$backupPath}\n";
            }
            foreach ($changes as $path => $change) {
                if (file_get_contents($path) !== $change['original']) {
                    throw new RuntimeException('Nginx configuration changed during the update: '.$path.'.');
                }
                $this->writeFile($path, $change['updated']);
                $written[] = $path;
            }
            $this->run(['nginx', '-t']);
            $reloadAttempted = true;
            $this->run(['systemctl', 'reload', 'nginx']);
            echo "Disabled uploads in {$locations} legacy Polycom location(s) in ".count($changes)." Nginx file(s); existing read access preserved, configuration validated and reload requested.\n";
            return $complete;
        } catch (Throwable $error) {
            $restored = true;
            foreach (array_reverse($written) as $path) {
                try {
                    if (file_get_contents($path) !== $changes[$path]['updated']) {
                        throw new RuntimeException('The file changed after this update wrote it; preserving the newer contents');
                    }
                    $this->writeFile($path, $changes[$path]['original']);
                    echo "Restored Nginx configuration: {$path}\n";
                } catch (Throwable $restoreError) {
                    $restored = false;
                    echo "WARNING: Restore failed for {$path}: {$restoreError->getMessage()}. Use the backup listed above.\n";
                }
            }
            if ($reloadAttempted && $restored) {
                try {
                    $this->run(['nginx', '-t']);
                    $this->run(['systemctl', 'reload', 'nginx']);
                } catch (Throwable $reloadError) {
                    echo "WARNING: Could not reload the restored configuration: {$reloadError->getMessage()}. Check Nginx manually.\n";
                }
            }
            echo "WARNING: Could not complete legacy Polycom Nginx hardening: {$error->getMessage()}\n";
            return false;
        }
    }

    protected function backupRoot(): string
    {
        return '/var/backups/fspbx/polycom-upload-hardening';
    }

    protected function run(array $command): string
    {
        $result = Process::timeout(60)->run($command);
        if (! $result->successful()) {
            // nginx -T stdout contains the full configuration; never echo it.
            throw new RuntimeException(implode(' ', $command).' failed: '.trim($result->errorOutput()));
        }
        return $result->output();
    }

    protected function writeFile(string $path, string $contents): void
    {
        $stat = stat($path);
        if ($stat === false) {
            throw new RuntimeException('Cannot read Nginx file metadata '.$path.'.');
        }
        $temporary = tempnam(dirname($path), '.fspbx-polycom-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot prepare Nginx configuration '.$path.'.');
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)
                || ! chown($temporary, $stat['uid']) || ! chgrp($temporary, $stat['gid'])
                || ! chmod($temporary, $stat['mode'] & 07777) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace Nginx configuration '.$path.'.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** Disable DAV writes in existing locations while preserving their read configuration. */
    protected function hardenNginxConfig(string $config): array
    {
        $tokens = $this->tokens($config);
        $stack = [];
        $closing = [];
        foreach ($tokens as $index => $token) {
            if (! $token['symbol']) {
                continue;
            }
            if ($token['value'] === '{') {
                $stack[] = $index;
            } elseif ($token['value'] === '}') {
                $opening = array_pop($stack);
                if ($opening === null) {
                    throw new RuntimeException('Unbalanced Nginx configuration.');
                }
                $closing[$opening] = $index;
            }
        }
        if ($stack !== []) {
            throw new RuntimeException('Unbalanced Nginx configuration.');
        }

        $edits = [];
        $changedLocations = 0;
        $warnings = [];
        $start = 0;
        $newline = str_contains($config, "\r\n") ? "\r\n" : "\n";
        for ($index = 0; $index < count($tokens); $index++) {
            if (! $tokens[$index]['symbol']) {
                continue;
            }
            $value = $tokens[$index]['value'];
            if ($value === '{' && ($tokens[$start]['value'] ?? '') === 'location') {
                $arguments = array_slice($tokens, $start + 1, $index - $start - 1);
                $modifier = count($arguments) === 2 ? $arguments[0]['value'] : '';
                $pathToken = end($arguments);
                $path = $pathToken['value'] ?? '';
                $target = in_array($modifier, ['', '^~', '='], true)
                    ? $this->isLegacyPath($path)
                    : (in_array($modifier, ['~', '~*'], true) && $this->isLegacyRegex($path));

                if ($target) {
                    $before = count($edits);
                    $directive = $index + 1;
                    $depth = 0;
                    $localDav = false;
                    $localReturn = false;
                    $localInclude = false;
                    for ($cursor = $index + 1; $cursor < $closing[$index]; $cursor++) {
                        if (! $tokens[$cursor]['symbol']) {
                            continue;
                        }
                        $symbol = $tokens[$cursor]['value'];
                        if ($symbol === ';') {
                            $name = $tokens[$directive]['value'] ?? '';
                            if ($depth === 0) {
                                $localDav = $localDav || $name === 'dav_methods';
                                $localReturn = $localReturn || $name === 'return';
                                $localInclude = $localInclude || $name === 'include';
                            }
                            if ($name === 'dav_methods' && ($cursor !== $directive + 2 || $tokens[$directive + 1]['value'] !== 'off')) {
                                $offset = $tokens[$directive]['start'];
                                $edits[] = [$offset, $tokens[$cursor]['end'] - $offset, 'dav_methods off;'];
                            }
                        } elseif ($symbol === '{') {
                            $depth++;
                        } elseif ($symbol === '}') {
                            $depth--;
                        }
                        $directive = $cursor + 1;
                    }
                    if (! $localDav && ! $localReturn) {
                        if ($localInclude) {
                            // The include may already set dav_methods. Do not
                            // add a duplicate directive or alter a shared file.
                            $warnings[] = 'Legacy location '.$path.' uses included configuration; review its DAV methods manually.';
                        } else {
                            // Explicitly override any inherited DAV methods.
                            $offset = $tokens[$start]['start'];
                            $lineStart = strrpos(substr($config, 0, $offset), "\n");
                            $indent = substr($config, $lineStart === false ? 0 : $lineStart + 1, $offset - ($lineStart === false ? 0 : $lineStart + 1));
                            $indent = preg_match('/^[ \t]*$/', $indent) ? $indent : '';
                            $edits[] = [$tokens[$index]['end'], 0, $newline.$indent.'    dav_methods off;'];
                        }
                    }
                    if (count($edits) > $before) {
                        $changedLocations++;
                    }
                    $index = $closing[$index];
                    $start = $index + 1;
                    continue;
                }

                if (str_contains($path, '/polycom/log')
                    || (str_contains($path, '/app/provision/') && preg_match('/\b(logs|phoneconfigs|calls|directories|corefiles)\b/', $path))) {
                    $warnings[] = 'Unrecognized legacy location '.$path.'; review it manually.';
                }
            }
            if (in_array($value, ['{', '}', ';'], true)) {
                $start = $index + 1;
            }
        }

        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($edits as [$offset, $length, $replacement]) {
            $config = substr_replace($config, $replacement, $offset, $length);
        }

        return ['config' => $config, 'changed' => $changedLocations, 'warnings' => $warnings];
    }

    private function isLegacyPath(string $path): bool
    {
        foreach (array_merge(['/polycom/log'], array_map(fn ($bucket) => '/app/provision/'.$bucket, self::BUCKETS)) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }
        return false;
    }

    private function isLegacyRegex(string $pattern): bool
    {
        // Recognize narrowly scoped regex locations, including grouped buckets.
        // Never rewrite a broad/custom expression that might serve other URLs.
        $pattern = str_starts_with($pattern, '^') ? substr($pattern, 1) : $pattern;
        if (preg_match('#^/app/provision/\((?:\?:)?([a-z|]+)\)(.*)$#s', $pattern, $matches)) {
            if (array_diff(explode('|', $matches[1]), self::BUCKETS) !== []) {
                return false;
            }
            $suffix = $matches[2];
        } else {
            $suffix = null;
            foreach (array_merge(['/polycom/log'], array_map(fn ($bucket) => '/app/provision/'.$bucket, self::BUCKETS)) as $prefix) {
                if (str_starts_with($pattern, $prefix)) {
                    $suffix = substr($pattern, strlen($prefix));
                    break;
                }
            }
        }

        return in_array($suffix, ['', '/', '$', '/$', '/?$', '/.*', '/.*$', '(/|$)', '(?:/|$)', '(/.*)?$', '(?:/.*)?$'], true);
    }

    /** Token offsets preserve unrelated text, comments, quotes and whitespace. */
    private function tokens(string $config): array
    {
        $tokens = [];
        $length = strlen($config);
        for ($index = 0; $index < $length;) {
            $char = $config[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }
            if ($char === '#') {
                $index = strpos($config, "\n", $index) ?: $length;
                continue;
            }
            $start = $index;
            if (str_contains('{};', $char)) {
                $tokens[] = ['value' => $char, 'start' => $start, 'end' => ++$index, 'symbol' => true];
                continue;
            }

            $value = '';
            $quote = null;
            for (; $index < $length; $index++) {
                $char = $config[$index];
                if ($char === '\\' && $index + 1 < $length) {
                    $value .= $char.$config[++$index];
                } elseif ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    } else {
                        $value .= $char;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '$' && substr($config, $index, 2) === '${') {
                    $end = strpos($config, '}', $index + 2);
                    if ($end === false) {
                        throw new RuntimeException('Unclosed Nginx variable.');
                    }
                    $value .= substr($config, $index, $end - $index + 1);
                    $index = $end;
                } elseif (ctype_space($char) || str_contains('{};#', $char)) {
                    break;
                } else {
                    $value .= $char;
                }
            }
            if ($quote !== null) {
                throw new RuntimeException('Unclosed Nginx quoted string.');
            }
            $tokens[] = ['value' => $value, 'start' => $start, 'end' => $index, 'symbol' => false];
        }
        return $tokens;
    }
}
