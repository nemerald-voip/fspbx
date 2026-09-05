<?php

namespace App\Console\Commands;

use App\Models\LdapDirectory;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\LdapDirectorySyncService;
use Illuminate\Console\Command;

class SyncLdapDirectoryCommand extends Command
{
    protected $signature = 'ldap:sync {directory : Directory UUID} {--dry-run} {--allow-large-removal}';
    protected $description = 'Synchronize one directory service connection immediately';

    public function handle(LdapDirectorySyncService $service, ActiveNodeResolver $activeNode): int
    {
        $directory = LdapDirectory::query()->find($this->argument('directory'));
        if (! $directory) {
            $this->error('Directory not found.');
            return self::FAILURE;
        }

        if (! function_exists('pcntl_alarm')) {
            $this->error('The pcntl extension is required to enforce the LDAP execution timeout.');
            return self::FAILURE;
        }
        $execution = $activeNode->claimExecution('ldap_directory_sync', $directory->directory_uuid, 600);
        if (! $execution) {
            $this->error('This server is not authorized to run the directory synchronization, or the directory is already running.');

            return self::FAILURE;
        }

        $previousHandler = pcntl_signal_get_handler(SIGALRM);
        $previousAsync = pcntl_async_signals(true);
        pcntl_signal(SIGALRM, fn () => throw new \RuntimeException('LDAP synchronization exceeded its 600-second deadline.', 409));
        pcntl_alarm(600);
        try {
            $run = $service->sync(
                $directory,
                (bool) $this->option('dry-run'),
                (bool) $this->option('allow-large-removal'),
                $execution
            );
            $activeNode->finishExecution($execution);
            $this->info("Synchronization {$run->status}: {$run->users_seen} users and {$run->groups_seen} groups seen.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $activeNode->finishExecution($execution, 'failed', $e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);
        }
    }
}
