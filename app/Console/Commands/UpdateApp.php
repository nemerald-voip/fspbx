<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GitHubApiService;
use Symfony\Component\Process\Process;
use App\Console\Commands\Updates\Update097;
use App\Console\Commands\Updates\Update0917;
use App\Console\Commands\Updates\Update0918;
use App\Console\Commands\Updates\Update0924;
use App\Console\Commands\Updates\Update0925;
use App\Console\Commands\Updates\Update0926;


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

    protected $githubApiService;


    public function __construct(GitHubApiService $githubApiService)
    {
        parent::__construct();
        $this->githubApiService = $githubApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update...');

        $this->runArtisanCommand('config:cache');
        $currentVersion = config('app.version');
        $downloadedVersion = config('version.release');

        // Define version-specific steps using an array
        $updateSteps = [
            '0.9.7' => Update097::class,
            '0.9.11' => Update097::class,
            '0.9.17' => Update0917::class,
            '0.9.18' => Update0918::class,
            '0.9.24' => Update0924::class,
            '0.9.25' => Update0925::class,
            '0.9.26' => Update0926::class,
            // Add more versions as needed
        ];

        foreach ($updateSteps as $version => $updateClass) {
            if (version_compare($currentVersion, $version, '<')) {
                $this->info("Applying update steps for version $version...");
                // Create instance of the class and call the apply() method
                $updateInstance = new $updateClass();
                if (!$updateInstance->apply()) {
                    // If the update fails, stop further updates and exit with failure
                    $this->error("Update to version $version failed. Stopping further updates.");
                    exit(1);
                }

                // If the update is successful, call the version:set command
                $this->call('version:set', ['version' => $version]);
                $this->info("Version successfully updated to $version.");
            }
        }

        if (version_compare($currentVersion, $downloadedVersion, '<')) {
            // Call version:set to update the version to the latest one, even if no steps were needed
            $this->call('version:set', ['version' => $downloadedVersion]);
            $this->info("Version successfully updated to $downloadedVersion.");
        }

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
