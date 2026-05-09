<?php

namespace App\Services;

use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MusicOnHoldService
{
    private const APP_UUID = '1dafe0f8-c08a-289b-0312-15baf4f20f81';
    private const VALID_EXTENSIONS = ['wav', 'mp3', 'ogg'];

    public function save(array $validated, ?MusicOnHold $musicOnHold = null): MusicOnHold
    {
        return DB::transaction(function () use ($validated, $musicOnHold) {
            $musicOnHold ??= new MusicOnHold();
            $isNew = ! $musicOnHold->exists;

            $musicOnHold->forceFill([
                'music_on_hold_uuid' => $musicOnHold->music_on_hold_uuid ?: (string) Str::uuid(),
                'domain_uuid' => $this->authorizedDomainUuid($validated['domain_uuid'] ?? null),
                'music_on_hold_name' => $validated['music_on_hold_name'],
                'music_on_hold_path' => $validated['music_on_hold_path'],
                'music_on_hold_rate' => $validated['music_on_hold_rate'] ?? null,
                'music_on_hold_shuffle' => $validated['music_on_hold_shuffle'],
                'music_on_hold_channels' => $validated['music_on_hold_channels'],
                'music_on_hold_interval' => $validated['music_on_hold_interval'] ?? null,
                'music_on_hold_timer_name' => $validated['music_on_hold_timer_name'] ?? 'soft',
                'music_on_hold_chime_list' => $validated['music_on_hold_chime_list'] ?? null,
                'music_on_hold_chime_freq' => $validated['music_on_hold_chime_freq'] ?? null,
                'music_on_hold_chime_max' => $validated['music_on_hold_chime_max'] ?? null,
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $this->refreshRuntime();

            return $musicOnHold;
        });
    }

    public function upload(array $validated, UploadedFile $file): MusicOnHold
    {
        $stream = ! empty($validated['music_on_hold_uuid'])
            ? $this->scopedQuery()->whereKey($validated['music_on_hold_uuid'])->firstOrFail()
            : $this->findOrCreateUploadStream($validated);

        $targetPath = $this->resolvedStreamPath($stream);
        File::ensureDirectoryExists($targetPath, 0770, true);

        $file->move($targetPath, $this->safeFileName($file->getClientOriginalName()));

        $this->refreshRuntime();

        return $stream;
    }

    public function deleteStreams(Collection $streams): int
    {
        $deleted = 0;

        DB::transaction(function () use ($streams, &$deleted) {
            foreach ($streams as $stream) {
                $streamPath = $this->resolvedStreamPath($stream);
                $stream->delete();
                $deleted++;

                @rmdir($streamPath);
                $namePath = dirname($streamPath);
                if (is_dir($namePath) && count(scandir($namePath) ?: []) === 2) {
                    @rmdir($namePath);
                }
            }
        });

        if ($deleted > 0) {
            $this->refreshRuntime();
        }

        return $deleted;
    }

    public function deleteFile(MusicOnHold $stream, string $fileName): bool
    {
        $filePath = $this->streamFilePath($stream, $fileName);

        if (! $filePath || ! File::exists($filePath)) {
            return false;
        }

        $deleted = File::delete($filePath);

        if ($deleted) {
            $this->refreshRuntime();
        }

        return $deleted;
    }

    public function streamFilePath(MusicOnHold $stream, string $fileName): ?string
    {
        $fileName = $this->safeFileName($fileName);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::VALID_EXTENSIONS, true)) {
            return null;
        }

        $basePath = $this->resolvedStreamPath($stream);
        $path = $basePath . DIRECTORY_SEPARATOR . $fileName;
        $realBase = realpath($basePath);
        $realPath = realpath($path);

        if (! $realBase || ! $realPath || ! str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    public function resolvedStreamPath(MusicOnHold $stream): string
    {
        return str_replace('$${sounds_dir}', $this->soundsRoot(), (string) $stream->music_on_hold_path);
    }

    public function fileRows(MusicOnHold $stream): array
    {
        $path = $this->resolvedStreamPath($stream);

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (self::VALID_EXTENSIONS as $extension) {
            $files = array_merge($files, glob($path . '/*.' . $extension) ?: []);
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return array_map(function (string $filePath) use ($stream) {
            $fileName = basename($filePath);

            return [
                'name' => $fileName,
                'size' => filesize($filePath) ?: 0,
                'size_label' => $this->bytesForHumans(filesize($filePath) ?: 0),
                'modified_at' => date('Y-m-d H:i:s', filemtime($filePath) ?: time()),
                'mime_type' => $this->mimeType($fileName),
                'download_url' => route('music-on-hold.files.download', [
                    'music_on_hold' => $stream->music_on_hold_uuid,
                    'file' => $fileName,
                ]),
            ];
        }, $files);
    }

    public function accessibleDomainUuids(): array
    {
        $domains = session('domains', []);

        if (is_array($domains) && ! empty($domains)) {
            return collect($domains)
                ->pluck('domain_uuid')
                ->filter()
                ->values()
                ->all();
        }

        return array_filter([session('domain_uuid')]);
    }

    public function authorizedDomainUuid(?string $domainUuid): ?string
    {
        if (! userCheckPermission('music_on_hold_domain')) {
            return session('domain_uuid');
        }

        if ($domainUuid === null || $domainUuid === '') {
            return null;
        }

        abort_unless(in_array($domainUuid, $this->accessibleDomainUuids(), true), 403);

        return $domainUuid;
    }

    public function scopedQuery()
    {
        return MusicOnHold::query()
            ->with(['domain:domain_uuid,domain_name,domain_description'])
            ->when(! userCheckPermission('music_on_hold_all'), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                });
            })
            ->when(userCheckPermission('music_on_hold_all'), function ($query) {
                $query->where(function ($query) {
                    $query->whereIn('domain_uuid', $this->accessibleDomainUuids())
                        ->orWhereNull('domain_uuid');
                });
            });
    }

    public function refreshRuntime(): void
    {
        FusionCache::clear('configuration:local_stream.conf');
        app(FreeswitchEslService::class)->executeCommand('reload mod_local_stream');
    }

    private function findOrCreateUploadStream(array $validated): MusicOnHold
    {
        $domainUuid = $this->authorizedDomainUuid($validated['domain_uuid'] ?? null);
        $name = $this->safeCategoryName($validated['music_on_hold_name']);
        $rate = $validated['music_on_hold_rate'] ?? null;
        $pathRate = $rate ?: '48000';
        $path = $this->uploadPath($domainUuid, $name, $pathRate);

        $existing = MusicOnHold::query()
            ->where('music_on_hold_path', $path)
            ->first();

        if ($existing) {
            return $existing;
        }

        $stream = new MusicOnHold();
        $stream->forceFill([
            'music_on_hold_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'music_on_hold_name' => $name,
            'music_on_hold_path' => $path,
            'music_on_hold_rate' => $rate,
            'music_on_hold_shuffle' => 'false',
            'music_on_hold_channels' => 1,
            'music_on_hold_interval' => 20,
            'music_on_hold_timer_name' => 'soft',
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ])->save();

        return $stream;
    }

    private function uploadPath(?string $domainUuid, string $name, string $rate): string
    {
        $domainName = $domainUuid
            ? data_get(collect(session('domains', []))->firstWhere('domain_uuid', $domainUuid), 'domain_name', session('domain_name'))
            : 'global';

        $path = $this->soundsRoot() . '/music/' . $domainName . '/' . $name . '/' . $rate;

        return str_replace('.loc', '._loc', $path);
    }

    private function safeCategoryName(string $name): string
    {
        $name = str_replace(['/', '\\'], '', $name);
        $name = str_replace(' ', '_', $name);

        return trim($name);
    }

    private function safeFileName(string $fileName): string
    {
        $fileName = basename($fileName);
        $fileName = str_replace(['..', '/', '\\', ':'], '', $fileName);

        return str_replace(' ', '-', $fileName);
    }

    private function soundsRoot(): string
    {
        return rtrim(config('filesystems.disks.sounds.root', '/usr/share/freeswitch/sounds'), '/');
    }

    private function bytesForHumans(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }

    private function mimeType(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }
}
