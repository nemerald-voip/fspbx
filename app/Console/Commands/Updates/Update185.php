<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\GroupPermissions;
use App\Models\MenuItem;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update185
{
    private const VERSION = '1.8.5';

    public function apply(): bool
    {
        try {
            $this->ensureWakeupCallRecordScopePermissions();
            $updated = MenuItem::query()
                ->where('menu_item_link', '/app/vars/vars.php')
                ->update([
                    'menu_item_link' => '/vars',
                ]);

            echo $updated === 0
                ? "No Variables menu items required updating.\n"
                : "Updated {$updated} Variables menu item(s).\n";

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureWakeupCallRecordScopePermissions(): void
    {
        $applicationName = 'Wakeup Calls';
        $permissions = [
            'wakeup_calls_view_self_records',
            'wakeup_calls_view_all_records',
        ];
        $now = date('Y-m-d H:i:s');

        $existingPermissions = Permissions::query()
            ->whereIn('permission_name', $permissions)
            ->pluck('permission_name')
            ->all();

        $permissionRows = collect($permissions)
            ->diff($existingPermissions)
            ->map(fn ($permissionName) => [
                'permission_uuid' => (string) Str::uuid(),
                'application_name' => $applicationName,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($permissionRows !== []) {
            Permissions::query()->insert($permissionRows);
            echo "Created " . count($permissionRows) . " Wakeup Calls record scope permission row(s).\n";
        } else {
            echo "Wakeup Calls record scope permissions already exist.\n";
        }

        $assignments = [
            'superadmin' => ['wakeup_calls_view_all_records'],
            'admin' => ['wakeup_calls_view_all_records'],
            'user' => ['wakeup_calls_view_self_records'],
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (!$group) {
                echo "Group '{$groupName}' not found; Wakeup Calls record scope permissions not assigned to it.\n";
                continue;
            }

            $existingGroupPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $groupPermissions)
                ->pluck('permission_name')
                ->all();

            $groupPermissionRows = collect($groupPermissions)
                ->diff($existingGroupPermissions)
                ->map(fn ($permissionName) => [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $groupName,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'true',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ])
                ->values()
                ->all();

            if ($groupPermissionRows === []) {
                echo "Wakeup Calls record scope permissions already assigned to group '{$groupName}'.\n";
                continue;
            }

            GroupPermissions::query()->insert($groupPermissionRows);
            echo "Assigned " . count($groupPermissionRows) . " Wakeup Calls record scope permission(s) to group '{$groupName}'.\n";
        }
    }
}
