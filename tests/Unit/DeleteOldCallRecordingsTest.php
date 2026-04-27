<?php

namespace Tests\Unit;

use App\Jobs\DeleteOldCallRecordings;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

class DeleteOldCallRecordingsTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/fspbx-recordings-cleanup-' . bin2hex(random_bytes(8));
        mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_it_deletes_old_recordings_recursively_under_domain_archives(): void
    {
        $cutoff = time() - 90;

        $oldNestedWav = $this->createFile('domain.example/archive/2025/Oct/15/old.wav', $cutoff - 10);
        $oldNestedMp3 = $this->createFile('domain.example/archive/2025/Oct/15/old.mp3', $cutoff - 10);
        $oldDirectWav = $this->createFile('domain.example/archive/old-direct.wav', $cutoff - 10);
        $recentNestedWav = $this->createFile('domain.example/archive/2026/Apr/26/recent.wav', $cutoff + 10);
        $oldTextFile = $this->createFile('domain.example/archive/2025/Oct/15/old.txt', $cutoff - 10);
        $oldOutsideArchive = $this->createFile('domain.example/not-archive/2025/Oct/15/old.wav', $cutoff - 10);
        $oldDomainRootWav = $this->createFile('domain.example/uploaded_greeting_20250318_185444.wav', $cutoff - 10);

        $method = new ReflectionMethod(DeleteOldCallRecordings::class, 'deleteOldRecordingFiles');
        $method->setAccessible(true);
        $method->invoke(new DeleteOldCallRecordings(), $this->basePath . DIRECTORY_SEPARATOR, $cutoff);

        $this->assertFileDoesNotExist($oldNestedWav);
        $this->assertFileDoesNotExist($oldNestedMp3);
        $this->assertFileDoesNotExist($oldDirectWav);
        $this->assertFileExists($recentNestedWav);
        $this->assertFileExists($oldTextFile);
        $this->assertFileExists($oldOutsideArchive);
        $this->assertFileExists($oldDomainRootWav);
    }

    private function createFile(string $relativePath, int $mtime): string
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        touch($path, $mtime);

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
