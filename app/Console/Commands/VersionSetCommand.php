<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'version:set')]
class VersionSetCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version:set
                    {version? : The version number to set}
                    {--show : Display the current version instead of modifying files}
                    {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the application version';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $version = $this->argument('version') ?? $this->ask('What version would you like to set?');

        if ($this->option('show')) {
            return $this->line('<comment>' . env('VERSION', 'No version set') . '</comment>');
        }

        if (!$this->setVersionInEnvironmentFile($version)) {
            return;
        }

        $this->laravel['config']['app.version'] = $version;

        $this->components->info('Application version set successfully.');
    }

    /**
     * Set the application version in the environment file.
     *
     * @param  string  $version
     * @return bool
     */
    protected function setVersionInEnvironmentFile($version)
    {
        $currentVersion = env('VERSION');

        if (strlen($currentVersion) !== 0 && (!$this->confirmToProceed())) {
            return false;
        }

        if (!$this->writeNewEnvironmentFileWith($version)) {
            return false;
        }

        return true;
    }

    /**
     * Write a new environment file with the given version.
     *
     * @param  string  $version
     * @return bool
     */
    protected function writeNewEnvironmentFileWith($version)
    {
        $filePath = $this->laravel->environmentFilePath();
        $envContent = file_get_contents($filePath);

        // Check if the VERSION key exists in the .env file
        if (preg_match($this->versionReplacementPattern(), $envContent)) {
            // Replace the existing VERSION key with the new value
            $replaced = preg_replace(
                $this->versionReplacementPattern(),
                'VERSION=' . $version,
                $envContent
            );
        } else {
            // Insert VERSION after APP_NAME only once
            $replaced = preg_replace(
                "/^APP_NAME=.*$/m",
                "$0\nVERSION=$version",
                $envContent,
                1  // Make sure the replacement happens only once
            );
        }

        // Save the updated content back to the .env file
        file_put_contents($filePath, $replaced);

        return true;
    }

    /**
     * Get a regex pattern that will match env VERSION with any version number.
     *
     * @return string
     */
    protected function versionReplacementPattern()
    {
        return "/^VERSION=.*$/m";
    }

}
