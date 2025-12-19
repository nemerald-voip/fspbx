<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GitHubApiService;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\Updates\Update097;
use App\Console\Commands\Updates\Update101;
use App\Console\Commands\Updates\Update102;
use App\Console\Commands\Updates\Update110;
use App\Console\Commands\Updates\Update111;
use App\Console\Commands\Updates\Update112;
use App\Console\Commands\Updates\Update113;
use App\Console\Commands\Updates\Update114;
use App\Console\Commands\Updates\Update120;
use App\Console\Commands\Updates\Update121;
use App\Console\Commands\Updates\Update0917;
use App\Console\Commands\Updates\Update0918;
use App\Console\Commands\Updates\Update0924;
use App\Console\Commands\Updates\Update0925;
use App\Console\Commands\Updates\Update0926;
use App\Console\Commands\Updates\Update0940;
use App\Console\Commands\Updates\Update0941;
use App\Console\Commands\Updates\Update0942;
use App\Console\Commands\Updates\Update0951;
use App\Console\Commands\Updates\Update0955;
use App\Console\Commands\Updates\Update0961;
use App\Console\Commands\Updates\Update0965;
use App\Console\Commands\Updates\Update0966;
use App\Console\Commands\Updates\Update0967;
use App\Console\Commands\Updates\Update0969;
use App\Console\Commands\Updates\Update0970;

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

        $currentVersion = config('app.version');

        // Always get latest version directly from the file (not the config cache)
        $versionConfigFile = base_path('config/version.php');
        $downloadedVersionArray = include($versionConfigFile);
        $downloadedVersion = $downloadedVersionArray['release'] ?? null;

        // Define version-specific steps using an array
        $updateSteps = [
            '0.9.7' => Update097::class,
            '0.9.11' => Update097::class,
            '0.9.17' => Update0917::class,
            '0.9.18' => Update0918::class,
            '0.9.24' => Update0924::class,
            '0.9.25' => Update0925::class,
            '0.9.26' => Update0926::class,
            '0.9.40' => Update0940::class,
            '0.9.41' => Update0941::class,
            '0.9.42' => Update0942::class,
            '0.9.51' => Update0951::class,
            '0.9.55' => Update0955::class,
            '0.9.61' => Update0961::class,
            '0.9.65' => Update0965::class,
            '0.9.66' => Update0966::class,
            '0.9.67' => Update0967::class,
            '0.9.69' => Update0969::class,
            '0.9.70' => Update0970::class,
            '1.0.1' => Update101::class,
            '1.0.2' => Update102::class,
            '1.1.0' => Update110::class,
            '1.1.1' => Update111::class,
            '1.1.2' => Update112::class,
            '1.1.3' => Update113::class,
            '1.1.4' => Update114::class,
            '1.2.0' => Update120::class,
            '1.2.1' => Update121::class,
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
                $this->call('version:set', ['version' => $version, '--force' => true]);
                $this->info("Version successfully updated to $version.");
            }
        }

        if (version_compare($currentVersion, $downloadedVersion, '<')) {
            // Call version:set to update the version to the latest one, even if no steps were needed
            $this->call('version:set', ['version' => $downloadedVersion, '--force' => true]);
            $this->info("Version successfully updated to $downloadedVersion.");
        }

        // Composer install
        $this->executeCommand('composer install --no-interaction --ignore-platform-reqs');
        $this->executeCommand('composer dump-autoload --no-interaction --ignore-platform-reqs');

        // Cache config and routes
        // $this->runArtisanCommand('config:cache');
        $this->executeCommand('php artisan config:cache');
        // $this->runArtisanCommand('route:cache');
        $this->executeCommand('php artisan route:cache');
        $this->runArtisanCommand('queue:restart');

        //Seed the db
        $this->runArtisanCommand('db:seed', ['--force' => true]);

        //Seed the templates
        echo "Running prov:templates:seed...\n";
        try {
            $exitCode = Artisan::call('prov:templates:seed', [
                '--no-interaction' => true,
            ]);
            echo Artisan::output();
            if ($exitCode !== 0) {
                echo "prov:templates:seed returned non-zero exit code: {$exitCode} (continuing)\n";
            }
        } catch (\Throwable $e) {
            echo "Skipping prov:templates:seed due to error: {$e->getMessage()}\n";
        }

        // Create storage link
        $this->runArtisanCommand('storage:link', ['--force' => true]);

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
