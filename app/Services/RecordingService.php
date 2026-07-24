<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Recordings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RecordingService
{
    private function query(string $domainUuid): Builder
    {
        return Recordings::query()->where('domain_uuid', $domainUuid);
    }

    public function list(
        string $domainUuid,
        ?int $limit = null,
        ?string $startingAfter = null,
        string $orderBy = 'recording_uuid'
    ): Collection {
        $query = $this->query($domainUuid)->orderBy($orderBy);

        if ($startingAfter) {
            $query->where('recording_uuid', '>', $startingAfter);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function find(string $domainUuid, string $recordingUuid): ?Recordings
    {
        return $this->query($domainUuid)->whereKey($recordingUuid)->first();
    }

    public function create(
        Domain $domain,
        string $name,
        ?string $description,
        UploadedFile $audio,
        ?string $userUuid = null
    ): array {
        $recording = new Recordings();
        $recording->recording_uuid = (string) Str::uuid();
        $recording->domain_uuid = $domain->domain_uuid;
        $recording->recording_filename = 'uploaded_'.Str::random(40).'.wav';
        $recording->recording_name = $name;
        $recording->recording_description = $description;
        $recording->insert_date = now();
        $recording->insert_user = $userUuid;

        $result = $this->updateAudio($recording, $domain, $audio);

        try {
            $recording->save();
        } catch (Throwable $e) {
            Storage::disk('recordings')->delete($domain->domain_name.'/'.$recording->recording_filename);
            throw $e;
        }

        return ['recording' => $recording, 'audio' => $result];
    }

    public function update(
        Recordings $recording,
        Domain $domain,
        array $attributes,
        ?UploadedFile $audio = null,
        ?string $userUuid = null
    ): array {
        $result = null;

        if ($audio) {
            $result = $this->updateAudio($recording, $domain, $audio);
        }

        if (array_key_exists('recording_name', $attributes)) {
            $recording->recording_name = $attributes['recording_name'];
        }
        if (array_key_exists('recording_description', $attributes)) {
            $recording->recording_description = $attributes['recording_description'];
        }

        if ($recording->isDirty()) {
            $recording->update_date = now();
            $recording->update_user = $userUuid;
            $recording->save();
        }

        return ['recording' => $recording->fresh(), 'audio' => $result];
    }

    public function delete(Recordings $recording, Domain $domain): bool
    {
        $path = $domain->domain_name.'/'.$recording->recording_filename;
        $deleted = (bool) $recording->delete();

        if ($deleted) {
            Storage::disk('recordings')->delete($path);
        }

        return $deleted;
    }

    public function audioDetails(Recordings $recording, Domain $domain): ?array
    {
        $disk = Storage::disk('recordings');
        $path = $domain->domain_name.'/'.$recording->recording_filename;

        if (! $disk->exists($path)) {
            return null;
        }

        $absolutePath = $disk->path($path);

        return [
            'bytes' => (int) $disk->size($path),
            'sha256' => (string) hash_file('sha256', $absolutePath),
        ];
    }

    public function audioExists(Recordings $recording, Domain $domain): bool
    {
        return Storage::disk('recordings')->exists($domain->domain_name.'/'.$recording->recording_filename);
    }

    /** @return array{bytes:int, sha256:string} */
    private function updateAudio(Recordings $recording, Domain $domain, UploadedFile $upload): array
    {
        $disk = Storage::disk('recordings');
        $relativePath = $domain->domain_name.'/'.$recording->recording_filename;
        $targetPath = $disk->path($relativePath);
        $directory = dirname($targetPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the recording directory.');
        }

        $temporaryPath = $directory.'/.'.basename($targetPath).'.'.bin2hex(random_bytes(8)).'.tmp.wav';

        try {
            $result = Process::timeout(120)->run([
                'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
                '-i', $upload->getRealPath(), '-vn', '-acodec', 'pcm_s16le',
                '-ac', '1', '-ar', '16000', $temporaryPath,
            ]);

            if (! $result->successful() || ! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                throw new RuntimeException('The uploaded audio could not be converted to a valid recording.');
            }

            if (! rename($temporaryPath, $targetPath)) {
                throw new RuntimeException('The recording could not be updated.');
            }

            clearstatcache(true, $targetPath);

            return [
                'bytes' => (int) filesize($targetPath),
                'sha256' => (string) hash_file('sha256', $targetPath),
            ];
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
