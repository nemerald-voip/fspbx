<?php

namespace App\Jobs;

use App\Models\LdapDirectory;
use App\Services\Ha\ActiveNodeResolver;
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

    public function __construct(
        public readonly string $directoryUuid,
        public readonly bool $allowLargeRemoval = false
    ) {
        $this->onConnection('scheduled-jobs');
        $this->onQueue('scheduled-jobs');
    }

    public function handle(LdapDirectorySyncService $syncService, ActiveNodeResolver $activeNode): void
    {
        $lock = Cache::lock('ldap-directory-sync:' . $this->directoryUuid, 900);

        if (! $lock->get()) {
            return;
        }

        $execution = null;
        try {
            $execution = $activeNode->claimExecution(
                'ldap_directory_sync',
                $this->directoryUuid,
                $this->timeout,
                function () {
                    $directory = LdapDirectory::query()
                        ->whereKey($this->directoryUuid)
                        ->lockForUpdate()
                        ->first();

                    if (! $directory?->enabled || ($directory->next_sync_at && $directory->next_sync_at->isFuture())) {
                        return false;
                    }

                    $directory->forceFill([
                        'next_sync_at' => now()->addMinutes(max(1, (int) $directory->sync_interval_minutes)),
                    ])->save();

                    return true;
                }
            );

            if (! $execution) {
                return;
            }

            $directory = LdapDirectory::query()->find($this->directoryUuid);

            if ($directory && $directory->enabled) {
                $syncService->sync($directory, false, $this->allowLargeRemoval, $execution);
            }
            $activeNode->finishExecution($execution);
        } catch (Throwable $exception) {
            if ($execution) {
                if ($this->attempts() < $this->tries) {
                    try {
                        $activeNode->withExecution($execution, fn () => LdapDirectory::query()->whereKey($this->directoryUuid)->update(['next_sync_at' => now()]));
                    } catch (\RuntimeException $authorization) {
                        if ($authorization->getCode() !== 409) {
                            throw $authorization;
                        }
                    }
                }
                $activeNode->finishExecution($execution, 'failed', $exception->getMessage());
            }

            throw $exception;
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
