<?php

namespace App\Services;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class LegacyProvisionTemplateService
{
    private const MAX_FILE_SIZE = 2097152;

    private ?string $resolvedRoot = null;

    public function root(): string
    {
        if ($this->resolvedRoot !== null) {
            return $this->resolvedRoot;
        }

        foreach ($this->rootCandidates() as $candidate) {
            $resolved = realpath($candidate);

            if ($resolved !== false && is_dir($resolved)) {
                return $this->resolvedRoot = rtrim($resolved, DIRECTORY_SEPARATOR);
            }
        }

        throw new RuntimeException('The legacy provisioning template directory could not be found.');
    }

    public function files(): array
    {
        $root = $this->root();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $path = $this->relativePath($file->getPathname());

            if ($this->shouldIgnore($path)) {
                continue;
            }

            $files[] = $this->metadata($file->getRealPath() ?: $file->getPathname());
        }

        usort($files, fn (array $left, array $right) => strnatcasecmp($left['path'], $right['path']));

        return $files;
    }

    public function read(string $relativePath): array
    {
        $path = $this->resolveExistingFile($relativePath);
        $size = filesize($path);

        if ($size === false || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('This file is too large to edit in the browser.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('The template file could not be read.');
        }

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($content, 'UTF-8')) {
            throw new RuntimeException('This file is not UTF-8 text and cannot be edited here.');
        }

        return [
            ...$this->metadata($path),
            'content' => $content,
        ];
    }

    public function write(string $relativePath, string $content): array
    {
        if (strlen($content) > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('This file is too large to save in the browser.');
        }

        $path = $this->resolveExistingFile($relativePath);

        if (! is_writable($path)) {
            throw new RuntimeException('The template file is not writable. Check its owner and permissions.');
        }

        $bytesWritten = file_put_contents($path, $content, LOCK_EX);

        if ($bytesWritten === false) {
            throw new RuntimeException('The template file could not be saved.');
        }

        clearstatcache(true, $path);

        return $this->metadata($path);
    }

    private function rootCandidates(): array
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => [
                '/usr/share/fusionpbx/templates/provision',
                '/etc/fusionpbx/resources/templates/provision',
                public_path('resources/templates/provision'),
            ],
            'BSD' => [
                '/usr/local/share/fusionpbx/templates/provision',
                '/usr/local/etc/fusionpbx/resources/templates/provision',
                public_path('resources/templates/provision'),
            ],
            default => [
                public_path('resources/templates/provision'),
            ],
        };
    }

    private function resolveExistingFile(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $resolved = realpath($this->root().DIRECTORY_SEPARATOR.$relativePath);

        if ($resolved === false || ! is_file($resolved) || ! $this->isInsideRoot($resolved)) {
            throw new InvalidArgumentException('The selected provisioning template file is invalid.');
        }

        return $resolved;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new InvalidArgumentException('The selected provisioning template path is invalid.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('The selected provisioning template path is invalid.');
            }
        }

        return $path;
    }

    private function isInsideRoot(string $path): bool
    {
        return str_starts_with(
            str_replace('\\', '/', $path),
            str_replace('\\', '/', $this->root()).'/'
        );
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($this->root()))), '/');
    }

    private function shouldIgnore(string $path): bool
    {
        $segments = explode('/', strtolower($path));

        if (in_array('.git', $segments, true) || in_array('.svn', $segments, true)) {
            return true;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'db',
            'gif',
            'ico',
            'jpg',
            'jpeg',
            'png',
            'ttf',
            'woff',
            'woff2',
        ], true);
    }

    private function metadata(string $path): array
    {
        $relativePath = $this->relativePath($path);
        $directory = str_replace('\\', '/', dirname($relativePath));
        $modifiedAt = filemtime($path);

        return [
            'path' => $relativePath,
            'name' => basename($relativePath),
            'directory' => $directory === '.' ? '' : $directory,
            'vendor' => explode('/', $relativePath, 2)[0],
            'extension' => strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)),
            'size' => filesize($path) ?: 0,
            'modified_at' => $modifiedAt === false ? null : date(DATE_ATOM, $modifiedAt),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
        ];
    }
}
