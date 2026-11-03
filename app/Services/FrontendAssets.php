<?php

namespace App\Services;

use RuntimeException;

/** Immutable asset packages and one atomic activation record, shared with npm. */
class FrontendAssets
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? base_path();
    }

    public function state(): array
    {
        $file = $this->root.'/storage/app/frontend/active.json';
        return is_file($file) ? $this->json($file) : ['core' => null, 'modules' => []];
    }

    public function buildDirectory(): string
    {
        return $this->state()['core']['directory'] ?? 'storage/vite';
    }

    public function browserConfiguration(array $enabledModules): array
    {
        $state = $this->state();
        $core = $state['core'] ?? null;
        $result = ['imports' => [], 'modules' => [], 'css' => [], 'buildDirectory' => $core['directory'] ?? 'storage/vite'];
        if (!$core) return $result;
        foreach ($core['metadata']['imports'] as $name => $file) {
            $result['imports'][$name] = '/'.$core['directory'].'/'.$file;
        }
        foreach ($enabledModules as $name) {
            $module = $state['modules'][$name] ?? null;
            if (!$module || !$this->compatible($core['metadata'], $module['metadata'])) continue;
            // Do not keep serving a previous module's frontend after its PHP
            // source has been independently replaced or the module removed.
            $manifest = $this->root.'/Modules/'.$name.'/module.json';
            if (!is_file($manifest) || ($this->json($manifest)['version'] ?? null) !== $module['metadata']['version']) continue;
            $result['modules'][$name] = '/'.$module['directory'].'/'.$module['metadata']['entry'];
            foreach ($module['metadata']['css'] as $file) {
                $result['css'][] = '/'.$module['directory'].'/'.$file;
            }
        }
        return $result;
    }

    public function compatible(array $core, array $module): bool
    {
        return $core['api'] === $module['api'] && hash_equals($core['runtime'], $module['runtime']);
    }

    public function sourceFiles(?string $module = null, ?string $moduleDirectory = null): array
    {
        if ($module !== null) $this->moduleName($module);
        $base = $module ? ($moduleDirectory ?? $this->root.'/Modules/'.$module) : $this->root;
        $paths = $module ? ['module.json', 'Resources/assets'] : [
            'package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
            'config/version.php', 'build', 'resources/js', 'resources/scss', 'resources/lang',
        ];
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($base.'/'.$path)) {
                foreach ($this->files($base.'/'.$path) as $file) $files[] = $path.'/'.$file;
            } elseif (is_file($base.'/'.$path)) {
                $files[] = $path;
            }
        }
        sort($files, SORT_STRING);
        $result = [];
        foreach ($files as $file) {
            if (preg_match('~^resources/lang/php_.*\.json$~', $file)) continue;
            $result[$file] = hash_file('sha256', $base.'/'.$file);
        }
        return $result;
    }

    public function validate(string $directory, ?string $kind = null, ?string $name = null, ?string $version = null): array
    {
        $meta = $this->json($directory.'/frontend.json');
        if (($meta['schema'] ?? null) !== 1 || !in_array($meta['kind'] ?? null, ['core', 'module'], true)
            || !is_int($meta['api'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $meta['runtime'] ?? '')
            || !is_array($meta['files'] ?? null) || !$meta['files'] || !is_array($meta['sources'] ?? null)
            || !in_array($meta['origin'] ?? null, ['local', 'release'], true)
            || !is_string($meta['version'] ?? null) || !is_array($meta['css'] ?? null)) {
            throw new RuntimeException('Invalid frontend package metadata.');
        }
        if (($kind && $meta['kind'] !== $kind) || ($name && ($meta['name'] ?? null) !== $name)
            || ($version && $meta['version'] !== $version)) {
            throw new RuntimeException('Frontend package does not match the requested release.');
        }
        if ($meta['kind'] === 'module') $this->moduleName($meta['name'] ?? '');
        $actual = $this->files($directory);
        $expected = array_merge(array_keys($meta['files']), ['frontend.json']);
        sort($actual); sort($expected);
        if ($actual !== $expected) throw new RuntimeException('Frontend package file list is incomplete or contains unexpected files.');
        $size = 0;
        foreach ($meta['files'] as $file => $checksum) {
            $this->safePath($file);
            if (!preg_match('/^[a-f0-9]{64}$/D', $checksum) || !hash_equals($checksum, hash_file('sha256', $directory.'/'.$file))) {
                throw new RuntimeException('Frontend package checksum failed: '.$file);
            }
            $size += filesize($directory.'/'.$file);
        }
        if (count($actual) > 10000 || $size > 268435456) throw new RuntimeException('Frontend package is too large.');
        $references = $meta['css'];
        if ($meta['kind'] === 'core') {
            if (!is_array($meta['imports'] ?? null) || !$meta['imports']) throw new RuntimeException('Shared frontend runtime is missing.');
            $manifest = $this->json($directory.'/manifest.json');
            foreach (['resources/js/vue.js', 'resources/scss/tailwind.css'] as $entry) {
                if (!isset($manifest[$entry]['file'])) throw new RuntimeException('Core frontend entry is missing: '.$entry);
                $references[] = $manifest[$entry]['file'];
            }
            $references = array_merge($references, array_values($meta['imports']));
        } else {
            $references[] = $meta['entry'] ?? '';
        }
        foreach ($references as $file) {
            if (!is_string($file) || !isset($meta['files'][$file])) throw new RuntimeException('Frontend entry references a missing file.');
        }
        return $meta;
    }

    public function verifyCoreSource(array $meta): void
    {
        if ($meta['sources'] !== $this->sourceFiles()) {
            throw new RuntimeException('Released frontend assets do not match this checkout. For development or a local patch, run npm run build.');
        }
    }

    /** Copy packages before acquiring the activation lock; failed work is never active. */
    public function publish(array $directories, bool $verifySources = false): array
    {
        if (!$directories) throw new RuntimeException('No frontend packages were supplied.');
        $packages = [];
        foreach ($directories as $directory) {
            $meta = $this->validate($directory);
            if ($verifySources && $meta['sources'] !== $this->sourceFiles($meta['kind'] === 'module' ? $meta['name'] : null)) {
                throw new RuntimeException('Source changed since the frontend build. Build again before activating.');
            }
            $packages[] = $this->store($directory, $meta);
        }
        return $this->activate($packages);
    }

    public function prepare(string $directory, string $version): void
    {
        $meta = $this->validate($directory, 'core', 'fspbx', $version);
        if ($meta['origin'] !== 'release') throw new RuntimeException('Expected a released frontend package.');
        $this->verifyCoreSource($meta);
        $package = $this->store($directory, $meta);
        $this->writeJson($this->preparedPath(), $package);
    }

    public function prepared(): ?array
    {
        $file = $this->preparedPath();
        return is_file($file) ? $this->json($file) : null;
    }

    public function prepareExistingLocal(): bool
    {
        $package = $this->state()['core'] ?? null;
        if (!$package || $package['metadata']['origin'] !== 'local') return false;
        $meta = $this->validate($this->absoluteDirectory($package), 'core');
        $this->verifyCoreSource($meta);
        $this->writeJson($this->preparedPath(), $package);
        return true;
    }

    public function rollback(string $version): array
    {
        $previous = $this->json($this->root.'/storage/app/frontend/previous.json');
        if (($previous['core']['metadata']['version'] ?? null) !== $version) {
            throw new RuntimeException('The previous frontend belongs to another application version. Restore matching application code first.');
        }
        $packages = array_merge([$previous['core']], array_values($previous['modules'] ?? []));
        foreach ($packages as $package) {
            $meta = $this->validate($this->absoluteDirectory($package));
            if ($meta['kind'] === 'module') {
                $installed = $this->root.'/Modules/'.$meta['name'].'/module.json';
                if (!is_file($installed) || ($this->json($installed)['version'] ?? null) !== $meta['version']) {
                    throw new RuntimeException('Restore matching '.$meta['name'].' source before rolling back its frontend.');
                }
            }
        }
        return $this->activate($packages, true);
    }

    public function activatePrepared(): array
    {
        $package = $this->prepared();
        if (!$package) throw new RuntimeException('No verified frontend release is staged for this checkout. Run php artisan frontend:install.');
        $meta = $this->validate($this->absoluteDirectory($package), 'core');
        $this->verifyCoreSource($meta);
        return $this->activate([$package]);
    }

    public function validateModule(string $directory, string $name, string $version): array
    {
        $meta = $this->validate($directory, 'module', $name, $version);
        $core = $this->prepared() ?? ($this->state()['core'] ?? null);
        if (!$core || !$this->compatible($core['metadata'], $meta)) {
            throw new RuntimeException($name.': frontend assets require a compatible FS PBX release.');
        }
        return $meta;
    }

    public function verifyModuleSource(string $directory, array $metadata): void
    {
        if ($metadata['sources'] !== $this->sourceFiles($metadata['name'], $directory)) {
            throw new RuntimeException('Module frontend assets do not match the packaged module source.');
        }
    }

    public function extract(string $archive, string $destination): void
    {
        $this->mkdir($destination);
        $phar = new \PharData($archive);
        $prefix = 'phar://'.$archive.'/';
        $size = 0; $count = 0;
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            $relative = substr($file->getPathname(), strlen($prefix));
            $this->safePath($relative);
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Asset archives may only contain regular files.');
            $size += $file->getSize();
            if (++$count > 10000 || $size > 268435456) throw new RuntimeException('Frontend archive is too large.');
            $this->mkdir(dirname($destination.'/'.$relative));
            if (!copy($file->getPathname(), $destination.'/'.$relative)) throw new RuntimeException('Unable to extract frontend assets.');
        }
    }

    private function store(string $source, array $meta): array
    {
        $id = hash_file('sha256', $source.'/frontend.json');
        $relative = 'storage/frontend/'.($meta['kind'] === 'core' ? 'core' : 'modules/'.$meta['name']).'/'.$id;
        $destination = $this->root.'/storage/app/public/'.substr($relative, strlen('storage/'));
        $this->mkdir(dirname($destination));
        if (!is_dir($destination)) {
            $temporary = $destination.'.'.bin2hex(random_bytes(8));
            try {
                $this->mkdir($temporary);
                foreach ($this->files($source) as $file) {
                    $this->mkdir(dirname($temporary.'/'.$file));
                    if (!copy($source.'/'.$file, $temporary.'/'.$file)) throw new RuntimeException('Unable to stage frontend assets.');
                    chmod($temporary.'/'.$file, 0644);
                    $this->inheritOwnership($temporary.'/'.$file);
                }
                $this->validate($temporary);
                if (!rename($temporary, $destination) && !is_dir($destination)) throw new RuntimeException('Unable to store frontend assets.');
            } finally {
                $this->removeDirectory($temporary);
            }
        } else {
            $this->validate($destination);
        }
        return ['directory' => $relative, 'metadata' => $meta];
    }

    private function activate(array $packages, bool $replace = false): array
    {
        $directory = $this->root.'/storage/app/frontend';
        $this->mkdir($directory);
        $lock = fopen($directory.'/publish.lock', 'c');
        if ($lock) $this->inheritOwnership($directory.'/publish.lock');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Unable to lock frontend publication.');
        try {
            $state = $replace ? ['core' => null, 'modules' => []] : $this->state();
            foreach ($packages as $package) {
                if ($package['metadata']['kind'] === 'core') $state['core'] = $package;
                else $state['modules'][$package['metadata']['name']] = $package;
            }
            $candidateCore = $state['core'] ?? $this->prepared();
            if (!$candidateCore) throw new RuntimeException('Install core frontend assets first.');
            // A module update may target the staged core during app:update.
            // The browser only exposes it once that matching core is activated.
            foreach ($packages as $package) {
                if ($package['metadata']['kind'] !== 'module') continue;
                $core = $candidateCore;
                if (!$this->compatible($core['metadata'], $package['metadata'])) {
                    $prepared = $this->prepared();
                    if (!$prepared || !$this->compatible($prepared['metadata'], $package['metadata'])) {
                        throw new RuntimeException('Module frontend runtime is incompatible.');
                    }
                }
            }
            $previous = $this->state();
            $this->writeJson($directory.'/previous.json', $previous);
            $this->writeJson($directory.'/active.json', $state);
            return $state;
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    private function preparedPath(): string
    {
        return $this->root.'/storage/app/frontend/prepared-'.hash('sha256', json_encode($this->sourceFiles(), JSON_UNESCAPED_SLASHES)).'.json';
    }

    private function absoluteDirectory(array $package): string
    {
        $this->safePath($package['directory']);
        return $this->root.'/storage/app/public/'.substr($package['directory'], strlen('storage/'));
    }

    private function json(string $file): array
    {
        if (!is_file($file)) throw new RuntimeException('Frontend file is missing: '.basename($file));
        $value = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('Invalid frontend JSON: '.basename($file));
        return $value;
    }

    private function writeJson(string $file, array $value): void
    {
        $this->mkdir(dirname($file));
        $temporary = $file.'.'.bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
                throw new RuntimeException('Unable to write frontend activation record.');
            }
            $this->inheritOwnership($temporary);
            if (!chmod($temporary, 0644) || !rename($temporary, $file)) {
                throw new RuntimeException('Unable to activate frontend assets.');
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    private function files(string $directory): array
    {
        $result = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Frontend files must be regular files.');
            $result[] = substr($file->getPathname(), strlen($directory) + 1);
        }
        sort($result, SORT_STRING);
        return $result;
    }

    private function safePath(string $path): void
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0") || str_starts_with($path, '/')
            || preg_match('~(^|/)\.{1,2}(/|$)~', $path) || str_contains($path, ':')) {
            throw new RuntimeException('Unsafe frontend file path.');
        }
    }

    private function moduleName(string $name): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name)) throw new RuntimeException('Invalid frontend module name.');
    }

    private function mkdir(string $directory): void
    {
        if (is_dir($directory)) return;
        $this->mkdir(dirname($directory));
        if (!mkdir($directory, 0755) && !is_dir($directory)) throw new RuntimeException('Unable to create frontend directory.');
        $this->inheritOwnership($directory);
    }

    private function inheritOwnership(string $path): void
    {
        // Root-run updates/builds should not prevent the storage owner from
        // publishing the next build. Never change ownership outside new assets.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $parent = stat(dirname($path));
            if (!chown($path, $parent['uid']) || !chgrp($path, $parent['gid'])) {
                throw new RuntimeException('Unable to preserve frontend storage ownership.');
            }
        }
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
