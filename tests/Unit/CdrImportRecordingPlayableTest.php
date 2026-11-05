<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CdrImportRecordingPlayableTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../../app/Console/Daemons/cdr_import.php';
        $this->dir = sys_get_temp_dir().'/cdr_import_recording_test_'.bin2hex(random_bytes(8));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_header_only_wav_is_not_treated_as_playable(): void
    {
        // A header-only RIFF/WAV container is exactly 44 bytes -- this is what
        // FreeSWITCH can leave behind for a failed/cancelled/unanswered call.
        $name = 'header-only.wav';
        file_put_contents($this->dir.'/'.$name, str_repeat("\0", 44));

        $this->assertFalse(\cdr_import::recording_is_playable($this->dir, $name));
    }

    public function test_wav_with_audio_payload_is_playable(): void
    {
        $name = 'with-audio.wav';
        file_put_contents($this->dir.'/'.$name, str_repeat("\0", 44).str_repeat("\x01", 100));

        $this->assertTrue(\cdr_import::recording_is_playable($this->dir, $name));
    }

    public function test_missing_file_is_not_playable(): void
    {
        $this->assertFalse(\cdr_import::recording_is_playable($this->dir, 'does-not-exist.wav'));
    }

    public function test_empty_file_is_not_playable(): void
    {
        $name = 'empty.wav';
        file_put_contents($this->dir.'/'.$name, '');

        $this->assertFalse(\cdr_import::recording_is_playable($this->dir, $name));
    }
}
