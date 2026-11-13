<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use RuntimeException;
use Throwable;

class HaAppKey extends Command
{
    protected $signature = 'ha:app-key {operation : export, check, import, or verify}';

    protected $description = 'Validate and synchronize the application key during fresh HA standby setup';

    public function handle(): int
    {
        $stage = 'Unable to read the application configuration.';
        $temporary = null;
        $previousUmask = umask(0077);

        try {
            $operation = $this->argument('operation');
            if (! in_array($operation, ['export', 'check', 'import', 'verify'], true)) {
                return $this->fail('Unsupported operation. Use export, check, import, or verify.');
            }

            $envPath = $this->laravel->environmentFilePath();
            $root = dirname($envPath);
            if (basename($envPath) !== '.env' || ! is_file($envPath)
                || is_link($envPath) || ! is_readable($envPath)) {
                return $this->fail('A readable, regular .env file is required. Configure custom environment files manually.');
            }

            $contents = file_get_contents($envPath);
            $env = Dotenv::parse($contents);
            $pattern = '/^[\t ]*(?:export[\t ]+)?APP_KEY[\t ]*=[^\r\n]*/m';
            if (preg_match_all($pattern, $contents, $matches) !== 1) {
                return $this->fail('The .env file must contain exactly one APP_KEY assignment.');
            }
            // Replacing a multiline or interpolated key could damage other settings.
            $keyLine = Dotenv::parse($matches[0][0]);
            if (($keyLine['APP_KEY'] ?? null) !== ($env['APP_KEY'] ?? null)) {
                return $this->fail('APP_KEY must be a literal value on one line.');
            }

            $config = $this->laravel['config']->get('app');
            $cipher = $config['cipher'];
            $envKey = $this->decodeKey($env['APP_KEY'] ?? null, $cipher);
            $cachePath = $this->laravel->getCachedConfigPath();

            if ($operation === 'export') {
                $effectiveKey = $this->decodeKey($config['key'] ?? null, $cipher);
                if (! hash_equals($envKey, $effectiveKey)) {
                    return $this->fail('The primary .env key differs from its effective configuration. Resolve the cached or environment override before retrying.');
                }

                // The script captures this secret payload and sends it through SSH stdin.
                $this->output->write(json_encode([
                    'key' => 'base64:'.base64_encode($effectiveKey),
                    'cipher' => $cipher,
                ], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $stage = 'Unable to validate the primary application key.';
            $payload = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
            if (($payload['cipher'] ?? null) !== $cipher) {
                return $this->fail('The two nodes use different encryption ciphers.');
            }
            $primaryKey = $this->decodeKey($payload['key'] ?? null, $cipher);

            if ($operation === 'verify') {
                if (! hash_equals($primaryKey, $envKey)
                    || ! hash_equals($primaryKey, $this->decodeKey($config['key'] ?? null, $cipher))) {
                    return $this->fail('The standby is not using the primary application key. Check configuration and environment overrides.');
                }

                return self::SUCCESS;
            }

            if (! is_writable($root) || ! is_writable($envPath)
                || (file_exists($cachePath) && ! is_writable(dirname($cachePath)))) {
                return $this->fail('The standby .env file or configuration cache cannot be updated.');
            }
            if ($operation === 'check') {
                return self::SUCCESS;
            }

            $stage = 'Unable to back up or replace the standby .env file.';
            if (! hash_equals($envKey, $primaryKey)) {
                $metadata = stat($envPath);
                $backup = $envPath.'.ha-backup-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
                $handle = fopen($backup, 'x');
                if ($handle === false || fwrite($handle, $contents) !== strlen($contents)) {
                    return $this->fail('Could not save the standby .env backup; the key was not changed.');
                }
                fclose($handle);

                $updated = preg_replace_callback($pattern,
                    static fn () => 'APP_KEY=base64:'.base64_encode($primaryKey), $contents);
                $temporary = tempnam($root, '.env.ha-');
                if ($temporary === false || file_put_contents($temporary, $updated) !== strlen($updated)
                    || ! chown($temporary, $metadata['uid']) || ! chgrp($temporary, $metadata['gid'])
                    || ! chmod($temporary, $metadata['mode'] & 0777)
                    || file_get_contents($envPath) !== $contents || ! rename($temporary, $envPath)) {
                    return $this->fail('Could not replace .env; retain the .env.ha-backup file and inspect permissions before retrying.');
                }
                $temporary = null;
                $this->info("Standby .env backup saved to {$backup} (owner-only access).");
            }

            $stage = 'The key is saved, but the standby configuration cache could not be cleared. Clear it before continuing.';
            if ($this->callSilent('config:clear') !== self::SUCCESS) {
                return $this->fail($stage);
            }
            clearstatcache(true, $cachePath);
            if (file_exists($cachePath)) {
                return $this->fail($stage);
            }
            $this->info('Standby APP_KEY synchronized; cached configuration cleared.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Parser/crypto exceptions may contain credentials. Never print their messages.
            return $this->fail($stage);
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            umask($previousUmask);
        }
    }

    private function decodeKey(mixed $value, string $cipher): string
    {
        $key = is_string($value) && str_starts_with($value, 'base64:')
            ? base64_decode(substr($value, 7), true) : $value;

        if (! is_string($key) || ! Encrypter::supported($key, $cipher)) {
            throw new RuntimeException('Invalid application key.');
        }

        return $key;
    }

    private function fail(string $message): int
    {
        $this->output->getErrorStyle()->error($message);

        return self::FAILURE;
    }
}
