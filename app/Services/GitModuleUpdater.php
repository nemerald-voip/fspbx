<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Fast-forward module source only after its matching frontend is available. */
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
        $temporary = sys_get_temp_dir().'/fspbx-git-module-'.bin2hex(random_bytes(12));
        try {
            $this->assertClean($directory);
            $branch = $this->git($directory, ['symbolic-ref', '--quiet', '--short', 'HEAD'], false);
            if ($branch === null) throw new RuntimeException('Detached Git checkout; select a tracking branch before updating.');
            $remote = $this->git($directory, ['config', '--get', 'branch.'.$branch.'.remote'], false);
            $merge = $this->git($directory, ['config', '--get', 'branch.'.$branch.'.merge'], false);
            if (!$remote || $remote === '.' || str_starts_with($remote, '-') || !$merge || !str_starts_with($merge, 'refs/heads/')) {
                throw new RuntimeException('Configure an upstream remote branch before updating this module.');
            }
            $before = $this->git($directory, ['rev-parse', 'HEAD']);
            $this->git($directory, ['fetch', '--no-tags', '--no-recurse-submodules', $remote, $merge]);
            $target = $this->git($directory, ['rev-parse', 'FETCH_HEAD^{commit}']);
            if ($this->git($directory, ['merge-base', '--is-ancestor', $before, $target], false) === null) {
                throw new RuntimeException('Local commits cannot be fast-forwarded to the upstream branch. Resolve them manually; no checkout files were changed.');
            }
            $manifest = json_decode($this->git($directory, ['show', $target.':module.json']), true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['name'] ?? null) !== $name || !is_string($manifest['version'] ?? null) || $manifest['version'] === '') {
                throw new RuntimeException('Upstream module.json has an invalid name or version.');
            }
            if (!mkdir($temporary, 0700)) throw new RuntimeException('Cannot create the module staging directory.');
            $package = null;
            if ($this->git($directory, ['cat-file', '-e', $target.':Resources/assets/js/Pages'], false) !== null) {
                // Inspect the incoming frontend without changing the live checkout.
                $this->git($directory, ['archive', '--format=tar', '--output='.$temporary.'/source.tar', $target, 'module.json', 'Resources/assets']);
                $this->assets->extract($temporary.'/source.tar', $temporary.'/source');
                $package = $this->existingPackage($name, $manifest['version'], $temporary.'/source');
                if ($package === null) {
                    $core = $this->assets->prepared() ?? ($this->assets->state()['core'] ?? null);
                    $runtime = $core['metadata']['runtime'] ?? '';
                    if (!preg_match('/^[a-f0-9]{64}$/D', $runtime)) {
                        throw new RuntimeException('Prepare core assets first with php artisan frontend:install --prepare.');
                    }
                    // Asset refs use the same remote and SSH/HTTPS credentials as code.
                    $ref = 'refs/tags/frontend/'.$target.'/'.$runtime;
                    if ($this->git($directory, ['fetch', '--no-tags', '--no-recurse-submodules', $remote, $ref], false) === null) {
                        throw new RuntimeException('Matching frontend assets are unavailable for Git commit '.substr($target, 0, 12).'. Publish the module frontend and retry php artisan modules:refresh. No checkout files were changed.');
                    }
                    $assetCommit = $this->git($directory, ['rev-parse', 'FETCH_HEAD^{commit}']);
                    $this->git($directory, ['archive', '--format=tar', '--output='.$temporary.'/frontend.tar', $assetCommit]);
                    $package = $temporary.'/frontend';
                    $this->assets->extract($temporary.'/frontend.tar', $package);
                    $meta = $this->assets->validateModule($package, $name, $manifest['version']);
                    if ($meta['origin'] !== 'release') throw new RuntimeException('Git frontend package must be a released build.');
                    $this->assets->verifyModuleSource($temporary.'/source', $meta);
                }
            }
            // An operator may have edited the checkout while downloads were running.
            $this->assertClean($directory);
            if ($this->git($directory, ['rev-parse', 'HEAD']) !== $before
                || $this->git($directory, ['symbolic-ref', '--quiet', '--short', 'HEAD'], false) !== $branch) {
                throw new RuntimeException('Git checkout changed during the update; retry after local work finishes.');
            }
            // This is the fast-forward part of git pull, using the already verified commit.
            $this->git($directory, ['-c', 'core.hooksPath=/dev/null', '-c', 'submodule.recurse=false', 'merge', '--ff-only', '--no-edit', $target]);
            if ($package !== null) {
                $meta = $this->assets->validateModule($package, $name, $manifest['version']);
                $this->assets->verifyModuleSource($directory, $meta);
                $this->assets->publish([$package]);
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

    private function assertClean(string $directory): void
    {
        if ($this->git($directory, ['status', '--porcelain', '--untracked-files=normal']) !== '') {
            throw new RuntimeException('Local changes or untracked files exist. Commit or stash them before retrying; the module was left unchanged.');
        }
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
            // Remote URLs and credential helpers can print secrets; keep them out of update logs.
            throw new RuntimeException('Git '.$arguments[0].' failed. Check repository access and upstream configuration in '.$directory.'.');
        }
        return trim($process->getOutput());
    }
}
