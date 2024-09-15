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

        // Cache config and routes
        $this->runArtisanCommand('config:cache');
        $this->runArtisanCommand('route:cache');
        $this->runArtisanCommand('queue:restart');

        //Seed the db
        $this->runArtisanCommand('db:seed', ['--force' => true]);

        // Create storage link
        $this->runArtisanCommand('storage:link');

        // Update Vue files
        $this->executeCommand('npm install');
        $this->executeCommand('npm run build', 300);

        // Output the current working directory
        $currentDirectory = $this->getCurrentDirectory();
        $this->info('Current working directory: ' . $currentDirectory);

        // Change ownership of the current directory
        $this->changeDirectoryOwnership($currentDirectory);

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

    /**
     * Run artisan command with optional array of options.
     */
    protected function runArtisanCommand($command, array $options = [])
    {
        $exitCode = $this->call($command, $options);
        if ($exitCode !== 0) {
            $this->error("Artisan command '$command' failed.");
            exit(1);
        }
    }

    /**
     * Retrieve the current working directory
     */
    protected function getCurrentDirectory()
    {
        return getcwd();
    }

    /**
     * Change ownership of the specified directory to www-data:www-data.
     */
    protected function changeDirectoryOwnership($directory)
    {
        $this->info("Changing ownership of directory: $directory");

        // Execute the chown command
        $this->executeCommand("chown -R www-data:www-data $directory");
    }

}
