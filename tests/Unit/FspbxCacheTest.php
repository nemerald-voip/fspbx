<?php

namespace Tests\Unit;

use App\Models\FusionCache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use Tests\TestCase;

class FspbxCacheTest extends TestCase
{
    public function test_file_cache_cleaner_preserves_syncthing_metadata(): void
    {
        $cachePath = $this->makeTempDirectory();

        mkdir($cachePath . DIRECTORY_SEPARATOR . '.stfolder');
        file_put_contents($cachePath . DIRECTORY_SEPARATOR . '.stfolder' . DIRECTORY_SEPARATOR . 'marker', '');
        file_put_contents($cachePath . DIRECTORY_SEPARATOR . '.stignore', 'ignored');
        mkdir($cachePath . DIRECTORY_SEPARATOR . '.stversions');
        file_put_contents($cachePath . DIRECTORY_SEPARATOR . '.stversions' . DIRECTORY_SEPARATOR . 'kept', '');

        mkdir($cachePath . DIRECTORY_SEPARATOR . 'directory.example.com');
        file_put_contents($cachePath . DIRECTORY_SEPARATOR . 'directory.example.com' . DIRECTORY_SEPARATOR . 'entry', 'cached');
        file_put_contents($cachePath . DIRECTORY_SEPARATOR . 'dialplan.example.com', 'cached');

        try {
            $method = new ReflectionMethod(FusionCache::class, 'cleanFileCacheDirectory');
            $method->setAccessible(true);
            $method->invoke(null, $cachePath);

            $this->assertDirectoryExists($cachePath . DIRECTORY_SEPARATOR . '.stfolder');
            $this->assertFileExists($cachePath . DIRECTORY_SEPARATOR . '.stignore');
            $this->assertDirectoryExists($cachePath . DIRECTORY_SEPARATOR . '.stversions');
            $this->assertDirectoryDoesNotExist($cachePath . DIRECTORY_SEPARATOR . 'directory.example.com');
            $this->assertFileDoesNotExist($cachePath . DIRECTORY_SEPARATOR . 'dialplan.example.com');
        } finally {
            $this->removeDirectory($cachePath);
        }
    }

    private function makeTempDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fspbx-cache-test-' . bin2hex(random_bytes(6));

        mkdir($path);

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
