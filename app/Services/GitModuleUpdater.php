<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Install frontend assets and run hooks for the operator's current checkout. */
class GitModuleUpdater
{
    private string $root;
    private bool $ignoreEditorCredentials = false;

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
        $temporary = sys_get_temp_dir().'/fspbx-git-module-'.bin2hex(random_bytes(12));
        try {
            $this->assertNoGitOperation($directory);
            $target = $this->git($directory, ['rev-parse', 'HEAD']);
            if (!is_file($directory.'/module.json')) throw new RuntimeException('Module checkout is missing module.json.');
            $manifest = json_decode(file_get_contents($directory.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['name'] ?? null) !== $name || !is_string($manifest['version'] ?? null) || $manifest['version'] === '') {
                throw new RuntimeException('Module checkout has an invalid name or version in module.json.');
            }
            if (!mkdir($temporary, 0700)) throw new RuntimeException('Cannot create the module staging directory.');
            $package = null;
            if (is_dir($directory.'/Resources/assets/js/Pages')
                || $this->git($directory, ['cat-file', '-e', $target.':Resources/assets/js/Pages'], false) !== null) {
                $package = $this->existingPackage($name, $manifest['version'], $directory);
                if ($package === null) {
                    $core = $this->assets->prepared() ?? ($this->assets->state()['core'] ?? null);
                    $runtime = $core['metadata']['runtime'] ?? '';
                    if (!preg_match('/^[a-f0-9]{64}$/D', $runtime)) {
                        throw new RuntimeException('Prepare core assets first with php artisan frontend:install --prepare.');
                    }
                    // Asset refs use the same remote and SSH/HTTPS credentials as code.
                    $ref = 'refs/tags/frontend/'.$target.'/'.$runtime;
                    $remote = $this->assetRemote($directory);
                    try {
                        $this->git($directory, ['fetch', '--no-tags', '--no-recurse-submodules', $remote, $ref]);
                    } catch (RuntimeException $error) {
                        throw new RuntimeException('Could not download frontend assets for checked-out commit '.substr($target, 0, 12).'. '
                            .$error->getMessage().' If the frontend reference is missing, publish matching assets. Retry php artisan modules:refresh.', 0, $error);
                    }
                    $assetCommit = $this->git($directory, ['rev-parse', 'FETCH_HEAD^{commit}']);
                    $this->git($directory, ['archive', '--format=tar', '--output='.$temporary.'/frontend.tar', $assetCommit]);
                    $package = $temporary.'/frontend';
                    $this->assets->extract($temporary.'/frontend.tar', $package);
                    $meta = $this->assets->validateModule($package, $name, $manifest['version']);
                    if ($meta['origin'] !== 'release') throw new RuntimeException('Git frontend package must be a released build.');
                }
            }
            // An operator may have edited the checkout while downloads were running.
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
            $this->assets->removeDirectory($temporary);
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

    private function assetRemote(string $directory): string
    {
        $branch = $this->git($directory, ['symbolic-ref', '--quiet', '--short', 'HEAD'], false);
        $remote = $branch === null ? null : $this->git($directory, ['config', '--get', 'branch.'.$branch.'.remote'], false);
        if (!$remote || $remote === '.') $remote = 'origin';
        if (str_starts_with($remote, '-') || !in_array($remote, explode("\n", $this->git($directory, ['remote'])), true)) {
            throw new RuntimeException('Configure origin or a tracking remote to download frontend assets for this module.');
        }
        return $remote;
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
        $process = $this->runGitProcess($directory, $arguments, $this->gitEnvironment());
        $retriedEditorFailure = false;
        if (!$process->isSuccessful() && !$this->ignoreEditorCredentials
            && self::unavailableEditorBridge($process->getErrorOutput())) {
            // A disconnected VS Code terminal can retain a dead askpass socket.
            // Retry once using normal configured credentials, preserving SSH agents
            // and all other Git settings. Never modify the parent shell or Git config.
            $this->ignoreEditorCredentials = true;
            $retriedEditorFailure = true;
            $process = $this->runGitProcess($directory, $arguments, $this->gitEnvironment());
        }
        if (!$process->isSuccessful()) {
            if (!$required) return null;
            $reason = ($retriedEditorFailure ? 'The VS Code credential bridge is unavailable. Retrying without it failed: ' : '')
                .self::failureReason($process->getErrorOutput());
            throw new RuntimeException('Git '.$arguments[0].' failed: '.$reason
                .' Check '.$directory.'.');
        }
        return trim($process->getOutput());
    }

    private function gitEnvironment(): array
    {
        $environment = ['GIT_TERMINAL_PROMPT' => '0'];
        if ($this->ignoreEditorCredentials) {
            foreach (['GIT_ASKPASS', 'VSCODE_GIT_ASKPASS_NODE', 'VSCODE_GIT_ASKPASS_MAIN',
                'VSCODE_GIT_ASKPASS_EXTRA_ARGS', 'VSCODE_GIT_IPC_HANDLE'] as $name) {
                $environment[$name] = false;
            }
        }
        return $environment;
    }

    protected function runGitProcess(string $directory, array $arguments, array $environment): Process
    {
        $process = new Process(['git', ...$arguments], $directory, $environment, null, 120);
        $process->run();
        return $process;
    }

    private static function unavailableEditorBridge(string $error): bool
    {
        return preg_match('/vscode-git-[^\s]+\.sock/i', $error)
            && preg_match('/ECONNREFUSED|ENOENT|EACCES/', $error);
    }

    /** Report actionable causes without copying credential-bearing Git output. */
    public static function failureReason(string $error): string
    {
        if (self::unavailableEditorBridge($error)) {
            return 'The VS Code Git credential bridge is unavailable; configure unattended authentication for the user running the update';
        }
        $causes = [
            '/dubious ownership/i' => 'Git rejected the repository ownership for the user running the update',
            '/Permission denied \(publickey/i' => 'SSH key authentication failed for the user running the update',
            '/Host key verification failed/i' => 'SSH host-key verification failed',
            '/could not read (Username|Password)|terminal prompts disabled|Authentication failed|Invalid username or (token|password)|HTTP Basic: Access denied/i'
                => 'HTTPS authentication is unavailable or was rejected; unattended updates cannot prompt for credentials',
            '/(cannot run|unable to read).*askpass|credential-.* is not a git command/i' => 'The configured Git credential helper is unavailable',
            '/couldn.t find remote ref/i' => 'The requested branch or frontend asset reference does not exist on the remote',
            '/Repository not found|does not appear to be a git repository/i' => 'The remote repository could not be found or accessed',
            '/Could not resolve proxy/i' => 'The configured proxy could not be resolved',
            '/Could not resolve host|Could not resolve hostname|Name or service not known|Temporary failure in name resolution/i'
                => 'The remote hostname could not be resolved',
            '/SSL certificate problem|server certificate verification failed|certificate verify failed/i' => 'TLS certificate verification failed',
            '/cannot open .*FETCH_HEAD|Read-only file system|insufficient permission|unable to (create|write)|Permission denied/i'
                => 'Git could not access or write repository files; check filesystem permissions',
            '/Connection timed out|Failed to connect|Connection refused|Network is unreachable/i' => 'The remote connection failed or timed out',
            '/unknown option|unrecognized option/i' => 'The installed Git rejected an updater command option',
            '/local changes.*overwritten/is' => 'Local changes prevent the checkout from advancing',
            '/not possible to fast-forward/i' => 'The checkout cannot be fast-forwarded',
        ];
        foreach ($causes as $pattern => $reason) {
            if (preg_match($pattern, $error)) return $reason;
        }
        return 'Git returned an unrecognized error; run a manual fetch dry-run to inspect it';
    }
}
