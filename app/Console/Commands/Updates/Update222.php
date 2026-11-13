<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update222
{
    public function apply(): bool
    {
        try {
            DB::transaction(function () {
                // Retire the old permission without carrying any assignments
                // into the separate Inbox and Sent download permissions.
                DB::table('v_group_permissions')->where('permission_name', 'fax_download_view')->delete();
                DB::table('v_permissions')->where('permission_name', 'fax_download_view')->delete();
            });

            Cache::tags(['permissions'])->flush();

            // UpdateApp runs DatabaseSeeder afterward to create the new
            // permissions and their admin/superadmin defaults.
            echo "Removed legacy fax download permission and assignments.\n";

            return true;
        } catch (Throwable $exception) {
            echo "Failed to remove legacy fax download permission: {$exception->getMessage()}\n";

            return false;
        }
    }
}
