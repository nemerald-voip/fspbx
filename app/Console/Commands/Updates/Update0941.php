<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;

class Update0941
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // Create symlink if it doesn't exist
        $this->createSymlink('/var/www/fspbx/resources/lua', '/usr/share/freeswitch/scripts/lua');

        // Set proper ownership and permissions
        $this->setOwnershipAndPermissions('/var/www/fspbx/resources/lua');

        // Run the ESL extension install script
        $this->runInstallESLExtension();

        return true;
    }

    /**
     * Create a symlink if it does not exist.
     *
     * @param string $target The target directory.
     * @param string $link   The link to be created.
     */
    protected function createSymlink(string $target, string $link)
    {
        if (!file_exists($link)) {
            $process = new Process(['ln', '-s', $target, $link]);
            $process->run();

            if ($process->isSuccessful()) {
                echo "✅ Symlink created: $link -> $target\n";
            } else {
                echo "⚠️ Failed to create symlink: $link -> $target\n";
            }
        } else {
            echo "ℹ️ Symlink already exists: $link\n";
        }
    }

    /**
     * Change ownership and permissions of the given path.
     *
     * @param string $path
     */
    protected function setOwnershipAndPermissions(string $path)
    {
        // Change ownership to www-data:www-data
        $chownProcess = new Process(['chown', '-R', 'www-data:www-data', $path]);
        $chownProcess->run();
        if ($chownProcess->isSuccessful()) {
            echo "✅ Ownership set to www-data:www-data for $path\n";
        } else {
            echo "⚠️ Failed to change ownership for $path\n";
        }

        // Change permissions to 755
        $chmodProcess = new Process(['chmod', '-R', '755', $path]);
        $chmodProcess->run();
        if ($chmodProcess->isSuccessful()) {
            echo "✅ Permissions set to 755 for $path\n";
        } else {
            echo "⚠️ Failed to change permissions for $path\n";
        }
    }

    /**
     * Run the install_esl_extension.sh script.
     */
    protected function runInstallESLExtension()
    {
        echo "🚀 Running install_esl_extension.sh...\n";
        $process = new Process(['bash', 'install/install_esl_extension.sh']);
        $process->run();

        if ($process->isSuccessful()) {
            echo "✅ install_esl_extension.sh executed successfully.\n";
        } else {
            echo "⚠️ install_esl_extension.sh encountered an issue.\n";
        }
    }
}
