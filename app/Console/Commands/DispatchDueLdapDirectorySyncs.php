<?php

namespace App\Console\Commands;

use App\Jobs\SyncLdapDirectory;
use App\Models\LdapDirectory;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DispatchDueLdapDirectorySyncs extends Command
{
    protected $signature = 'ldap:dispatch-due';
    protected $description = 'Queue synchronization for enabled directory service connections that are due';

    public function handle(ActiveNodeResolver $activeNode): int
    {
        if (! Schema::hasTable('ldap_directories')) {
            return self::SUCCESS;
        }

        $decision = $activeNode->resolve();

        if (! $decision['active']) {
            $signature = sha1($decision['source'].'|'.$decision['status'].'|'.$decision['reason']);
            if (Cache::add('ldap:dispatch-due:last-skip:'.$signature, true, 3600)) {
                logger('ldap:dispatch-due: skipped ('.$decision['source'].'/'.$decision['status'].') '.$decision['reason']);
            }

            return self::SUCCESS;
        }

        LdapDirectory::query()->where('enabled', true)
            ->whereHas('domain', fn ($query) => $query->where('domain_enabled', 'true'))
            ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
            ->orderBy('priority')
            ->chunkById(100, function ($directories) {
                foreach ($directories as $directory) {
                    // The worker advances next_sync_at only after it has an
                    // ownership-generation claim. A stale queued job leaves
                    // this directory due for the new owner.
                    SyncLdapDirectory::dispatch($directory->directory_uuid);
                }
            }, 'directory_uuid', 'directory_uuid');

        return self::SUCCESS;
    }
}
