<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class Update182
{
    private const PROVISION_CLASS_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/provision/resources/classes/provision.php';
    private const PROVISION_CLASS_PATH = 'public/app/provision/resources/classes/provision.php';

    public function apply(): bool
    {
        if (! $this->downloadAndReplaceFile(
            self::PROVISION_CLASS_URL,
            base_path(self::PROVISION_CLASS_PATH),
            'provision.php'
        )) {
            return false;
        }

        echo "Update 1.8.2 completed successfully.\n";
        return true;
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
}
