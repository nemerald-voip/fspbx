<?php

namespace Database\Seeders;

use App\Models\Groups;
use App\Models\Permissions;
use App\Models\ProFeatures;
use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use App\Models\GroupPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->createGroups();

        $this->createMobileAppSettings();

        $this->createPermissions();

        $this->createGroupPermissions();

        $this->createDefaultSettings();

        $this->createProFeatures();

        Model::reguard();
    }

    private function createGroups()
    {

        $groups = [
            [
                'group_name'        => 'Message Admin',
                'group_protected'   => 'true',
                'group_level'       => 20,
                'group_description' => "Message Admin Group",
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'group_name'        => 'multi-site admin',
                'group_protected'   => 'true',
                'group_level'       => 60,
                'group_description' => "A multi-site admin can manages multiple domains using one login",
                'insert_date'       => date("Y-m-d H:i:s"),
            ],

        ];

        foreach ($groups as $group) {
            $existing_item = Groups::where('group_name', $group['group_name'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
                Groups::create([
                    'group_name'        => $group['group_name'],
                    'group_protected'   => $group['group_protected'],
                    'group_level'       => $group['group_level'],
                    'group_description' => $group['group_description'],
                    'insert_date'       => $group['insert_date'],
                ]);
            }
        }
    }

    private function createPermissions()
    {
        $permissions = [
            [
                'application_name'       => 'Message Settings',
                'permission_name'        => 'message_settings_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Extensions',
                'permission_name'        => 'extension_suspended',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Mobile Apps',
                'permission_name'        => 'mobile_apps_password_url_show',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Firewall',
                'permission_name'        => 'firewall_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Whitelisted Numbers',
                'permission_name'        => 'whitelisted_numbers_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Wakeup Calls',
                'permission_name'        => 'wakeup_calls_list_view', // View list of wake-up calls
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'  => 'Wakeup Calls',
                'permission_name'   => 'wakeup_calls_create', // Create new wake-up calls
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'  => 'Wakeup Calls',
                'permission_name'   => 'wakeup_calls_edit', // Edit/update wake-up calls
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'  => 'Wakeup Calls',
                'permission_name'   => 'wakeup_calls_delete', // Delete wake-up calls
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'  => 'Wakeup Calls',
                'permission_name'   => 'wakeup_calls_all', // Delete wake-up calls
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'  => 'Wakeup Calls',
                'permission_name'   => 'wakeup_calls_view_settings', 
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Account Settings',
                'permission_name'        => 'account_settings_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Ring Groups',
                'permission_name'        => 'ring_group_view_settings',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Ring Groups',
                'permission_name'        => 'ring_group_view_advanced',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_update',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_delete',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_holidays_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_holidays_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_holidays_update',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Business Hours',
                'permission_name'        => 'business_hours_holidays_delete',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Group Manager',
                'permission_name'        => 'domain_groups_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'user_view_managed_account_groups',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'user_update_managed_account_groups',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'user_view_managed_accounts',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'user_update_managed_accounts',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'api_key_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'api_key_update',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'User Manager',
                'permission_name'        => 'api_key_delete',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Extensions',
                'permission_name'        => 'extension_device_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Extensions',
                'permission_name'        => 'extension_device_assign',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Extensions',
                'permission_name'        => 'extension_device_unassign',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'       => 'Extensions',
                'permission_name'        => 'extension_device_update',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            // Add more permissions as needed
        ];

        foreach ($permissions as $permission) {
            $existingPermission = Permissions::where('application_name', $permission['application_name'])
                ->where('permission_name', $permission['permission_name'])
                ->first();

            if (is_null($existingPermission)) {
                Permissions::create([
                    'application_name'        => $permission['application_name'],
                    'permission_name'        => $permission['permission_name'],
                    'insert_date'       => $permission['insert_date'],
                ]);
            }
        }
    }

    private function createGroupPermissions()
    {
        $group_permissions = [
            [
                'permission_name'        => 'message_settings_list_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'message_settings_list_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Message Admin",
                'group_uuid'            => Groups::where('group_name', "Message Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'extension_suspended',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'mobile_apps_password_url_show',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'firewall_list_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'account_settings_list_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],

            [
                'permission_name'        => 'domain_select',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "multi-site admin",
                'group_uuid'            => Groups::where('group_name', "multi-site admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_edit',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_all',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_edit',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'wakeup_calls_view_settings',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'ring_group_view_settings',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'ring_group_view_settings',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'ring_group_view_advanced',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'business_hours_holidays_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'domain_groups_list_view',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'user_view_managed_account_groups',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'user_update_managed_account_groups',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],

            [
                'permission_name'      => 'user_view_managed_accounts',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],

            [
                'permission_name'      => 'user_update_managed_accounts',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'api_key_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'api_key_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'api_key_delete',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_create',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_assign',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_assign',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_unassign',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_unassign',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "superadmin",
                'group_uuid'           => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'      => 'extension_device_update',
                'permission_protected' => 'true',
                'permission_assigned'  => 'true',
                'group_name'           => "admin",
                'group_uuid'           => Groups::where('group_name', "admin")->value('group_uuid'),
                'insert_date'          => date("Y-m-d H:i:s"),
            ],

            
        ];

        foreach ($group_permissions as $permission) {
            $existing_item = GroupPermissions::where('permission_name', $permission['permission_name'])
                ->where('group_uuid', $permission['group_uuid'])
                ->first();

            if (empty($existing_item)) {
                // Add new permission
                GroupPermissions::create([
                    'permission_name'        => $permission['permission_name'],
                    'permission_protected'  => $permission['permission_protected'],
                    'permission_assigned'  => $permission['permission_assigned'],
                    'group_name'            => $permission['group_name'],
                    'group_uuid'            => $permission['group_uuid'],
                    'insert_date'       => $permission['insert_date'],
                ]);
            }
        }
    }

    private function createMobileAppSettings()
    {
        $settings = [
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'apple_store_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'google_play_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mac_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'windows_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_conn_protocol',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "sip or tcp or sips",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_proxy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'organization_region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London)",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'dont_send_user_credentials',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Don't include user credentials in the welcome email",
            ],
            /*[
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'password_url_show',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Display 'Get Password' link on the success notification pop-up",
            ],*/

        ];

        // Log::alert(Category::where('name', trans('custom-fields::general.categories.cost_recovery'))->where('company_id', $company_id)->value('id'));
        foreach ($settings as $setting) {
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
                DefaultSettings::create([
                    'default_setting_category'      => $setting['default_setting_category'],
                    'default_setting_subcategory'   => $setting['default_setting_subcategory'],
                    'default_setting_name'          => $setting['default_setting_name'],
                    'default_setting_value'         => $setting['default_setting_value'],
                    'default_setting_enabled'       => $setting['default_setting_enabled'],
                    'default_setting_description'   => $setting['default_setting_description'],
                ]);
            }
        }
    }

    private function createDefaultSettings()
    {
        $settings = [
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e350_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e300_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e220_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e500_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e550_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'polycom_vvx_320x240_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'polycom_vvx_480x272_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e350_logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e350_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e220_logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e220_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e500_logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e500_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e550_logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e550_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'yealink_t46s_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'yealink_t46s_wallpaper_filename',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'yealink_t46u_wallpaper',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'yealink_t46u_wallpaper_filename',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'grandstream_outbound_proxy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'company',
                'default_setting_subcategory'   => 'billing_suspension',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'aws_upload_calls_' . $this->getMacAddress(),
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Executes upload job only on the server with MAC address " . $this->getMacAddress(),
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'clear_export_directory',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'horizon_snapshot',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'horizon_check_status',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'cache_prune_stale_tags',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'prune_old_webhook_requests',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'backup',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'backup_path',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "/var/backups/fspbx",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'check_fax_service_status',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Monitors pending faxes and identifies those exceeding the allowed threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_service_threshold',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "5",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Defines the maximum number of pending faxes allowed before exceeding the threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_wait_time_threshold',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "60",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of minutes a fax can remain in waiting status before being counted against the threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_service_notify_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Email address to receive notifications when pending faxes exceed the allowed wait time threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'audit_stale_ringotel_users',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Enables checking for stale Ringotel users based on last active time.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'ringotel_audit_notify_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Email address to receive notifications for stale Ringotel users.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'stale_ringotel_users_threshold',
                'default_setting_name'          => 'numeric',
                'default_setting_value'         => "180",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Defines the number of days after which a Ringotel user is considered stale.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'wake_up_calls',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable or disable the processing of scheduled wake-up calls. If set to 'false', scheduled calls will not be executed.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'delete_old_faxes',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enables automatic deletion of fax files (.tif and .pdf) and their corresponding database records older than the configured retention period.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'days_keep_fax',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "90",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of days to retain fax files and logs before they are automatically deleted.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'delete_old_call_recordings',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enables automatic deletion of call recordings (.wav and .mp3) and their corresponding database records older than the configured retention period.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'days_keep_call_recordings',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "90",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of days to retain call recordings before they are automatically deleted.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'delete_old_voicemails',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enables automatic deletion of voicemail messages (msg_*.wav and msg_*.mp3) older than the configured retention period.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'days_keep_voicemails',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "90",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of days to retain voicemails before they are automatically deleted.",
            ],
            [
                'default_setting_category'      => 'authentication',
                'default_setting_subcategory'   => 'email_challenge',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable or disable email challenge authentication. When enabled, users will be required to verify their email before completing the login process.",
            ],

          [
                'default_setting_category'      => 'ring_group',
                'default_setting_subcategory'   => 'honor_member_cfwd',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Default setting for Allow Member Call Forwarding Rules",
            ],
            [
                'default_setting_category'      => 'ring_group',
                'default_setting_subcategory'   => 'honor_member_followme',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Default setting for Allow Member Follow Me Rules",
            ],

        ];

        // Log::alert(Category::where('name', trans('custom-fields::general.categories.cost_recovery'))->where('company_id', $company_id)->value('id'));
        foreach ($settings as $setting) {
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
                DefaultSettings::create([
                    'default_setting_category'      => $setting['default_setting_category'],
                    'default_setting_subcategory'   => $setting['default_setting_subcategory'],
                    'default_setting_name'          => $setting['default_setting_name'],
                    'default_setting_value'         => $setting['default_setting_value'],
                    'default_setting_enabled'       => $setting['default_setting_enabled'],
                    'default_setting_description'   => $setting['default_setting_description'],
                ]);
            }
        }
    }

    private function createProFeatures()
    {
        try {
            $features = [
                [
                    'name' => 'FS PBX Pro Features',
                    'slug' => 'fspbx',
                ],
            ];
    
            foreach ($features as $feature) {
                $existingFeature = ProFeatures::where('name', $feature['name'])->first();
    
                if (is_null($existingFeature)) {
                    ProFeatures::create([
                        'name' => $feature['name'],
                        'slug' => $feature['slug'],
                        'license' => null, // or provide a default value for license if needed
                    ]);
                }
            }
        } catch (\Exception $e) {
            logger("Error seeding ProFeatures");
        }
    }



    public function getMacAddress()
    {
        // Run the shell command using Process
        $process = Process::run("ip link show | grep 'link/ether' | awk '{print $2}' | head -n 1");

        // Get the output from the process
        $macAddress = trim($process->output());

        return $macAddress ?: null;
    }
}
