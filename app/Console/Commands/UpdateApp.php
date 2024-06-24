<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;


class UpdateApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply recent updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update...');

        // Composer install
        $this->executeCommand('composer install --no-interaction --ignore-platform-reqs');
        $this->executeCommand('composer dump-autoload --no-interaction --ignore-platform-reqs');
        $this->runArtisanCommand('config:cache');
        $this->runArtisanCommand('route:cache');
        $this->runArtisanCommand('queue:restart');
        $this->runArtisanCommand('db:seed');
        $this->executeCommand('npm install');
        $this->executeCommand('npm run build', 300);

        $this->info('Update completed successfully!');

    }


    protected function executeCommand($command, $timeout = 60)
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout); // Set the timeout
        $process->setTty(true); // Enable TTY mode to preserve color output
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->output->write($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error("Command '$command' failed.");
            exit(1);
        }
    }


    protected function runArtisanCommand($command)
    {
        $exitCode = $this->call($command);
        if ($exitCode !== 0) {
            $this->error("Artisan command '$command' failed.");
            exit(1);
        }
    }

}
