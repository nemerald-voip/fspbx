<?php

namespace Database\Seeders;

use App\Models\CallTranscriptionProvider;
use App\Models\Groups;
use App\Models\Permissions;
use App\Models\ProFeatures;
use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use App\Models\GroupPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\PaymentGateway;
use App\Models\GatewaySetting;

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

        $this->createPaymentGateways();

        $this->createCallTranscriptionProviders();

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
            ['application_name' => 'Message Settings', 'permission_name' => 'message_settings_list_view'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_suspended'],
            ['application_name' => 'Mobile Apps', 'permission_name' => 'mobile_apps_password_url_show'],
            ['application_name' => 'Firewall', 'permission_name' => 'firewall_list_view'],
            ['application_name' => 'Cloud Provisioning', 'permission_name' => 'manage_cloud_provision_providers'],
            ['application_name' => 'Cloud Provisioning', 'permission_name' => 'manage_device_cloud_provisioning_settings'],
            ['application_name' => 'Whitelisted Numbers', 'permission_name' => 'whitelisted_numbers_list_view'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_list_view'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_create'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_edit'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_delete'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_all'],
            ['application_name' => 'Wakeup Calls', 'permission_name' => 'wakeup_calls_view_settings'],
            ['application_name' => 'Cloud Provisioning', 'permission_name' => 'cloud_provisioning_show_credentials'],
            ['application_name' => 'Cloud Provisioning', 'permission_name' => 'polycom_api_token_update'],
            ['application_name' => 'Account Settings', 'permission_name' => 'account_settings_list_view'],
            ['application_name' => 'Ring Groups', 'permission_name' => 'ring_group_view_settings'],
            ['application_name' => 'Ring Groups', 'permission_name' => 'ring_group_view_advanced'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_list_view'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_create'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_update'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_delete'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_holidays_list_view'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_holidays_create'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_holidays_update'],
            ['application_name' => 'Business Hours', 'permission_name' => 'business_hours_holidays_delete'],
            ['application_name' => 'Group Manager', 'permission_name' => 'domain_groups_list_view'],
            ['application_name' => 'User Manager', 'permission_name' => 'user_view_managed_account_groups'],
            ['application_name' => 'User Manager', 'permission_name' => 'user_update_managed_account_groups'],
            ['application_name' => 'User Manager', 'permission_name' => 'user_view_managed_accounts'],
            ['application_name' => 'User Manager', 'permission_name' => 'user_update_managed_accounts'],
            ['application_name' => 'User Manager', 'permission_name' => 'user_status'],
            ['application_name' => 'User Manager', 'permission_name' => 'api_key_create'],
            ['application_name' => 'User Manager', 'permission_name' => 'api_key_update'],
            ['application_name' => 'User Manager', 'permission_name' => 'api_key_delete'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_device_create'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_device_assign'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_device_unassign'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_device_update'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_forward_all'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_forward_busy'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_forward_no_answer'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_forward_not_registered'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_call_sequence'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_do_not_disturb'],
            ['application_name' => 'Extensions', 'permission_name' => 'extension_mobile_app_settings'],
            ['application_name' => 'Locations', 'permission_name' => 'location_view'],
            ['application_name' => 'Locations', 'permission_name' => 'location_create'],
            ['application_name' => 'Locations', 'permission_name' => 'location_update'],
            ['application_name' => 'Locations', 'permission_name' => 'location_delete'],
            ['application_name' => 'Devices', 'permission_name' => 'device_key_advanced'],
            ['application_name' => 'Logs', 'permission_name' => 'logs_list_view'],
            ['application_name' => 'System Settings', 'permission_name' => 'payment_gateways_view'],
            ['application_name' => 'System Settings', 'permission_name' => 'call_transcription_settings_view'],
            ['application_name' => 'Call Transcriptions', 'permission_name' => 'transcription_view'],
            ['application_name' => 'Call Transcriptions', 'permission_name' => 'transcription_create'],
            ['application_name' => 'Call Transcriptions', 'permission_name' => 'transcription_read'],
            ['application_name' => 'Call Transcriptions', 'permission_name' => 'transcription_summary'],
            ['application_name' => 'XML CDR', 'permission_name' => 'xml_cdr_search_sentiment'],
        ];
        $timestamp = date("Y-m-d H:i:s");

        // 1) Find which permission_names already exist (so we don't touch them)
        $names = array_column($permissions, 'permission_name');
        $existing = Permissions::whereIn('permission_name', $names)
            ->pluck('permission_name')
            ->all();

        // 2) Build ONLY the missing rows, adding the required UUID + insert_date
        $toInsert = [];
        foreach ($permissions as $p) {
            if (!in_array($p['permission_name'], $existing, true)) {
                $toInsert[] = [
                    'permission_uuid'  => (string) Str::uuid(),
                    'application_name' => $p['application_name'],
                    'permission_name'  => $p['permission_name'],
                    'insert_date'      => $timestamp,
                ];
            }
        }

        // 3) Insert missing ones (no updates, no overrides)
        if (!empty($toInsert)) {
            Permissions::insert($toInsert);
        }
    }

    private function createGroupPermissions()
    {
        $permissionsByGroup = [
            'superadmin' => [
                'message_settings_list_view',
                'extension_suspended',
                'mobile_apps_password_url_show',
                'firewall_list_view',
                'manage_cloud_provision_providers',
                'manage_device_cloud_provisioning_settings',
                'cloud_provisioning_show_credentials',
                'polycom_api_token_update',
                'wakeup_calls_list_view',
                'wakeup_calls_create',
                'wakeup_calls_edit',
                'wakeup_calls_delete',
                'wakeup_calls_all',
                'account_settings_list_view',
                'wakeup_calls_view_settings',
                'ring_group_view_settings',
                'ring_group_view_advanced',
                'business_hours_list_view',
                'business_hours_create',
                'business_hours_update',
                'business_hours_delete',
                'business_hours_holidays_list_view',
                'business_hours_holidays_create',
                'business_hours_holidays_update',
                'business_hours_holidays_delete',
                'domain_groups_list_view',
                'user_view_managed_account_groups',
                'user_update_managed_account_groups',
                'user_view_managed_accounts',
                'user_update_managed_accounts',
                'user_status',
                'api_key_create',
                'api_key_update',
                'api_key_delete',
                'extension_device_create',
                'extension_device_assign',
                'extension_device_unassign',
                'extension_device_update',
                'extension_forward_all',
                'extension_forward_busy',
                'extension_forward_no_answer',
                'extension_forward_not_registered',
                'extension_call_sequence',
                'extension_do_not_disturb',
                'extension_mobile_app_settings',
                'location_view',
                'location_create',
                'location_update',
                'location_delete',
                'device_key_advanced',
                'device_line_server_address_primary',
                'device_line_server_address_secondary',
                'device_line_outbound_proxy_primary',
                'device_line_outbound_proxy_secondary',
                'logs_list_view',
                'call_transcription_settings_view',
                'transcription_view',
                'transcription_read',
                'transcription_create',
                'transcription_summary',
                'xml_cdr_search_sentiment',
            ],
            'admin' => [
                'wakeup_calls_list_view',
                'wakeup_calls_create',
                'wakeup_calls_edit',
                'wakeup_calls_delete',
                'ring_group_view_settings',
                'business_hours_list_view',
                'business_hours_create',
                'business_hours_update',
                'business_hours_delete',
                'business_hours_holidays_list_view',
                'business_hours_holidays_create',
                'business_hours_holidays_update',
                'business_hours_holidays_delete',
                'extension_device_create',
                'extension_device_assign',
                'extension_device_unassign',
                'extension_device_update',
                'extension_forward_all',
                'extension_forward_busy',
                'extension_forward_no_answer',
                'extension_forward_not_registered',
                'extension_call_sequence',
                'extension_do_not_disturb',
                'extension_mobile_app_settings',
                'xml_cdr_search_sentiment',
            ],
            'Message Admin' => [
                'message_settings_list_view',
            ],
            'multi-site admin' => [
                'domain_select',
            ],
        ];


        // Groups by name -> uuid
        $groupNames = array_keys($permissionsByGroup);
        $groups = Groups::whereIn('group_name', $groupNames)->pluck('group_uuid', 'group_name');

        // Flatten all permission names we'll touch
        $allPerms = [];
        foreach ($permissionsByGroup as $perms) {
            foreach ($perms as $p) $allPerms[$p] = true;
        }
        $allPerms = array_keys($allPerms);

        // Existing pairs so we don't override or duplicate anything
        $existingPairs = GroupPermissions::whereIn('group_uuid', $groups->values())
            ->whereIn('permission_name', $allPerms)
            ->get(['group_uuid', 'permission_name'])
            ->map(fn($row) => $row->group_uuid . '|' . $row->permission_name)
            ->all();
        $existingSet = array_flip($existingPairs);

        // Build only missing rows, include required UUID
        $now = date("Y-m-d H:i:s");
        $toInsert = [];

        foreach ($permissionsByGroup as $groupName => $permissions) {
            if (!isset($groups[$groupName])) {
                continue; // group not present in DB
            }
            $gid = $groups[$groupName];

            foreach ($permissions as $permissionName) {
                $key = $gid . '|' . $permissionName;
                if (isset($existingSet[$key])) {
                    continue; // already assigned, leave as-is
                }
                $toInsert[] = [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid'            => $gid,
                    'group_name'            => $groupName,
                    'permission_name'       => $permissionName,
                    'permission_protected'  => 'true',
                    'permission_assigned'   => 'true',
                    'insert_date'           => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            GroupPermissions::insert($toInsert); // insert-only, no updates
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
                'default_setting_subcategory'   => 'polycom_vvx_firmware_url',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e350_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e300_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e220_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e500_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e550_firmware',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Example: https://domain.com/sip.ld",
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
                'default_setting_subcategory'   => 'poly_e300_logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'poly_e300_wallpaper',
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
                'default_setting_subcategory'   => 'polycom_softkey_recent_calls',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "1",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable to display the Recent Calls softkey. 1 (default) - Enabled. 0 - Disabled",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'polycom_vm_transfer_enable',
                'default_setting_name'          => 'numeric',
                'default_setting_value'         => "1",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable to display transfer to voicemail. 1 - Enabled. 0 - Disabled",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'polycom_speeddial_enable',
                'default_setting_name'          => 'numeric',
                'default_setting_value'         => "0",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Enable to display speed dial button. 1 - Enabled. 0 - Disabled",
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
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'cisco_outbound_proxy_1',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'cisco_outbound_proxy_2',
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
                'default_setting_subcategory'   => 'days_keep_email_logs',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "90",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of days to retain email logs before they are automatically deleted.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'days_keep_transcriptions',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "90",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of days to retain call transcriptions before they are automatically deleted.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'delete_old_transcriptions',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Enables automatic deletion of call transcriptions older than the configured retention period.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'delete_old_email_logs',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enables automatic deletion of email logs older than the configured retention period.",
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
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'transcribe_provider',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "openai",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "openai,google,azure,watson",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'openai_transcription_model',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "whisper-1",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "gpt-4o-transcribe - best quality, gpt-4o-mini-transcribe - faster/cheaper/less accurate, whisper-1 - good, but older",
            ],
            [
                'default_setting_category'      => 'recordings',
                'default_setting_subcategory'   => 'openai_default_voice',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "alloy",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "alloy, echo, fable, onyx, nova, shimmer",
            ],
            [
                'default_setting_category'      => 'cloud_provision',
                'default_setting_subcategory'   => 'polycom_api_token',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'cloud_provision',
                'default_setting_subcategory'   => 'polycom_custom_configuration',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'aws',
                'default_setting_subcategory'   => 'access_key',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'aws',
                'default_setting_subcategory'   => 'bucket_name',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'aws',
                'default_setting_subcategory'   => 'region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'aws',
                'default_setting_subcategory'   => 'secret_key',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'aws',
                'default_setting_subcategory'   => 'upload_notification_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'sms_notifications_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'sms_notification_from_number',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "enter number in e164 format",
            ],
                        [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'sms_notification_include_transcription',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Include voicemail transcription text in SMS notifications",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'sms_notification_text',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'New voicemail: ${caller_id_name} - ${caller_id_number} left a message for mailbox ${voicemail_id} at ${message_date}. Duration: ${message_length} sec.',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "SMS notification text",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'voicemail_queue_strategy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'legacy',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "modern: enables the new, optimized queue handling with improved performance and stability. legacy: uses the original queue behavior for backward compatibility.",
            ],
            [
                'default_setting_category'      => 'limit',
                'default_setting_subcategory'   => 'mobile_app_users',
                'default_setting_name'          => 'numeric',
                'default_setting_value'         => '3',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'limit',
                'default_setting_subcategory'   => 'extension_limit_error',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'You have reached the maximum number of extensions allowed (%d)',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'provision_base_url',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'https://domain.com/prov',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "New provision server base URL",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'notify_in_transit',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Send a notification to the sender that the fax has been accepted and is being processed",
            ],


        ];

        if (empty($settings)) {
            return;
        }

        // Build an OR-of-ANDs to match the exact (category, subcategory, name) triples we care about
        $existingTriples = DefaultSettings::query()
            ->where(function ($q) use ($settings) {
                foreach ($settings as $s) {
                    $q->orWhere(function ($q2) use ($s) {
                        $q2->where('default_setting_category', $s['default_setting_category'])
                            ->where('default_setting_subcategory', $s['default_setting_subcategory'])
                            ->where('default_setting_name', $s['default_setting_name']);
                    });
                }
            })
            ->get(['default_setting_category', 'default_setting_subcategory', 'default_setting_name'])
            ->map(fn($r) => "{$r->default_setting_category}|{$r->default_setting_subcategory}|{$r->default_setting_name}")
            ->all();

        $existingLookup = array_flip($existingTriples);

        // Build ONLY the missing rows
        $toInsert = [];
        foreach ($settings as $s) {
            $key = "{$s['default_setting_category']}|{$s['default_setting_subcategory']}|{$s['default_setting_name']}";
            if (!isset($existingLookup[$key])) {
                $toInsert[] = [
                    'default_setting_uuid'        => (string) Str::uuid(),
                    'default_setting_category'    => $s['default_setting_category'],
                    'default_setting_subcategory' => $s['default_setting_subcategory'],
                    'default_setting_name'        => $s['default_setting_name'],
                    'default_setting_value'       => $s['default_setting_value'],
                    'default_setting_enabled'     => $s['default_setting_enabled'],
                    'default_setting_description' => $s['default_setting_description'],
                ];
            }
        }

        if (!empty($toInsert)) {
            DefaultSettings::insert($toInsert);
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

    private function createCallTranscriptionProviders(): void
    {
        if (! Schema::hasTable('call_transcription_providers')) {
            return;
        }

        // Define the providers you want available by default
        $providers = [
            ['key' => 'assemblyai', 'name' => 'AssemblyAI', 'is_active' => true],
            // add more here later, e.g. ['key' => 'whisper', 'name' => 'OpenAI Whisper', 'is_active' => true],
        ];

        // Find existing keys so we only insert missing ones
        $keys      = array_column($providers, 'key');
        $existing  = CallTranscriptionProvider::whereIn('key', $keys)
            ->pluck('key')
            ->all();

        $now       = now();
        $toInsert  = [];

        foreach ($providers as $p) {
            if (!in_array($p['key'], $existing, true)) {
                $toInsert[] = [
                    'uuid'       => (string) \Illuminate\Support\Str::uuid(),
                    'key'        => $p['key'],
                    'name'       => $p['name'],
                    'is_active'  => $p['is_active'] ?? true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            CallTranscriptionProvider::insert($toInsert);
        }
    }



    private function createPaymentGateways()
    {
        // Bail out if migrations haven't run yet
        if (
            ! Schema::hasTable('payment_gateways') ||
            ! Schema::hasTable('gateway_settings')
        ) {
            return;
        }

        // 1) Ensure a Stripe gateway record
        $stripe = PaymentGateway::firstOrCreate(
            ['slug' => 'stripe'],
            [
                'name'       => 'Stripe',
                'is_enabled' => false,
            ]
        );

        $stripe = PaymentGateway::where('slug', 'stripe')->first();
        $gatewayUuid = $stripe->getKey();

        // 2) Seed its settings if missing
        $stripe_settings = [
            'sandbox'      => 'false',
        ];

        foreach ($stripe_settings as $key => $value) {
            GatewaySetting::firstOrCreate(
                [
                    'gateway_uuid' => $gatewayUuid,
                    'setting_key'  => $key,
                ],
                [
                    'uuid'          => Str::uuid(),
                    'setting_value' => $value,
                ]
            );
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
