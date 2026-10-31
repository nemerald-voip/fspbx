<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Install frontend assets and run hooks for the operator's current checkout. */
class GitModuleUpdater
{
    private string $root;

    public function __construct(private FrontendAssets $assets, ?string $root = null)
    {
        $this->root = $root ?? base_path();
    }

    public function update(string $name, callable $configure): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name)) throw new RuntimeException('Invalid module name.');
        $directory = $this->root.'/Modules/'.$name;
        if (realpath($this->git($directory, ['rev-parse', '--show-toplevel'])) !== realpath($directory)) {
            throw new RuntimeException('The module must have its own Git checkout.');
        }
        $lockPath = $this->git($directory, ['rev-parse', '--git-path', 'fspbx-update.lock']);
        if (!str_starts_with($lockPath, '/')) $lockPath = $directory.'/'.$lockPath;
        $lock = fopen($lockPath, 'c');
        if (!$lock) throw new RuntimeException('Cannot open the module update lock.');
        $owner = stat(dirname($lockPath));
        if ($owner) { @chown($lockPath, $owner['uid']); @chgrp($lockPath, $owner['gid']); }
        chmod($lockPath, 0664);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Another update is running for this module.');
        }
        try {
            $this->assertNoGitOperation($directory);
            $target = $this->git($directory, ['rev-parse', 'HEAD']);
            if (!is_file($directory.'/module.json')) throw new RuntimeException('Module checkout is missing module.json.');
            $manifest = json_decode(file_get_contents($directory.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['name'] ?? null) !== $name || !is_string($manifest['version'] ?? null) || $manifest['version'] === '') {
                throw new RuntimeException('Module checkout has an invalid name or version in module.json.');
            }
            $package = null;
            if (is_dir($directory.'/Resources/assets/js/Pages')
                || $this->git($directory, ['cat-file', '-e', $target.':Resources/assets/js/Pages'], false) !== null) {
                $package = $this->existingPackage($name, $manifest['version'], $directory);
                if ($package === null) {
                    throw new RuntimeException('Matching frontend assets are missing or incompatible. Wait for the module build to finish, pull the source including Resources/dist, and retry php artisan modules:refresh. For local frontend changes, run npm run build.');
                }
            }
            // An operator may have edited the checkout while assets were checked.
            $this->assertNoGitOperation($directory);
            if ($this->git($directory, ['rev-parse', 'HEAD']) !== $target) {
                throw new RuntimeException('Git checkout changed during the update; retry after local work finishes.');
            }
            if ($package !== null) {
                $meta = $this->assets->validateModule($package, $name, $manifest['version']);
                try {
                    $this->assets->verifyModuleSource($directory, $meta);
                } catch (RuntimeException $error) {
                    throw new RuntimeException('Module frontend assets do not match this checkout. For local frontend changes, run npm run build.', 0, $error);
                }
                $this->assets->publish([$package], true);
            }
            // Retry hooks on subsequent runs even when Git is already up to date.
            $configure();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function existingPackage(string $name, string $version, string $source): ?string
    {
        $active = $this->assets->state()['modules'][$name] ?? null;
        $candidates = [$this->root.'/Modules/'.$name.'/Resources/dist'];
        if ($active && str_starts_with($active['directory'], 'storage/frontend/')) {
            array_unshift($candidates, $this->root.'/storage/app/public/'.substr($active['directory'], 8));
        }
        foreach ($candidates as $directory) {
            try {
                $meta = $this->assets->validateModule($directory, $name, $version);
                $this->assets->verifyModuleSource($source, $meta);
                return $directory;
            } catch (RuntimeException | \JsonException) {
                // A changed source/version/runtime needs a new released bundle.
            }
        }
        return null;
    }

    private function assertNoGitOperation(string $directory): void
    {
        foreach (['MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply', 'sequencer'] as $operation) {
            $path = $this->git($directory, ['rev-parse', '--git-path', $operation]);
            if (!str_starts_with($path, '/')) $path = $directory.'/'.$path;
            if (file_exists($path)) throw new RuntimeException('Finish the in-progress Git operation before updating this module.');
        }
    }

    private function git(string $directory, array $arguments, bool $required = true): ?string
    {
        $process = new Process(['git', ...$arguments], $directory, ['GIT_TERMINAL_PROMPT' => '0'], null, 120);
        $process->run();
        if (!$process->isSuccessful()) {
            if (!$required) return null;
            $reason = self::failureReason($process->getErrorOutput());
            throw new RuntimeException('Git '.$arguments[0].' failed: '.$reason
                .' Check '.$directory.'.');
        }
        return trim($process->getOutput());
    }

    /** Local Git failures must not expose arbitrary command output. */
    public static function failureReason(string $error): string
    {
        $causes = [
            '/dubious ownership/i' => 'Git rejected the repository ownership for the user running the update',
            '/not a git repository/i' => 'The module is not a Git checkout',
            '/Read-only file system|insufficient permission|unable to (create|write)|Permission denied/i'
                => 'Git could not access or write repository files; check filesystem permissions',
            '/unknown option|unrecognized option/i' => 'The installed Git rejected an updater command option',
        ];
        foreach ($causes as $pattern => $reason) {
            if (preg_match($pattern, $error)) return $reason;
        }
        return 'Could not inspect the local module checkout; check it with git status';
    }
}
