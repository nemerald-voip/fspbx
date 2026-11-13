<?php

namespace App\Console\Commands\Updates;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class Update225
{
    public const RETRY_COMMAND = "cd /var/www/fspbx && sudo php artisan tinker --execute='(new \\App\\Console\\Commands\\Updates\\Update225)->apply();'";

    public function __construct(
        protected string $dehydratedDirectory = '/etc/dehydrated',
        protected string $applicationDirectory = '/var/www/fspbx',
    ) {}

    public function apply(): bool
    {
        try {
            $result = $this->migrate();
            echo $result['message']."\n";
        } catch (Throwable $exception) {
            echo 'WARNING: Nginx certificate configuration was not repaired: '.$exception->getMessage()."\n";
            echo 'Continuing the application update. After correcting the error, retry: '.self::RETRY_COMMAND."\n";
        }

        return true;
    }

    /** @return array{status: string, message: string} */
    public function migrate(): array
    {
        if (! $this->isRoot()) {
            throw new RuntimeException('Certificate script migration must run as root.');
        }

        $deploymentPath = $this->dehydratedDirectory.'/fspbx-deployment.conf';
        if (! file_exists($deploymentPath)) {
            return $this->skipped('No current FS PBX certificate deployment is configured.');
        }

        $deployment = $this->readFile($deploymentPath);
        if (! preg_match("/^MODE='(single|redundant|disabled)'\r?$/m", $deployment, $mode)) {
            return $this->skipped('The certificate deployment mode is not recognized.');
        }
        if ($mode[1] === 'disabled') {
            return $this->skipped('Certificate renewal is disabled on this former peer.');
        }

        $configPath = $this->dehydratedDirectory.'/config';
        if (is_link($configPath)) {
            return $this->skipped('The Dehydrated configuration is a custom symbolic link.');
        }
        $contents = $this->readFile($configPath);

        // Only migrate the single literal assignment written by our installer.
        // Keep comments, quoting, line endings, and all other settings intact.
        $assignments = preg_match_all('/^[ \t]*(?:export[ \t]+)?HOOK[ \t]*=/m', $contents);
        $pattern = '/^([ \t]*(?:export[ \t]+)?HOOK[ \t]*=[ \t]*)([\'"]?)([^\'"\s#;]+)\2([ \t]*(?:#[^\r\n]*)?)(\r?)$/m';
        if ($assignments !== 1 || ! preg_match($pattern, $contents, $hook, PREG_OFFSET_CAPTURE)) {
            return $this->skipped('The Dehydrated hook configuration is custom or ambiguous.');
        }

        $hookPath = $this->applicationDirectory.'/install/dehydrated_fspbx_hook.sh';
        if (! in_array($hook[3][0], ['/etc/dehydrated/fspbx-hook.sh', $hookPath], true)) {
            return $this->skipped('Dehydrated uses a custom hook.');
        }

        $this->validateScripts();
        if ($hook[3][0] === $hookPath) {
            return ['status' => 'current', 'message' => 'Nginx certificate scripts already run from the FS PBX repository.'];
        }

        $updated = substr_replace($contents, $hookPath, $hook[3][1], strlen($hook[3][0]));
        $backup = $this->replaceConfiguration($configPath, $contents, $updated);

        return [
            'status' => 'updated',
            'message' => "Nginx certificate scripts now run from the FS PBX repository. Configuration backup: {$backup}. The next scheduled renewal will use them.",
        ];
    }

    protected function validateScripts(): void
    {
        foreach (['dehydrated_fspbx_hook.sh', 'deploy_nginx_certificate.sh'] as $filename) {
            $path = $this->applicationDirectory.'/install/'.$filename;
            if (! is_file($path) || ! is_readable($path) || ! is_executable($path)) {
                throw new RuntimeException("Certificate script is missing, unreadable, or not executable: {$path}. Update the FS PBX checkout first.");
            }

            $process = new Process(['/bin/bash', '-n', $path], null, null, null, 10);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException("Certificate script failed its syntax check: {$path}. ".trim($process->getErrorOutput()));
            }
        }
    }

    protected function replaceConfiguration(string $path, string $original, string $updated): string
    {
        $metadata = @stat($path);
        if ($metadata === false) {
            throw new RuntimeException("Unable to inspect {$path}.");
        }

        $suffix = date('YmdHis').'-'.bin2hex(random_bytes(6));
        $backup = $path.'.fspbx-before-2.2.5-'.$suffix.'.bak';
        $temporary = $path.'.fspbx-'.$suffix.'.tmp';
        $this->writePreservedFile($backup, $original, $metadata);

        try {
            $this->writePreservedFile($temporary, $updated, $metadata);
            if (is_link($path) || $this->readFile($path) !== $original) {
                throw new RuntimeException('Dehydrated configuration changed during migration; retry after the other edit finishes.');
            }
            $this->renameConfiguration($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $backup;
    }

    protected function writePreservedFile(string $path, string $contents, array $metadata): void
    {
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException("Unable to create {$path}.");
        }

        try {
            if (! chmod($path, 0600)
                || fwrite($handle, $contents) !== strlen($contents)
                || ! fflush($handle)
                || ! chown($path, $metadata['uid'])
                || ! chgrp($path, $metadata['gid'])
                || ! chmod($path, $metadata['mode'] & 0777)) {
                throw new RuntimeException("Unable to write {$path} with the original configuration permissions.");
            }
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    protected function renameConfiguration(string $temporary, string $path): void
    {
        if (! @rename($temporary, $path)) {
            throw new RuntimeException("Unable to replace {$path}. The original configuration was preserved.");
        }
    }

    protected function readFile(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        return $contents;
    }

    protected function skipped(string $reason): array
    {
        return ['status' => 'skipped', 'message' => 'Certificate script migration skipped. '.$reason.' Configuration was left unchanged.'];
    }

    protected function isRoot(): bool
    {
        return ! function_exists('posix_geteuid') || posix_geteuid() === 0;
    }
}
