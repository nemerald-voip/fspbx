<?php

namespace App\Services;

use App\Models\CDR;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class S3RecordingArchiver
{
    public function __construct(private readonly S3StorageConfigService $storage) {}

    public function archive(CDR $recording, array $settings, string $timeZone, Closure $authorize, Closure $write): ?string
    {
        $authorize();
        $recording->refresh();
        if (! $recording->record_path || ! $recording->record_name
            || in_array($recording->record_path, ['S3', 'NFS'], true)) {
            return null;
        }

        $source = rtrim($recording->record_path, '/').'/'.$recording->record_name;
        if (! is_file($source)) {
            throw new RuntimeException('Recording file not found locally; deferred until a later run.');
        }

        // Each attempt owns its files. Never overwrite a sibling MP3 or upload a
        // live source that another process could change during the request.
        $snapshot = tempnam(sys_get_temp_dir(), 'fspbx-s3-');
        $converted = null;
        try {
            if ($snapshot === false || ! copy($source, $snapshot)) {
                throw new RuntimeException('Could not snapshot the recording.');
            }
            $sourceHash = hash_file('sha256', $snapshot);
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            $upload = $snapshot;
            if (strtolower($extension) === 'wav') {
                $converted = tempnam(sys_get_temp_dir(), 'fspbx-s3-mp3-');
                if ($converted === false) {
                    throw new RuntimeException('Could not create the MP3 conversion file.');
                }
                $this->convert($snapshot, $converted);
                $upload = $converted;
                $extension = 'mp3';
            }

            $checksum = hash_file('sha256', $upload);
            $size = filesize($upload);
            if (! $checksum || ! $sourceHash || ! $size) {
                throw new RuntimeException('The recording is empty or unreadable.');
            }
            $key = $this->objectKey($recording, $settings, $timeZone, $extension);
            $s3 = $this->storage->buildClientFromSettings($settings);
            $authorize();
            if (! $this->verified($s3, $settings['bucket'], $key, $size, $checksum, true)) {
                $s3->putObject([
                    'Bucket' => $settings['bucket'], 'Key' => $key, 'SourceFile' => $upload,
                    'ContentMD5' => base64_encode(hash_file('md5', $upload, true)),
                    'Metadata' => ['fspbx-sha256' => $checksum],
                    '@http' => ['connect_timeout' => 10, 'timeout' => 120],
                    '@retries' => 0,
                ]);
                if (! $this->verified($s3, $settings['bucket'], $key, $size, $checksum)) {
                    throw new RuntimeException('The uploaded recording could not be verified.');
                }
            }

            $authorize();
            if (! is_file($source) || hash_file('sha256', $source) !== $sourceHash) {
                throw new RuntimeException('The local recording changed during upload; retry deferred.');
            }
            $receipt = [
                'domain_uuid' => $recording->domain_uuid,
                'record_path' => $recording->record_path, 'record_name' => $recording->record_name,
                'source_hash' => $sourceHash, 'key' => $key, 'size' => $size, 'checksum' => $checksum,
                'storage_hash' => $this->storage->getSettingsHash($settings),
            ];
            // Persist before the database commit, so a crash after commit or a
            // failed unlink can be recovered without uploading again.
            $receiptPath = $this->saveReceipt($receipt);
            $recordingStart = Carbon::parse($recording->start_stamp);
            $updated = $write(fn () => CDR::query()
                ->where('domain_uuid', $recording->domain_uuid)
                ->where('record_path', $recording->record_path)
                ->where('record_name', $recording->record_name)
                ->whereBetween('start_stamp', [
                    $recordingStart->copy()->subDay(),
                    $recordingStart->copy()->addDay(),
                ])
                ->update(['record_path' => 'S3', 'record_name' => $key]));
            if (! $updated) {
                throw new RuntimeException('Recording references changed before the archive could be committed.');
            }
            $this->cleanup($receiptPath, $receipt, $settings, $authorize);

            return $key;
        } finally {
            foreach ([$snapshot, $converted] as $temporary) {
                if ($temporary && is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }

    public function cleanup(string $receiptPath, array $receipt, array $settings, Closure $authorize): void
    {
        $authorize();
        if ($receipt['storage_hash'] !== $this->storage->getSettingsHash($settings)) {
            return;
        }
        // A receipt alone never authorizes deletion: require committed S3
        // references and no remaining local references, including other accounts.
        if (! CDR::query()->where('domain_uuid', $receipt['domain_uuid'])
            ->where('record_path', 'S3')->where('record_name', $receipt['key'])->exists()
            || CDR::query()->where('record_path', $receipt['record_path'])
                ->where('record_name', $receipt['record_name'])->exists()) {
            return;
        }
        $source = rtrim($receipt['record_path'], '/').'/'.$receipt['record_name'];
        if (is_file($source)) {
            $s3 = $this->storage->buildClientFromSettings($settings);
            if (! $this->verified($s3, $settings['bucket'], $receipt['key'], $receipt['size'], $receipt['checksum'])) {
                throw new RuntimeException('Archived recording could not be verified for local cleanup.');
            }
            $authorize();
            if (hash_file('sha256', $source) !== $receipt['source_hash']) {
                throw new RuntimeException('Local recording changed; retained for review.');
            }
            if (! $this->deleteSource($source)) {
                throw new RuntimeException('Local cleanup failed; it will be retried on a later run.');
            }
        }
        if (is_file($receiptPath)) {
            unlink($receiptPath);
        }
    }

    public function receipts(): iterable
    {
        if (! is_dir($this->receiptDirectory())) {
            return;
        }
        $count = 0;
        foreach (new \DirectoryIterator($this->receiptDirectory()) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }
            $receipt = json_decode(file_get_contents($file->getPathname()), true);
            if (is_array($receipt) && count(array_intersect([
                'domain_uuid', 'record_path', 'record_name', 'source_hash', 'key', 'size', 'checksum', 'storage_hash',
            ], array_keys($receipt))) === 8) {
                yield $file->getPathname() => $receipt;
            }
            if (++$count >= 2000) {
                break;
            }
        }
    }

    protected function receiptDirectory(): string
    {
        return storage_path('app/s3-upload-cleanup');
    }

    protected function saveReceipt(array $receipt): string
    {
        File::ensureDirectoryExists($this->receiptDirectory(), 0700);
        $path = $this->receiptDirectory().'/'.hash('sha256', json_encode($receipt)).'.json';
        $temporary = tempnam($this->receiptDirectory(), '.receipt-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create an archive cleanup receipt.');
        }
        try {
            if (file_put_contents($temporary, json_encode($receipt, JSON_THROW_ON_ERROR)) === false
                || ! rename($temporary, $path)) {
                throw new RuntimeException('Could not persist the archive cleanup receipt.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $path;
    }

    protected function deleteSource(string $source): bool
    {
        return unlink($source);
    }

    protected function convert(string $source, string $destination): void
    {
        $process = new Process(['ffmpeg', '-nostdin', '-y', '-i', $source,
            '-b:a', '16k', '-ac', '1', '-q:a', '5', '-f', 'mp3', $destination]);
        $process->setTimeout(600);
        $process->mustRun();
    }

    protected function verified(S3Client $s3, string $bucket, string $key, int $size, string $checksum, bool $beforeUpload = false): bool
    {
        try {
            $head = $s3->headObject([
                'Bucket' => $bucket, 'Key' => $key,
                '@http' => ['connect_timeout' => 10, 'timeout' => 30], '@retries' => 0,
            ]);
        } catch (AwsException $exception) {
            // Without ListBucket permission S3 reports a missing key as 403.
            // Verification after upload must still succeed with the existing
            // GetObject permission.
            if ($exception->getStatusCode() === 404 || ($beforeUpload && $exception->getStatusCode() === 403)) {
                return false;
            }
            throw $exception;
        }
        if ((int) ($head['ContentLength'] ?? -1) !== $size
            || ($head['Metadata']['fspbx-sha256'] ?? null) !== $checksum) {
            // Do not overwrite an unexpected object, even on a retry.
            throw new RuntimeException('S3 object verification failed; the existing object was left unchanged.');
        }

        return true;
    }

    protected function objectKey(CDR $recording, array $settings, string $timeZone, string $extension): string
    {
        $start = Carbon::parse($recording->start_stamp)->setTimezone($timeZone);
        $base = ($settings['type'] ?? 'default') === 'default' ? $recording->domain_name : 'recordings';
        $label = collect([$recording->direction, $recording->caller_id_number, $recording->caller_destination])
            ->map(fn ($value) => trim(preg_replace('/[^\w\-\+\.]/', '_', (string) $value), '_') ?: 'unknown')
            ->implode('_');
        // Preserve the established archive filename and folder layout.
        return $base.'/'.$start->format('Y/m/d/His').'_'.$label.'.'.$extension;
    }
}
