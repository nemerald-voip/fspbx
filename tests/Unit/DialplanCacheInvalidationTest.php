<?php

namespace Tests\Unit;

use App\Models\FusionCache;
use App\Services\DialplanService;
use ReflectionProperty;
use Tests\TestCase;

class DialplanCacheInvalidationTest extends TestCase
{
    public function test_public_context_invalidation_clears_multiple_and_single_mode_entries(): void
    {
        $this->withFileCache(function (string $cachePath) {
            $this->putCacheFile($cachePath, 'dialplan.public');
            $this->putCacheFile($cachePath, 'dialplan.public.15304792220');
            $this->putCacheFile($cachePath, 'dialplan.public.18005550100');
            $this->putCacheFile($cachePath, 'dialplan.example.com');

            (new DialplanService())->clearDialplanCache('public');

            $this->assertFileDoesNotExist($cachePath . '/dialplan.public');
            $this->assertFileDoesNotExist($cachePath . '/dialplan.public.15304792220');
            $this->assertFileDoesNotExist($cachePath . '/dialplan.public.18005550100');
            $this->assertFileExists($cachePath . '/dialplan.example.com');
        });
    }

    public function test_domain_context_invalidation_does_not_clear_other_contexts(): void
    {
        $this->withFileCache(function (string $cachePath) {
            $this->putCacheFile($cachePath, 'dialplan.example.com');
            $this->putCacheFile($cachePath, 'dialplan.example.com.1000');
            $this->putCacheFile($cachePath, 'dialplan.other.example.com');

            (new DialplanService())->clearDialplanCache('example.com');

            $this->assertFileDoesNotExist($cachePath . '/dialplan.example.com');
            $this->assertFileDoesNotExist($cachePath . '/dialplan.example.com.1000');
            $this->assertFileExists($cachePath . '/dialplan.other.example.com');
        });
    }

    public function test_global_context_invalidation_clears_every_dialplan_entry(): void
    {
        $this->withFileCache(function (string $cachePath) {
            $this->putCacheFile($cachePath, 'dialplan.public.15304792220');
            $this->putCacheFile($cachePath, 'dialplan.example.com');
            $this->putCacheFile($cachePath, 'dialplan.mode');
            $this->putCacheFile($cachePath, 'directory.1000@example.com');

            (new DialplanService())->clearDialplanCache('global');

            $this->assertFileDoesNotExist($cachePath . '/dialplan.public.15304792220');
            $this->assertFileDoesNotExist($cachePath . '/dialplan.example.com');
            $this->assertFileDoesNotExist($cachePath . '/dialplan.mode');
            $this->assertFileExists($cachePath . '/directory.1000@example.com');
        });
    }

    private function withFileCache(callable $callback): void
    {
        $cachePath = sys_get_temp_dir() . '/fspbx-dialplan-cache-test-' . bin2hex(random_bytes(6));
        mkdir($cachePath);

        $cacheType = new ReflectionProperty(FusionCache::class, 'cacheType');
        $cacheType->setAccessible(true);
        $cacheLocation = new ReflectionProperty(FusionCache::class, 'cacheLocation');
        $cacheLocation->setAccessible(true);

        $cacheType->setValue(null, 'file');
        $cacheLocation->setValue(null, $cachePath);

        try {
            $callback($cachePath);
        } finally {
            $cacheType->setValue(null, null);
            $cacheLocation->setValue(null, null);

            foreach (glob($cachePath . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($cachePath);
        }
    }

    private function putCacheFile(string $cachePath, string $key): void
    {
        file_put_contents($cachePath . '/' . $key, 'cached');
    }
}
