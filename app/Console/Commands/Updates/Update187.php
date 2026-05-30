<?php

namespace App\Console\Commands\Updates;

use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update187
{
    private const VERSION = '1.8.7';
    private const VENDOR = 'polycom';
    private const OLD_NAME = 'VVX411';
    private const NEW_NAME = 'VVX';

    public function apply(): bool
    {
        try {
            if (! Schema::hasTable('provisioning_templates')) {
                echo "Provisioning templates table not found; skipping Polycom VVX template rename.\n";
                return true;
            }

            $this->renamePolycomVvxTemplate();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function renamePolycomVvxTemplate(): void
    {
        DB::transaction(function () {
            $defaultUpdated = ProvisioningTemplate::query()
                ->where('vendor', self::VENDOR)
                ->where('type', 'default')
                ->where('name', self::OLD_NAME)
                ->update([
                    'name' => self::NEW_NAME,
                ]);

            echo $defaultUpdated === 0
                ? "No default Polycom " . self::OLD_NAME . " templates required renaming.\n"
                : "Renamed {$defaultUpdated} default Polycom " . self::OLD_NAME . " template(s) to " . self::NEW_NAME . ".\n";

            $customUpdated = ProvisioningTemplate::query()
                ->where('vendor', self::VENDOR)
                ->where('type', 'custom')
                ->where('base_template', self::OLD_NAME)
                ->update([
                    'base_template' => self::NEW_NAME,
                ]);

            echo $customUpdated === 0
                ? "No custom Polycom templates referenced base template " . self::OLD_NAME . ".\n"
                : "Updated {$customUpdated} custom Polycom template base reference(s) from " . self::OLD_NAME . " to " . self::NEW_NAME . ".\n";
        });
    }
}
