<?php

namespace App\Jobs;

use App\Models\LdapDirectory;
use App\Services\LdapDirectorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncLdapDirectory implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $uniqueFor = 900;

    public function __construct(public readonly string $directoryUuid, public readonly bool $allowLargeRemoval = false)
    {
    }

    public function handle(LdapDirectorySyncService $syncService): void
    {
        $lock = Cache::lock('ldap-directory-sync:' . $this->directoryUuid, 900);

        if (! $lock->get()) {
            return;
        }

        try {
            $directory = LdapDirectory::query()->find($this->directoryUuid);
            if ($directory && $directory->enabled) {
                $syncService->sync($directory, false, $this->allowLargeRemoval);
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    public function uniqueId(): string
    {
        return $this->directoryUuid;
    }
}
