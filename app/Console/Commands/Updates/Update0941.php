<?php

namespace App\Console\Commands\Updates;

use App\Models\Menu;
use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Artisan;


class Update0941
{
    protected $fileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/switch/conf/dialplan/440_wake-up.xml';
    protected $filePath;

    public function __construct()
    {
        $this->filePath = base_path('public/app/dialplans/resources/switch/conf/dialplan/440_wake-up.xml');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->fileUrl, $this->filePath, '440_wake-up.xml')) {
            return false;
        }

        // Create symlink if it doesn't exist
        $this->createSymlink('/var/www/fspbx/resources/lua', '/usr/share/freeswitch/scripts/lua');

        // Set proper ownership and permissions
        $this->setOwnershipAndPermissions('/var/www/fspbx/resources/lua');

        // Run the ESL extension install script
        $this->runInstallESLExtension();

        // Update menu links if the FS PBX menu exists
        $this->updateMenuLinks();

        // Remove Dialplans records where dialplan_number equals "*925" and their associated dialplan_details
        $this->removeDialplans();

        $this->runUpgradeDefaults();

        return true;
    }

    /**
     * Download a file from a URL and replace the local file.
     *
     * @return bool
     */
    protected function downloadAndReplaceFile($url, $filePath, $fileName)
    {
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                File::put($filePath, $response->body());
                echo "$fileName file downloaded and replaced successfully.\n";
                return true;
            } else {
                echo "Error downloading $fileName. Status Code: " . $response->status() . "\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "Error downloading $fileName: " . $e->getMessage() . "\n";
            return false;
        }
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
        // Fix the symlink's ownership to www-data:www-data
        $this->fixSymlinkOwnership($link);
    }

    /**
     * Fix the ownership of the symlink.
     *
     * @param string $link
     */
    protected function fixSymlinkOwnership(string $link)
    {
        $chownProcess = new Process(['chown', '-h', 'www-data:www-data', $link]);
        $chownProcess->run();

        if ($chownProcess->isSuccessful()) {
            echo "✅ Symlink ownership changed to www-data:www-data for $link\n";
        } else {
            echo "⚠️ Failed to change symlink ownership for $link\n";
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

    /**
     * Update the FS PBX menu links by running the artisan command,
     * but only if the menu exists.
     */
    protected function updateMenuLinks()
    {
        $menuName = 'fspbx';
        $menuExists = Menu::where('menu_name', $menuName)->exists();

        if ($menuExists) {
            echo "✅ Menu '$menuName' exists. Updating menu links...\n";
            Artisan::call('menu:create-fspbx');
            echo "✅ Artisan command 'menu:create-fspbx' executed successfully.\n";
        } else {
            echo "ℹ️ Menu '$menuName' does not exist. Skipping menu links update.\n";
        }
    }

    /**
     * Remove all Dialplans records with dialplan_number "*925"
     * along with their associated dialplan_details.
     */
    protected function removeDialplans()
    {
        $dialplans = Dialplans::where('dialplan_number', '*925')->get();

        if ($dialplans->isEmpty()) {
            echo "ℹ️ No dialplan records with dialplan_number '*925' found.\n";
        } else {
            foreach ($dialplans as $dialplan) {
                // Delete associated dialplan_details
                $dialplan->dialplan_details()->delete();

                // Delete the dialplan record
                $dialplan->delete();
            }
        }

        //clear fusionpbx cache
        FusionCache::clear("dialplan.*");
    }

    private function runUpgradeDefaults()
    {
        echo "Running upgrade defaults script...";
        shell_exec("cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1");
        echo "Upgrade defaults executed successfully.";
    }
}
