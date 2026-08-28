<?php

namespace App\Console\Commands;

use App\Models\LdapDirectory;
use App\Services\LdapDirectorySyncService;
use Illuminate\Console\Command;

class SyncLdapDirectoryCommand extends Command
{
    protected $signature = 'ldap:sync {directory : Directory UUID} {--dry-run} {--allow-large-removal}';
    protected $description = 'Synchronize one directory service connection immediately';

    public function handle(LdapDirectorySyncService $service): int
    {
        $directory = LdapDirectory::query()->find($this->argument('directory'));
        if (! $directory) {
            $this->error('Directory not found.');
            return self::FAILURE;
        }

        try {
            $run = $service->sync($directory, (bool) $this->option('dry-run'), (bool) $this->option('allow-large-removal'));
            $this->info("Synchronization {$run->status}: {$run->users_seen} users and {$run->groups_seen} groups seen.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
