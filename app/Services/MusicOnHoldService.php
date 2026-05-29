<?php

namespace App\Services;

use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MusicOnHoldService
{
    private const APP_UUID = '1dafe0f8-c08a-289b-0312-15baf4f20f81';
    private const DEFAULT_RATE = '16000';
    private const OUTPUT_RATES = ['8000', '16000'];
    private const KNOWN_RATES = ['8000', '16000', '32000', '48000'];
    private const VALID_EXTENSIONS = ['wav', 'mp3', 'ogg'];

    public function save(array $validated, ?MusicOnHold $musicOnHold = null): MusicOnHold
    {
        return DB::transaction(function () use ($validated, $musicOnHold) {
            $musicOnHold ??= new MusicOnHold();
            $domainUuid = $this->saveDomainUuid($musicOnHold, $validated['domain_uuid'] ?? null);
            $name = $this->safeCategoryName($validated['music_on_hold_name']);
            $familyPath = $this->defaultStreamPath($domainUuid, $name);
            $existingFamily = $musicOnHold->exists ? $this->streamFamily($musicOnHold) : collect();
            $representative = null;

            foreach (self::OUTPUT_RATES as $rate) {
                $stream = $existingFamily->firstWhere('music_on_hold_rate', $rate)
                    ?? ($rate === self::DEFAULT_RATE ? $this->defaultRateStream($existingFamily) : null)
                    ?? $this->findExistingFamilyStreamForRate($domainUuid, $name, $rate, $familyPath)
                    ?? new MusicOnHold();

                $isNew = ! $stream->exists;
                $stream->forceFill([
                    'music_on_hold_uuid' => $stream->music_on_hold_uuid ?: (string) Str::uuid(),
                    'domain_uuid' => $domainUuid,
                    'music_on_hold_name' => $name,
                    'music_on_hold_path' => $this->ratePath($familyPath, $rate),
                    'music_on_hold_rate' => $rate,
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

                if ($rate === self::DEFAULT_RATE) {
                    $representative = $stream;
                }
            }

            $this->refreshRuntime();

            return $representative ?? $musicOnHold;
        });
    }

    public function upload(array $validated, UploadedFile $file): MusicOnHold
    {
        if (! empty($validated['music_on_hold_uuid'])) {
            $stream = $this->scopedQuery()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($validated['music_on_hold_uuid'])
                ->firstOrFail();
            $streams = $this->uploadTargetStreams($stream);
        } else {
            $streams = $this->findOrCreateUploadStreams($validated);
            $stream = $streams->firstWhere('music_on_hold_rate', self::DEFAULT_RATE) ?? $streams->first();
        }

        $fileName = $this->safeConvertedFileName($file->getClientOriginalName());
        $sourcePath = $file->getRealPath();
        $writtenFiles = [];

        try {
            foreach ($streams as $targetStream) {
                $targetPath = $this->resolvedStreamPath($targetStream);
                File::ensureDirectoryExists($targetPath, 0770, true);

                $targetFile = $targetPath . DIRECTORY_SEPARATOR . $fileName;
                $this->convertUpload(
                    $sourcePath,
                    $targetFile,
                    (string) ($targetStream->music_on_hold_rate ?: self::DEFAULT_RATE)
                );
                $writtenFiles[] = $targetFile;
            }
        } catch (ValidationException $exception) {
            File::delete($writtenFiles);
            throw $exception;
        }

        $this->refreshRuntime();

        return $stream;
    }

    public function deleteStreams(Collection $streams): int
    {
        $deleted = 0;
        $directoriesToDelete = collect();
        $parentPaths = collect();

        DB::transaction(function () use ($streams, &$deleted, $directoriesToDelete, $parentPaths) {
            foreach ($this->streamFamilies($streams) as $stream) {
                $streamPath = $this->resolvedStreamPath($stream);
                $familyPath = $this->resolvedPath($this->formPath($stream));

                $directoriesToDelete->push($streamPath, $familyPath);
                $parentPaths->push(dirname($familyPath));
                $stream->delete();
                $deleted++;
            }
        });

        $directoriesToDelete
            ->unique()
            ->sortByDesc(fn (string $path) => substr_count($path, DIRECTORY_SEPARATOR))
            ->each(function (string $path) {
                if ($this->isDeletableStreamDirectory($path)) {
                    File::deleteDirectory($path);
                }
            });

        $parentPaths
            ->unique()
            ->sortByDesc(fn (string $path) => substr_count($path, DIRECTORY_SEPARATOR))
            ->each(function (string $path) {
                if (File::isDirectory($path) && count(scandir($path) ?: []) === 2) {
                    @rmdir($path);
                }
            });

        if ($deleted > 0) {
            $this->refreshRuntime();
        }

        return $deleted;
    }

    public function deleteFile(MusicOnHold $stream, string $fileName): bool
    {
        $deleted = false;

        foreach ($this->streamFamily($stream) as $familyStream) {
            $filePath = $this->streamFilePath($familyStream, $fileName);

            if ($filePath && File::exists($filePath)) {
                $deleted = File::delete($filePath) || $deleted;
            }
        }

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
        return $this->resolvedPath((string) $stream->music_on_hold_path);
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

    public function formPath(MusicOnHold $stream): string
    {
        return $this->familyPath((string) $stream->music_on_hold_path);
    }

    public function representativeQuery()
    {
        $representatives = DB::query()
            ->select('music_on_hold_uuid')
            ->fromSub(function ($query) {
                $query->from((new MusicOnHold())->getTable())
                    ->select('music_on_hold_uuid')
                    ->selectRaw(
                        "row_number() over (
                            partition by domain_uuid,
                                music_on_hold_name,
                                regexp_replace(rtrim(music_on_hold_path, '/'), '/(8000|16000|32000|48000)$', '')
                            order by
                                case
                                    when music_on_hold_rate = ? then 0
                                    when music_on_hold_rate is null then 1
                                    when music_on_hold_rate = '8000' then 2
                                    when music_on_hold_rate = '32000' then 3
                                    when music_on_hold_rate = '48000' then 4
                                    else 5
                                end,
                                music_on_hold_rate nulls last,
                                music_on_hold_uuid
                        ) as stream_rank",
                        [self::DEFAULT_RATE]
                    );
            }, 'ranked_music_on_hold')
            ->where('stream_rank', 1);

        return $this->scopedQuery()
            ->whereIn('music_on_hold_uuid', $representatives);
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
        $sessionDomainUuid = session('domain_uuid');

        if ($domainUuid === null || $domainUuid === '') {
            return $sessionDomainUuid;
        }

        abort_unless($domainUuid === $sessionDomainUuid, 403);

        return $domainUuid;
    }

    private function saveDomainUuid(MusicOnHold $musicOnHold, ?string $domainUuid): ?string
    {
        if ($musicOnHold->exists) {
            return $musicOnHold->domain_uuid;
        }

        return $this->authorizedDomainUuid($domainUuid);
    }

    public function defaultStreamPath(?string $domainUuid, string $name): string
    {
        $domainName = $this->domainPathName($domainUuid);
        $pathName = $this->safeCategoryName($name);

        return '$${sounds_dir}/music/' . $domainName . '/' . $pathName;
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
        $this->reloadLocalStream();
    }

    public function reloadLocalStream(): array
    {
        FusionCache::clear('configuration:local_stream.conf');
        $response = app(FreeswitchEslService::class)->executeCommand('reload mod_local_stream');
        $message = is_string($response) ? trim($response) : null;

        if ($message === null || $message === '') {
            return [
                'success' => false,
                'message' => 'No response received from FreeSWITCH.',
            ];
        }

        return [
            'success' => ! str_starts_with($message, '-ERR'),
            'message' => $message,
        ];
    }

    private function findOrCreateUploadStreams(array $validated): Collection
    {
        $domainUuid = $this->authorizedDomainUuid($validated['domain_uuid'] ?? null);
        $name = $this->safeCategoryName($validated['music_on_hold_name']);

        return collect(self::OUTPUT_RATES)
            ->map(fn (string $rate) => $this->findOrCreateUploadStreamForRate($domainUuid, $name, $rate))
            ->values();
    }

    private function findOrCreateUploadStreamForRate(?string $domainUuid, string $name, string $rate): MusicOnHold
    {
        $familyPath = $this->defaultStreamPath($domainUuid, $name);
        $path = $this->ratePath($familyPath, $rate);

        $existing = $this->findExistingFamilyStreamForRate($domainUuid, $name, $rate, $familyPath);

        if ($existing) {
            $updates = [];

            if ((string) $existing->music_on_hold_rate !== $rate) {
                $updates['music_on_hold_rate'] = $rate;
            }

            if ((string) $existing->music_on_hold_path !== $path) {
                $updates['music_on_hold_path'] = $path;
            }

            if (! empty($updates)) {
                $updates['update_date'] = now();
                $updates['update_user'] = session('user_uuid');
                $existing->forceFill($updates)->save();
            }

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

    private function uploadTargetStreams(MusicOnHold $stream): Collection
    {
        $familyPath = $this->formPath($stream);
        $name = $this->safeCategoryName($stream->music_on_hold_name);

        return collect(self::OUTPUT_RATES)
            ->map(function (string $rate) use ($stream, $name, $familyPath) {
                $target = $this->findExistingFamilyStreamForRate($stream->domain_uuid, $name, $rate, $familyPath)
                    ?? new MusicOnHold();

                $isNew = ! $target->exists;
                $target->forceFill([
                    'music_on_hold_uuid' => $target->music_on_hold_uuid ?: (string) Str::uuid(),
                    'domain_uuid' => $stream->domain_uuid,
                    'music_on_hold_name' => $name,
                    'music_on_hold_path' => $this->ratePath($familyPath, $rate),
                    'music_on_hold_rate' => $rate,
                    'music_on_hold_shuffle' => $stream->music_on_hold_shuffle,
                    'music_on_hold_channels' => $stream->music_on_hold_channels,
                    'music_on_hold_interval' => $stream->music_on_hold_interval,
                    'music_on_hold_timer_name' => $stream->music_on_hold_timer_name ?: 'soft',
                    'music_on_hold_chime_list' => $stream->music_on_hold_chime_list,
                    'music_on_hold_chime_freq' => $stream->music_on_hold_chime_freq,
                    'music_on_hold_chime_max' => $stream->music_on_hold_chime_max,
                    $isNew ? 'insert_date' : 'update_date' => now(),
                    $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
                ])->save();

                return $target;
            })
            ->values();
    }

    private function convertUpload(string $sourcePath, string $targetPath, string $rate): void
    {
        $process = Process::run([
            'ffmpeg',
            '-nostdin',
            '-y',
            '-i',
            $sourcePath,
            '-vn',
            '-acodec',
            'pcm_s16le',
            '-ac',
            '1',
            '-ar',
            $rate,
            $targetPath,
        ]);

        if ($process->failed()) {
            File::delete($targetPath);

            throw ValidationException::withMessages([
                'file' => ['Could not convert the uploaded file for FreeSWITCH playback.'],
            ]);
        }
    }

    private function pathAlternates(string $path): array
    {
        return collect([
            $path,
            $this->resolvedPath($path),
        ])->unique()->values()->all();
    }

    private function streamFamily(MusicOnHold $stream): Collection
    {
        $familyPath = $this->formPath($stream);
        $query = MusicOnHold::query()
            ->where('music_on_hold_name', $stream->music_on_hold_name)
            ->where(function ($query) use ($stream) {
                $stream->domain_uuid === null
                    ? $query->whereNull('domain_uuid')
                    : $query->where('domain_uuid', $stream->domain_uuid);
            })
            ->where(function ($query) use ($familyPath) {
                foreach (self::KNOWN_RATES as $rate) {
                    $query->orWhereIn('music_on_hold_path', $this->pathAlternates($this->ratePath($familyPath, $rate)));
                }
            });

        $family = $query->get();

        if (! $family->contains('music_on_hold_uuid', $stream->music_on_hold_uuid)) {
            $family->push($stream);
        }

        return $family;
    }

    private function streamFamilies(Collection $streams): Collection
    {
        return $streams
            ->flatMap(fn (MusicOnHold $stream) => $this->streamFamily($stream))
            ->unique('music_on_hold_uuid')
            ->values();
    }

    private function defaultRateStream(Collection $streams): ?MusicOnHold
    {
        return $streams->first(function (MusicOnHold $stream) {
            return $stream->music_on_hold_rate === null;
        });
    }

    private function findExistingFamilyStreamForRate(?string $domainUuid, string $name, string $rate, string $familyPath): ?MusicOnHold
    {
        $query = MusicOnHold::query()
            ->where(function ($query) use ($domainUuid) {
                $domainUuid === null
                    ? $query->whereNull('domain_uuid')
                    : $query->where('domain_uuid', $domainUuid);
            })
            ->where('music_on_hold_name', $name)
            ->where(function ($query) use ($rate, $familyPath) {
                $query->where('music_on_hold_rate', $rate)
                    ->orWhereIn('music_on_hold_path', $this->pathAlternates($this->ratePath($familyPath, $rate)));
            });

        if ($rate === self::DEFAULT_RATE) {
            $query->orWhere(function ($query) use ($domainUuid, $name, $familyPath) {
                $query->where(function ($query) use ($domainUuid) {
                    $domainUuid === null
                        ? $query->whereNull('domain_uuid')
                        : $query->where('domain_uuid', $domainUuid);
                    })
                    ->where('music_on_hold_name', $name)
                    ->whereNull('music_on_hold_rate')
                    ->whereIn('music_on_hold_path', $this->pathAlternates($this->ratePath($familyPath, self::DEFAULT_RATE)));
            });
        }

        return $query->first();
    }

    private function familyPath(string $path): string
    {
        $path = rtrim($path, '/');

        foreach (self::KNOWN_RATES as $rate) {
            if (str_ends_with($path, '/' . $rate)) {
                return substr($path, 0, -strlen('/' . $rate));
            }
        }

        return $path;
    }

    private function ratePath(string $familyPath, string $rate): string
    {
        return rtrim($familyPath, '/') . '/' . $rate;
    }

    private function resolvedPath(string $path): string
    {
        return str_replace('$${sounds_dir}', $this->soundsRoot(), $path);
    }

    private function isDeletableStreamDirectory(string $path): bool
    {
        $musicRoot = realpath($this->soundsRoot() . DIRECTORY_SEPARATOR . 'music');

        if (! $musicRoot) {
            return false;
        }

        $resolvedPath = realpath($path) ?: $path;
        $musicRoot = rtrim(str_replace('\\', '/', $musicRoot), '/');
        $resolvedPath = rtrim(str_replace('\\', '/', $resolvedPath), '/');

        if ($resolvedPath === $musicRoot || ! str_starts_with($resolvedPath, $musicRoot . '/')) {
            return false;
        }

        $relativePath = trim(substr($resolvedPath, strlen($musicRoot)), '/');
        $parts = array_values(array_filter(explode('/', $relativePath), fn (string $part) => $part !== ''));

        return count($parts) >= 2;
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

    private function safeConvertedFileName(string $fileName): string
    {
        $fileName = pathinfo($this->safeFileName($fileName), PATHINFO_FILENAME);
        $fileName = trim($fileName, '.');

        return ($fileName !== '' ? $fileName : 'music_on_hold') . '.wav';
    }

    private function soundsRoot(): string
    {
        return rtrim(config('filesystems.disks.sounds.root', '/usr/share/freeswitch/sounds'), '/');
    }

    private function domainPathName(?string $domainUuid): string
    {
        if ($domainUuid === null || $domainUuid === '') {
            return 'global';
        }

        return data_get(collect(session('domains', []))->firstWhere('domain_uuid', $domainUuid), 'domain_name')
            ?: session('domain_name')
            ?: 'global';
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
