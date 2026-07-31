<?php

namespace App\Console\Commands\Updates;

use App\Models\FusionCache;
use App\Models\GroupPermissions;
use App\Models\MenuItem;
use App\Models\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update197
{
    private const MENU_CLASS_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/resources/classes/menu.php';

    private const MENU_CLASS_PATH = 'public/resources/classes/menu.php';

    private const PUBLIC_REPOSITORY_RAW_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/';

    private const LEGACY_DASHBOARD_CALLER_FILES = [
        'fusionpbx_index.php',
        'resources/login.php',
        'resources/check_auth.php',
        'secure/index.php',
        'core/domains/domains.php',
        'core/default_settings/app_config.php',
        'app/devices/resources/dashboard/device_keys.php',
        'app/call_centers/resources/dashboard/call_center_agents.php',
    ];

    private const LEGACY_GRANDSTREAM_WAVE_CALLER_FILES = [
        'app/devices/device_edit.php',
    ];

    private const LEGACY_GRANDSTREAM_WAVE_DIRECTORIES = [
        'app/gswave',
        'resources/templates/provision/grandstream/gswave',
        'resources/templates/provision/grandstream/wave',
    ];

    private const LEGACY_UPGRADE_WEB_FILES = [
        'core/upgrade/app_config.php',
        'core/upgrade/app_menu.php',
        'core/upgrade/index.php',
    ];

    private const LEGACY_UPGRADE_CLI_FILES = [
        'core/upgrade/upgrade.php',
        'core/upgrade/upgrade_domains.php',
        'core/upgrade/upgrade_schema.php',
    ];

    private const LEGACY_EDITOR_MENU_FILES = [
        'app/edit/app_menu.php',
    ];

    private const LEGACY_CALL_BROADCAST_PERMISSIONS = [
        'call_broadcast_accountcode',
        'call_broadcast_add',
        'call_broadcast_all',
        'call_broadcast_caller_id',
        'call_broadcast_concurrent_limit',
        'call_broadcast_delete',
        'call_broadcast_destination_number',
        'call_broadcast_edit',
        'call_broadcast_phone_numbers',
        'call_broadcast_send',
        'call_broadcast_start_time',
        'call_broadcast_timeout',
        'call_broadcast_toll_allow',
        'call_broadcast_view',
        'call_broadcast_voicemail_detection',
    ];

    private const LEGACY_CALL_FORWARD_PERMISSIONS = [
        'call_forward',
        'call_forward_all',
        'do_not_disturb',
        'follow_me',
    ];

    private const LEGACY_CLICK_TO_CALL_PERMISSIONS = [
        'click_to_call_call',
        'click_to_call_view',
    ];

    private const LEGACY_DASHBOARD_PERMISSIONS = [
        'dashboard_add',
        'dashboard_all',
        'dashboard_delete',
        'dashboard_edit',
        'dashboard_group_add',
        'dashboard_group_all',
        'dashboard_group_delete',
        'dashboard_group_edit',
        'dashboard_group_view',
        'dashboard_view',
    ];

    private const LEGACY_DEVICE_LOG_PERMISSIONS = [
        'device_log_add',
        'device_log_all',
        'device_log_delete',
        'device_log_edit',
        'device_log_view',
    ];

    private const LEGACY_DIALPLAN_TOOL_PERMISSIONS = [
        'dialplan_tool_add',
        'dialplan_tool_all',
        'dialplan_tool_delete',
        'dialplan_tool_destinations',
        'dialplan_tool_domain',
        'dialplan_tool_edit',
        'dialplan_tool_view',
    ];

    private const LEGACY_FOLLOW_ME_PERMISSIONS = [
        'follow_me_add',
        'follow_me_cid_name_prefix',
        'follow_me_cid_number_prefix',
        'follow_me_delete',
        'follow_me_destination_add',
        'follow_me_destination_delete',
        'follow_me_destination_edit',
        'follow_me_destination_view',
        'follow_me_edit',
        'follow_me_ignore_busy',
        'follow_me_prompt',
        'follow_me_view',
    ];

    private const LEGACY_GRANDSTREAM_WAVE_PERMISSIONS = [
        'gswave_view',
        'gswave_xml_view',
    ];

    private const LEGACY_UPGRADE_PERMISSIONS = [
        'upgrade_apps',
        'upgrade_schema',
        'upgrade_source',
        'upgrade_switch',
    ];

    private const LEGACY_GRANDSTREAM_WAVE_APP_UUID = '29ad51b0-6ab0-4d65-9394-629d1a34580b';

    private const LEGACY_GRANDSTREAM_WAVE_MENU_ITEM_UUID = '4c737fd8-145e-4e1d-9662-20a5ba1e82e0';

    private const LEGACY_GRANDSTREAM_WAVE_MENU_LINK = '/app/gswave/index.php';

    private const LEGACY_UPGRADE_APP_UUID = '8b1d7eb5-1009-052c-e1a8-d1f4887a3f5c';

    private const LEGACY_UPGRADE_MENU_ITEM_UUID = '8c826e92-be3c-0944-669a-24e5b915d562';

    private const LEGACY_UPGRADE_MENU_LINK = '/core/upgrade/index.php';

    private const LEGACY_CLICK_TO_CALL_DIALPLAN_APP_UUID = '90c51470-dc31-11e3-9c1a-0800200c9a66';

    private const LEGACY_CLICK_TO_CALL_DIALPLAN_TEMPLATE = 'app/dialplans/resources/switch/conf/dialplan/25_clear_sip_auto_answer.xml';

    private const LEGACY_PROVISION_EDITOR_MENU_ITEM_UUID = '57773542-a565-1a29-605d-6535da1a0870';

    private const LEGACY_PROVISION_EDITOR_MENU_LINK = '/app/edit/index.php?dir=provision';

    public function apply(): bool
    {
        try {
            if (! $this->downloadAndReplaceFile(
                self::MENU_CLASS_URL,
                base_path(self::MENU_CLASS_PATH),
                'menu.php'
            )) {
                return false;
            }

            if (! $this->replaceLegacyDashboardCallerFiles()) {
                return false;
            }

            if (! $this->replaceLegacyGrandstreamWaveCallerFiles()) {
                return false;
            }

            if (! $this->replaceLegacyUpgradeCliFiles()) {
                return false;
            }

            if (! $this->replaceLegacyEditorMenuFiles()) {
                return false;
            }

            $updated = MenuItem::query()
                ->where('menu_item_link', '/core/menu/menu.php')
                ->update(['menu_item_link' => '/menus']);

            echo $updated === 0
                ? "No Menu Manager menu items required updating.\n"
                : "Updated {$updated} Menu Manager menu item(s).\n";

            $this->replaceLegacyProvisionEditorMenuItem();
            $this->replaceLegacyDashboardReferences();
            $this->removeLegacyPermissions('Call Broadcast', self::LEGACY_CALL_BROADCAST_PERMISSIONS);
            $this->removeLegacyPermissions('Call Forward', self::LEGACY_CALL_FORWARD_PERMISSIONS);
            $this->removeLegacyPermissions('Click to Call', self::LEGACY_CLICK_TO_CALL_PERMISSIONS);
            $this->removeLegacyPermissions('Dashboard', self::LEGACY_DASHBOARD_PERMISSIONS);
            $this->removeLegacyPermissions('Device Log', self::LEGACY_DEVICE_LOG_PERMISSIONS);
            $this->removeLegacyPermissions('Dialplan Tool', self::LEGACY_DIALPLAN_TOOL_PERMISSIONS);
            $this->removeLegacyPermissions('Follow Me', self::LEGACY_FOLLOW_ME_PERMISSIONS);
            $this->removeLegacyPermissions('Grandstream Wave', self::LEGACY_GRANDSTREAM_WAVE_PERMISSIONS);
            $this->removeLegacyPermissions('Upgrade', self::LEGACY_UPGRADE_PERMISSIONS);
            $this->removeLegacyGrandstreamWaveMetadata();
            $this->removeLegacyUpgradeMetadata();
            $this->removeLegacyClickToCallFiles();
            $this->removeLegacyClickToCallDialplan();
            $this->removeLegacyDashboardFiles();
            $this->removeLegacyGrandstreamWaveFiles();
            $this->removeLegacyUpgradeWebFiles();

            echo "Update 1.9.7 completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo "Error applying update 1.9.7: {$exception->getMessage()}\n";

            return false;
        }
    }

    private function removeLegacyPermissions(string $label, array $permissionNames): void
    {
        [$groupPermissionsRemoved, $permissionsRemoved] = DB::transaction(function () use ($permissionNames) {
            $groupPermissionsRemoved = GroupPermissions::query()
                ->whereIn('permission_name', $permissionNames)
                ->delete();

            $permissionsRemoved = Permissions::query()
                ->whereIn('permission_name', $permissionNames)
                ->delete();

            return [$groupPermissionsRemoved, $permissionsRemoved];
        });

        echo "Removed {$groupPermissionsRemoved} legacy {$label} group permission assignment(s) and {$permissionsRemoved} permission catalog row(s).\n";
    }

    private function replaceLegacyDashboardReferences(): void
    {
        $menuItemsUpdated = MenuItem::query()
            ->whereIn('menu_item_link', [
                '/core/dashboard/',
                '/core/dashboard/index.php',
            ])
            ->update(['menu_item_link' => '/dashboard']);

        $defaultSettingsUpdated = DB::table('v_default_settings')
            ->where('default_setting_category', 'login')
            ->where('default_setting_subcategory', 'destination')
            ->whereIn('default_setting_value', [
                '/core/dashboard/',
                '/core/dashboard/index.php',
            ])
            ->update(['default_setting_value' => '/dashboard']);

        $domainSettingsUpdated = DB::table('v_domain_settings')
            ->where('domain_setting_category', 'login')
            ->where('domain_setting_subcategory', 'destination')
            ->whereIn('domain_setting_value', [
                '/core/dashboard/',
                '/core/dashboard/index.php',
            ])
            ->update(['domain_setting_value' => '/dashboard']);

        echo "Updated {$menuItemsUpdated} legacy Dashboard menu item(s), {$defaultSettingsUpdated} default login destination(s), and {$domainSettingsUpdated} account login destination(s).\n";
    }

    private function replaceLegacyDashboardCallerFiles(): bool
    {
        foreach (self::LEGACY_DASHBOARD_CALLER_FILES as $relativePath) {
            if (! $this->downloadAndReplaceFile(
                self::PUBLIC_REPOSITORY_RAW_URL.$relativePath,
                public_path($relativePath),
                $relativePath
            )) {
                return false;
            }
        }

        return true;
    }

    private function replaceLegacyGrandstreamWaveCallerFiles(): bool
    {
        foreach (self::LEGACY_GRANDSTREAM_WAVE_CALLER_FILES as $relativePath) {
            if (! $this->downloadAndReplaceFile(
                self::PUBLIC_REPOSITORY_RAW_URL.$relativePath,
                public_path($relativePath),
                $relativePath
            )) {
                return false;
            }
        }

        return true;
    }

    private function replaceLegacyUpgradeCliFiles(): bool
    {
        foreach (self::LEGACY_UPGRADE_CLI_FILES as $relativePath) {
            if (! $this->downloadAndReplaceFile(
                self::PUBLIC_REPOSITORY_RAW_URL.$relativePath,
                public_path($relativePath),
                $relativePath
            )) {
                return false;
            }
        }

        return true;
    }

    private function replaceLegacyEditorMenuFiles(): bool
    {
        foreach (self::LEGACY_EDITOR_MENU_FILES as $relativePath) {
            if (! $this->downloadAndReplaceFile(
                self::PUBLIC_REPOSITORY_RAW_URL.$relativePath,
                public_path($relativePath),
                $relativePath
            )) {
                return false;
            }
        }

        return true;
    }

    private function replaceLegacyProvisionEditorMenuItem(): void
    {
        $menuItems = MenuItem::query()
            ->where('menu_item_uuid', self::LEGACY_PROVISION_EDITOR_MENU_ITEM_UUID)
            ->orWhere('menu_item_link', self::LEGACY_PROVISION_EDITOR_MENU_LINK)
            ->get(['menu_item_uuid']);

        if ($menuItems->isEmpty()) {
            echo "No legacy Provision Editor menu items required updating.\n";

            return;
        }

        $menuItemUuids = $menuItems->pluck('menu_item_uuid');

        $updated = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->update([
                'menu_item_title' => 'Legacy Provision Templates',
                'menu_item_link' => '/legacy-provision-templates',
            ]);

        if (Schema::hasTable('v_menu_languages')) {
            DB::table('v_menu_languages')
                ->whereIn('menu_item_uuid', $menuItemUuids)
                ->where('menu_language', 'en-us')
                ->update(['menu_item_title' => 'Legacy Provision Templates']);
        }

        echo "Updated {$updated} legacy Provision Editor menu item(s).\n";
    }

    private function removeLegacyGrandstreamWaveMetadata(): void
    {
        [$menuItemsRemoved, $applicationsRemoved] = DB::transaction(function () {
            $menuItemUuids = DB::table('v_menu_items')
                ->where('menu_item_uuid', self::LEGACY_GRANDSTREAM_WAVE_MENU_ITEM_UUID)
                ->orWhere('menu_item_link', self::LEGACY_GRANDSTREAM_WAVE_MENU_LINK)
                ->pluck('menu_item_uuid');

            if (Schema::hasTable('v_menu_item_groups')) {
                DB::table('v_menu_item_groups')
                    ->whereIn('menu_item_uuid', $menuItemUuids)
                    ->delete();
            }

            if (Schema::hasTable('v_menu_languages')) {
                DB::table('v_menu_languages')
                    ->whereIn('menu_item_uuid', $menuItemUuids)
                    ->delete();
            }

            $menuItemsRemoved = DB::table('v_menu_items')
                ->whereIn('menu_item_uuid', $menuItemUuids)
                ->delete();

            $applicationsRemoved = Schema::hasTable('v_applications')
                ? DB::table('v_applications')
                    ->where('application_uuid', self::LEGACY_GRANDSTREAM_WAVE_APP_UUID)
                    ->delete()
                : 0;

            return [$menuItemsRemoved, $applicationsRemoved];
        });

        echo "Removed {$menuItemsRemoved} legacy Grandstream Wave menu item(s) and {$applicationsRemoved} application catalog row(s).\n";
    }

    private function removeLegacyUpgradeMetadata(): void
    {
        [$menuItemsRemoved, $applicationsRemoved] = DB::transaction(function () {
            $menuItemUuids = DB::table('v_menu_items')
                ->where('menu_item_uuid', self::LEGACY_UPGRADE_MENU_ITEM_UUID)
                ->orWhere('menu_item_link', self::LEGACY_UPGRADE_MENU_LINK)
                ->pluck('menu_item_uuid');

            if (Schema::hasTable('v_menu_item_groups')) {
                DB::table('v_menu_item_groups')
                    ->whereIn('menu_item_uuid', $menuItemUuids)
                    ->delete();
            }

            if (Schema::hasTable('v_menu_languages')) {
                DB::table('v_menu_languages')
                    ->whereIn('menu_item_uuid', $menuItemUuids)
                    ->delete();
            }

            $menuItemsRemoved = DB::table('v_menu_items')
                ->whereIn('menu_item_uuid', $menuItemUuids)
                ->delete();

            $applicationsRemoved = Schema::hasTable('v_applications')
                ? DB::table('v_applications')
                    ->where('application_uuid', self::LEGACY_UPGRADE_APP_UUID)
                    ->delete()
                : 0;

            return [$menuItemsRemoved, $applicationsRemoved];
        });

        echo "Removed {$menuItemsRemoved} legacy Upgrade menu item(s) and {$applicationsRemoved} application catalog row(s).\n";
    }

    private function removeLegacyClickToCallFiles(): void
    {
        $directory = public_path('app/click_to_call');
        $removed = false;
        $removalWarning = false;

        if (File::isDirectory($directory)) {
            if (! File::deleteDirectory($directory)) {
                throw new \RuntimeException("Unable to remove legacy Click to Call application directory: {$directory}");
            }

            $removed = true;
        }

        $dialplanTemplate = public_path(self::LEGACY_CLICK_TO_CALL_DIALPLAN_TEMPLATE);

        try {
            if (File::isFile($dialplanTemplate)) {
                if (File::delete($dialplanTemplate)) {
                    $removed = true;
                } else {
                    $removalWarning = true;
                    echo "Warning: Unable to remove legacy Click to Call dialplan template: {$dialplanTemplate}\n";
                }
            }
        } catch (Throwable $exception) {
            $removalWarning = true;
            echo "Warning: Unable to remove legacy Click to Call dialplan template: {$exception->getMessage()}\n";
        }

        if ($removed) {
            echo "Removed legacy Click to Call application files.\n";
        } elseif (! $removalWarning) {
            echo "Legacy Click to Call application files were already absent.\n";
        }
    }

    private function removeLegacyClickToCallDialplan(): void
    {
        [$detailsRemoved, $dialplansRemoved] = DB::transaction(function () {
            $dialplanUuids = DB::table('v_dialplans')
                ->where('app_uuid', self::LEGACY_CLICK_TO_CALL_DIALPLAN_APP_UUID)
                ->pluck('dialplan_uuid');

            $detailsRemoved = DB::table('v_dialplan_details')
                ->whereIn('dialplan_uuid', $dialplanUuids)
                ->delete();

            $dialplansRemoved = DB::table('v_dialplans')
                ->whereIn('dialplan_uuid', $dialplanUuids)
                ->delete();

            return [$detailsRemoved, $dialplansRemoved];
        });

        FusionCache::clear('dialplan:global');

        echo "Removed {$dialplansRemoved} legacy Click to Call dialplan(s) and {$detailsRemoved} dialplan detail row(s).\n";
    }

    private function removeLegacyDashboardFiles(): void
    {
        $directory = public_path('core/dashboard');

        try {
            if (! File::isDirectory($directory)) {
                echo "Legacy Dashboard application directory was already absent.\n";

                return;
            }

            if (File::deleteDirectory($directory)) {
                echo "Removed the legacy Dashboard application directory.\n";

                return;
            }

            echo "Warning: Unable to remove legacy Dashboard application directory: {$directory}\n";
        } catch (Throwable $exception) {
            echo "Warning: Unable to remove legacy Dashboard application directory: {$exception->getMessage()}\n";
        }
    }

    private function removeLegacyGrandstreamWaveFiles(): void
    {
        $removed = 0;
        $warnings = 0;

        foreach (self::LEGACY_GRANDSTREAM_WAVE_DIRECTORIES as $relativePath) {
            $directory = public_path($relativePath);

            try {
                if (! File::isDirectory($directory)) {
                    continue;
                }

                if (File::deleteDirectory($directory)) {
                    $removed++;
                } else {
                    $warnings++;
                    echo "Warning: Unable to remove legacy Grandstream Wave directory: {$directory}\n";
                }
            } catch (Throwable $exception) {
                $warnings++;
                echo "Warning: Unable to remove legacy Grandstream Wave directory {$directory}: {$exception->getMessage()}\n";
            }
        }

        if ($removed > 0) {
            $directoryLabel = $removed === 1 ? 'directory' : 'directories';
            echo "Removed {$removed} legacy Grandstream Wave application/template {$directoryLabel}.\n";
        } elseif ($warnings === 0) {
            echo "Legacy Grandstream Wave application and template directories were already absent.\n";
        }
    }

    private function removeLegacyUpgradeWebFiles(): void
    {
        $removed = 0;
        $warnings = 0;

        foreach (self::LEGACY_UPGRADE_WEB_FILES as $relativePath) {
            $filePath = public_path($relativePath);

            try {
                if (! File::isFile($filePath)) {
                    continue;
                }

                if (File::delete($filePath)) {
                    $removed++;
                } else {
                    $warnings++;
                    echo "Warning: Unable to remove legacy Upgrade web file: {$filePath}\n";
                }
            } catch (Throwable $exception) {
                $warnings++;
                echo "Warning: Unable to remove legacy Upgrade web file {$filePath}: {$exception->getMessage()}\n";
            }
        }

        if ($removed > 0) {
            echo "Removed {$removed} legacy Upgrade web file(s).\n";
        } elseif ($warnings === 0) {
            echo "Legacy Upgrade web files were already absent.\n";
        }
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
            $bytesWritten = File::put($filePath, $body);

            if ($bytesWritten === false || $bytesWritten === 0) {
                echo "Error replacing {$fileName}. No content was written to {$filePath}.\n";

                return false;
            }

            echo "{$fileName} file downloaded and replaced successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo "Error downloading {$fileName}: {$exception->getMessage()}\n";

            return false;
        }
    }
}
