<?php

namespace Tests\Unit;

use App\Models\CDR;
use App\Services\S3RecordingArchiver;
use App\Services\S3StorageConfigService;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class S3RecordingArchiverTest extends TestCase
{
    private string $directory;
    private string $originalConnection;
    private S3RecordingArchiver $archiver;
    private array $objects = [];
    private int $puts = 0;
    private $afterPut = null;
    private bool $corrupt = false;
    private bool $headFailure = false;
    private int $missingStatus = 404;
    private array $settings = ['bucket' => 'recordings', 'type' => 'default'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config(['database.default' => 's3_archive_test', 'database.connections.s3_archive_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('s3_archive_test');
        Schema::create('v_xml_cdr', function (Blueprint $table) {
            $table->uuid('xml_cdr_uuid')->primary();
            foreach (['domain_uuid', 'domain_name', 'record_path', 'record_name', 'direction',
                'caller_id_number', 'caller_destination', 'start_stamp'] as $field) {
                $table->string($field)->nullable();
            }
        });
        $this->directory = sys_get_temp_dir().'/fspbx-s3-test-'.Str::uuid();
        mkdir($this->directory, 0700);
        $s3 = new S3Client([
            'version' => 'latest', 'region' => 'us-east-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => function ($command) {
                $key = $command['Key'];
                if ($command->getName() === 'PutObject') {
                    $this->puts++;
                    $body = (string) $command['Body'];
                    $this->assertSame(base64_encode(md5($body, true)), $command['ContentMD5']);
                    $this->objects[$key] = ['ContentLength' => strlen($body), 'Metadata' => $command['Metadata']];
                    if ($this->corrupt) {
                        $this->objects[$key]['ContentLength']++;
                    }
                    if ($this->afterPut) { ($this->afterPut)(); }
                    return Create::promiseFor(new Result());
                }
                if ($this->headFailure && isset($this->objects[$key])) {
                    throw new RuntimeException('Temporary verification failure.');
                }
                if (! isset($this->objects[$key])) {
                    throw new AwsException('Not found', $command, [
                        'code' => 'NotFound', 'response' => new \GuzzleHttp\Psr7\Response($this->missingStatus),
                    ]);
                }
                return Create::promiseFor(new Result($this->objects[$key]));
            },
        ]);
        $storage = Mockery::mock(S3StorageConfigService::class);
        $storage->shouldReceive('buildClientFromSettings')->andReturn($s3);
        $storage->shouldReceive('getSettingsHash')->andReturn('test-storage');
        $this->archiver = new class($storage, $this->directory) extends S3RecordingArchiver {
            public bool $failDelete = false;
            public function __construct(S3StorageConfigService $storage, private string $directory)
            { parent::__construct($storage); }
            protected function receiptDirectory(): string { return $this->directory.'/receipts'; }
            protected function deleteSource(string $source): bool
            { return $this->failDelete ? false : parent::deleteSource($source); }
        };
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        DB::disconnect('s3_archive_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_upload_updates_all_shared_file_references_and_removes_source_after_verification(): void
    {
        $record = $this->record();
        $sibling = $this->record();
        $key = $this->archive($record);
        $this->assertSame('S3', $record->fresh()->record_path);
        $this->assertSame($key, $sibling->fresh()->record_name);
        $this->assertFileDoesNotExist($this->directory.'/call.mp3');
        $this->assertNull($this->archive($sibling));
        $this->assertSame(1, $this->puts);
        $this->assertSame([], iterator_to_array($this->archiver->receipts()));
    }

    public function test_missing_file_keeps_references_and_can_be_archived_after_file_replication(): void
    {
        $record = $this->record();
        unlink($this->directory.'/call.mp3');
        $this->fails(fn () => $this->archive($record), 'deferred');
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->assertSame($this->directory, $record->fresh()->record_path);
        file_put_contents($this->directory.'/call.mp3', 'replicated audio');
        $this->assertNotNull($this->archive($record));
    }

    public function test_lost_put_response_is_recovered_without_a_second_upload(): void
    {
        $record = $this->record();
        $this->afterPut = fn () => throw new RuntimeException('Lost response.');
        $this->fails(fn () => $this->archive($record), 'Lost response');
        $this->assertFileExists($this->directory.'/call.mp3');
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->afterPut = null;
        $this->archive($record);
        $this->assertSame(1, $this->puts);
    }

    public function test_verification_failure_retains_source_and_retry_reuses_existing_object(): void
    {
        $record = $this->record();
        $this->headFailure = true;
        $this->fails(fn () => $this->archive($record), 'verification failure');
        $this->assertSame($this->directory, $record->fresh()->record_path);
        $this->assertFileExists($this->directory.'/call.mp3');
        $this->headFailure = false;
        $this->archive($record);
        $this->assertSame(1, $this->puts);
    }

    public function test_wrong_remote_size_is_never_committed_or_overwritten_on_retry(): void
    {
        $record = $this->record();
        $this->corrupt = true;
        $this->fails(fn () => $this->archive($record), 'verification failed');
        $this->fails(fn () => $this->archive($record), 'verification failed');
        $this->assertSame(1, $this->puts);
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->assertFileExists($this->directory.'/call.mp3');
    }

    public function test_existing_bucket_permissions_without_list_bucket_still_allow_uploads(): void
    {
        $this->missingStatus = 403;
        $record = $this->record();
        $this->assertNotNull($this->archive($record));
        $this->assertSame('S3', $record->fresh()->record_path);
    }

    public function test_wrong_remote_checksum_with_correct_size_is_rejected(): void
    {
        $record = $this->record();
        $this->afterPut = function () {
            foreach ($this->objects as &$object) {
                $object['Metadata']['fspbx-sha256'] = str_repeat('0', 64);
            }
        };
        $this->fails(fn () => $this->archive($record), 'verification failed');
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->assertFileExists($this->directory.'/call.mp3');
    }

    public function test_ownership_change_during_upload_preserves_source_and_database_references(): void
    {
        $record = $this->record();
        $authorized = true;
        $this->afterPut = function () use (&$authorized) { $authorized = false; };
        $authorize = function () use (&$authorized) {
            if (! $authorized) { throw new RuntimeException('Ownership revoked.'); }
        };
        $this->fails(fn () => $this->archiver->archive($record, $this->settings, 'UTC', $authorize,
            fn ($write) => DB::transaction($write)), 'Ownership revoked');
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->assertFileExists($this->directory.'/call.mp3');
    }

    public function test_rejected_database_commit_cannot_authorize_receipt_cleanup(): void
    {
        $record = $this->record();
        $this->fails(fn () => $this->archiver->archive($record, $this->settings, 'UTC', fn () => null,
            fn ($write) => DB::transaction(function () use ($write) {
                $write();
                throw new RuntimeException('Claim expired before commit.');
            })), 'Claim expired');
        $this->recoverCleanup();
        $this->assertFileExists($this->directory.'/call.mp3');
        $this->assertSame('call.mp3', $record->fresh()->record_name);
        $this->archive($record);
        $this->assertSame(1, $this->puts);
    }

    public function test_failed_cleanup_is_recovered_from_receipt_after_cdr_is_already_on_s3(): void
    {
        $record = $this->record();
        $this->archiver->failDelete = true;
        $this->fails(fn () => $this->archive($record), 'cleanup failed');
        $this->assertSame('S3', $record->fresh()->record_path);
        $this->assertCount(1, iterator_to_array($this->archiver->receipts()));
        $this->archiver->failDelete = false;
        $this->recoverCleanup();
        $this->assertFileDoesNotExist($this->directory.'/call.mp3');
        $this->assertSame([], iterator_to_array($this->archiver->receipts()));
        $this->assertSame(1, $this->puts);
    }

    public function test_changed_source_is_not_deleted_by_a_cleanup_receipt(): void
    {
        $record = $this->record();
        $this->archiver->failDelete = true;
        $this->fails(fn () => $this->archive($record), 'cleanup failed');
        file_put_contents($this->directory.'/call.mp3', 'replacement recording');
        $this->archiver->failDelete = false;
        $this->fails(fn () => $this->recoverCleanup(), 'changed');
        $this->assertSame('replacement recording', file_get_contents($this->directory.'/call.mp3'));
    }

    public function test_existing_filename_format_is_preserved(): void
    {
        $this->assertSame('example.test/2026/10/01/120000_inbound_1001_1002.mp3', $this->archive($this->record()));
        $this->settings['type'] = 'custom';
        $this->assertSame('recordings/2026/10/01/120000_inbound_1001_1002.mp3', $this->archive($this->record('other.mp3')));
    }

    public function test_existing_name_collision_with_different_content_is_reported_without_overwriting(): void
    {
        $one = $this->record();
        $two = $this->record('other.mp3');
        file_put_contents($this->directory.'/other.mp3', 'different audio');
        $this->archive($one);
        $this->fails(fn () => $this->archive($two), 'verification failed');
        $this->assertSame(1, $this->puts);
        $this->assertSame('other.mp3', $two->fresh()->record_name);
        $this->assertFileExists($this->directory.'/other.mp3');
    }

    public function test_other_tenant_sharing_a_path_is_not_updated_or_left_without_a_source(): void
    {
        $record = $this->record();
        $other = $this->record();
        DB::table('v_xml_cdr')->where('xml_cdr_uuid', $other->getKey())->update(['domain_uuid' => 'other-account']);
        $this->archive($record);
        $this->assertSame($this->directory, $other->fresh()->record_path);
        $this->assertFileExists($this->directory.'/call.mp3');
        $this->archive($other);
        $this->recoverCleanup();
        $this->assertFileDoesNotExist($this->directory.'/call.mp3');
        $this->assertSame(1, $this->puts);
    }

    public function test_wav_conversion_does_not_overwrite_an_existing_sibling_mp3(): void
    {
        $record = $this->record('call.wav');
        $samples = str_repeat(pack('v', 0), 8000);
        file_put_contents($this->directory.'/call.wav', 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples);
        file_put_contents($this->directory.'/call.mp3', 'unrelated sibling');
        $key = $this->archive($record);
        $this->assertStringEndsWith('.mp3', $key);
        $this->assertSame('unrelated sibling', file_get_contents($this->directory.'/call.mp3'));
        $this->assertFileDoesNotExist($this->directory.'/call.wav');
    }

    private function record(string $name = 'call.mp3'): CDR
    {
        file_put_contents($this->directory.'/'.$name, 'test recording bytes');
        $id = (string) Str::uuid();
        DB::table('v_xml_cdr')->insert([
            'xml_cdr_uuid' => $id, 'domain_uuid' => 'account', 'domain_name' => 'example.test',
            'record_path' => $this->directory, 'record_name' => $name, 'direction' => 'inbound',
            'caller_id_number' => '1001', 'caller_destination' => '1002', 'start_stamp' => '2026-10-01 12:00:00',
        ]);
        return CDR::findOrFail($id);
    }

    private function archive(CDR $record): ?string
    {
        return $this->archiver->archive($record, $this->settings, 'UTC', fn () => null, fn ($write) => DB::transaction($write));
    }

    private function recoverCleanup(): void
    {
        foreach ($this->archiver->receipts() as $path => $receipt) {
            $this->archiver->cleanup($path, $receipt, $this->settings, fn () => null);
        }
    }

    private function fails(callable $work, string $message): void
    {
        try {
            $work();
            $this->fail('Expected failure: '.$message);
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
}
