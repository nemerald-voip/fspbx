<?php

namespace App\Console\Commands;

use App\Jobs\SyncLdapDirectory;
use App\Models\LdapDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchDueLdapDirectorySyncs extends Command
{
    protected $signature = 'ldap:dispatch-due';
    protected $description = 'Queue synchronization for enabled directory service connections that are due';

    public function handle(): int
    {
        if (! Schema::hasTable('ldap_directories')) {
            return self::SUCCESS;
        }

        LdapDirectory::query()->where('enabled', true)
            ->whereHas('domain', fn ($query) => $query->where('domain_enabled', 'true'))
            ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
            ->orderBy('priority')
            ->chunkById(100, function ($directories) {
                foreach ($directories as $directory) {
                    $claimed = LdapDirectory::query()->whereKey($directory->directory_uuid)->where('enabled', true)
                        ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
                        ->update(['next_sync_at' => now()->addMinutes($directory->sync_interval_minutes)]);

                    if ($claimed === 1) {
                        SyncLdapDirectory::dispatch($directory->directory_uuid);
                    }
                }
            }, 'directory_uuid', 'directory_uuid');

        return self::SUCCESS;
    }
}
