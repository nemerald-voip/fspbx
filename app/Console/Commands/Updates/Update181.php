<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class Update181
{
    // FreeSWITCH scripts are now committed under resources/freeswitch_scripts.
    // Update183 links /usr/share/freeswitch/scripts to that directory, so old
    // upgrades must not download the removed public script copy.
    // private const LOCAL_STREAM_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua';
    // private const LOCAL_STREAM_PATHS = [
    //     'future-install local_stream.conf.lua' => 'public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua',
    //     'installed local_stream.conf.lua' => '/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua',
    // ];

    public function apply(): bool
    {
        // The current committed local_stream.conf.lua is deployed by Update183's symlink.

        $this->updateSettingsNavigation();

        echo "Update 1.8.1 completed successfully.\n";
        return true;
    }

    private function targetPath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function downloadAndReplaceFile(string $url, string $filePath, string $fileName): bool
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                echo "Error downloading {$fileName}. Status Code: {$response->status()}\n";
                return false;
            }

            $body = $response->body();

            if (trim($body) === '') {
                echo "Error downloading {$fileName}. Downloaded file was empty.\n";
                return false;
            }

            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, $body);

            echo "{$fileName} file downloaded and replaced successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error downloading {$fileName}: {$exception->getMessage()}\n";
            return false;
        }
    }

    private function updateSettingsNavigation(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/core/default_settings/default_settings.php')
            ->update([
                'menu_item_link' => '/default-settings',
            ]);

        echo $updated === 0
            ? "No Default Settings menu items required updating.\n"
            : "Updated {$updated} Default Settings menu item(s).\n";

    }

}
