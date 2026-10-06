<?php

namespace Tests\Unit;

use App\Console\Commands\UploadCallRecordingsToS3Storage;
use ReflectionMethod;
use Tests\TestCase;

class UploadCallRecordingsToS3StorageTest extends TestCase
{
    /** @dataProvider commandModes */
    public function test_plain_cli_remains_manual_and_only_the_scheduled_flag_enforces_selection(array $arguments, bool $scheduled): void
    {
        $command = new class(
            \Mockery::mock(\App\Services\S3StorageConfigService::class),
            \Mockery::mock(\App\Services\S3UploadServerSelector::class),
            \Mockery::mock(\App\Services\Ha\ActiveNodeResolver::class),
            \Mockery::mock(\App\Services\S3RecordingArchiver::class),
        ) extends UploadCallRecordingsToS3Storage {
            public ?bool $observedScheduled = null;
            public function uploadRecordings(bool $scheduled = false) { $this->observedScheduled = $scheduled; }
        };
        $command->setLaravel($this->app);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $this->assertSame(0, $tester->execute($arguments));
        $this->assertSame($scheduled, $command->observedScheduled);
    }

    public static function commandModes(): array
    {
        return ['manual' => [[], false], 'scheduled' => [['--scheduled' => true], true]];
    }

    /**
     * Compiled Contact Center hold audio lives on the recordings disk. The
     * uploader converts a wav, deletes the local copy and clears the CDR
     * pointer, so it must never treat those segments as call recordings.
     */
    public function test_contact_center_hold_audio_is_not_treated_as_a_call_recording(): void
    {
        config(['filesystems.disks.recordings.root' => '/var/lib/freeswitch/recordings']);
        $command = app(UploadCallRecordingsToS3Storage::class);
        $method = new ReflectionMethod(UploadCallRecordingsToS3Storage::class, 'isManagedQueueAudio');
        $method->setAccessible(true);
        $isManaged = fn (string $path) => $method->invoke($command, $path);
        $root = '/var/lib/freeswitch/recordings/';

        $this->assertTrue($isManaged($root.'example.test/contact-center-audio/'.
            'eedc0a4e-d342-4cfc-899a-6977cd1dc509/segments/707155e7/8000.wav'));
        $this->assertTrue($isManaged($root.'example.test/contact-center-audio/queue/revisions/abc.json'));

        // Ordinary call recordings must still be uploaded.
        $this->assertFalse($isManaged($root.'example.test/archive/2026/09/22/call.wav'));
        $this->assertFalse($isManaged($root.'example.test/greeting.wav'));

        // An account named after the directory records into its own archive.
        $this->assertFalse($isManaged($root.'contact-center-audio/archive/2026/09/22/call.wav'));

        // Paths outside the recordings disk are none of this guard's business.
        $this->assertFalse($isManaged('/var/spool/freeswitch/contact-center-audio/x/8000.wav'));
    }
}
