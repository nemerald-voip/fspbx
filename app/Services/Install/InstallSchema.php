<?php

namespace App\Services\Install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallSchema
{
    private const EXTENSIONS_APP_UUID = 'e68d9689-2769-e013-28fa-6214bf47fca3';
    private const RING_GROUPS_APP_UUID = '1d61fb65-1eec-bc73-a6ee-a6203b4fe6f2';
    private const IVR_MENUS_APP_UUID = 'a5788e9b-58bc-bd1b-df59-fff5d51253ab';
    private const FOLLOW_ME_APP_UUID = 'f5210fba-337d-4e05-86b6-7a2fd9dc7c42';

    public function ensureSchemas(): void
    {
        $this->ensureExtensionsSchema();
        $this->ensureRingGroupsSchema();
        $this->ensureIvrMenusSchema();
        $this->ensureFollowMeSchema();
    }

    public function ensureMetadata(): void
    {
        $this->seedExtensionPermissions();
        $this->seedExtensionDefaultSettings();
        $this->seedRingGroupPermissions();
        $this->seedRingGroupDefaultSettings();
        $this->seedIvrMenuPermissions();
        $this->seedIvrMenuDefaultSettings();
        $this->seedFollowMePermissions();
        $this->seedFollowMeDefaultSettings();
    }

    private function ensureExtensionsSchema(): void
    {
        $this->ensureNaturalSortFunction();

        if (!Schema::hasTable('v_extensions')) {
            Schema::create('v_extensions', function (Blueprint $table) {
                $table->uuid('extension_uuid')->primary();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->text('extension')->nullable()->index();
                $table->text('number_alias')->nullable();
                $table->text('password')->nullable();
                $table->text('accountcode')->nullable();
                $table->text('effective_caller_id_name')->nullable();
                $table->text('effective_caller_id_number')->nullable();
                $table->text('outbound_caller_id_name')->nullable();
                $table->text('outbound_caller_id_number')->nullable();
                $table->text('emergency_caller_id_name')->nullable();
                $table->text('emergency_caller_id_number')->nullable();
                $table->text('directory_first_name')->nullable();
                $table->text('directory_last_name')->nullable();
                $table->text('directory_visible')->nullable();
                $table->text('directory_exten_visible')->nullable();
                $table->text('max_registrations')->nullable();
                $table->text('limit_max')->nullable();
                $table->text('limit_destination')->nullable();
                $table->text('missed_call_app')->nullable();
                $table->text('missed_call_data')->nullable();
                $table->text('user_context')->nullable()->index();
                $table->text('toll_allow')->nullable();
                $table->decimal('call_timeout', 20, 0)->nullable();
                $table->text('call_group')->nullable()->index();
                $table->text('call_screen_enabled')->nullable();
                $table->text('user_record')->nullable();
                $table->text('hold_music')->nullable();
                $table->text('auth_acl')->nullable();
                $table->text('cidr')->nullable();
                $table->text('sip_force_contact')->nullable();
                $table->text('nibble_account')->nullable();
                $table->decimal('sip_force_expires', 20, 0)->nullable();
                $table->text('mwi_account')->nullable();
                $table->text('sip_bypass_media')->nullable();
                $table->decimal('unique_id', 20, 0)->nullable();
                $table->text('dial_string')->nullable();
                $table->text('dial_user')->nullable();
                $table->text('dial_domain')->nullable();
                $table->text('do_not_disturb')->nullable();
                $table->text('forward_all_destination')->nullable();
                $table->text('forward_all_enabled')->nullable();
                $table->text('forward_busy_destination')->nullable();
                $table->text('forward_busy_enabled')->nullable();
                $table->text('forward_no_answer_destination')->nullable();
                $table->text('forward_no_answer_enabled')->nullable();
                $table->text('forward_user_not_registered_destination')->nullable();
                $table->text('forward_user_not_registered_enabled')->nullable();
                $table->uuid('follow_me_uuid')->nullable();
                $table->text('follow_me_enabled')->nullable();
                $table->text('follow_me_destinations')->nullable();
                $table->text('extension_type')->nullable();
                $table->text('enabled')->nullable();
                $table->text('description')->nullable()->index();
                $table->text('absolute_codec_string')->nullable();
                $table->text('force_ping')->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();

                $table->index(['domain_uuid', 'extension']);
            });
        }

        if (!Schema::hasTable('v_extension_users')) {
            Schema::create('v_extension_users', function (Blueprint $table) {
                $table->uuid('extension_user_uuid')->primary();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('extension_uuid')->nullable()->index();
                $table->uuid('user_uuid')->nullable()->index();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    private function ensureRingGroupsSchema(): void
    {
        $this->ensureNaturalSortFunction();

        if (!Schema::hasTable('v_ring_groups')) {
            Schema::create('v_ring_groups', function (Blueprint $table) {
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('ring_group_uuid')->primary();
                $table->text('ring_group_name')->nullable()->index();
                $table->text('ring_group_extension')->nullable()->index();
                $table->text('ring_group_greeting')->nullable();
                $table->text('ring_group_context')->nullable()->index();
                $table->decimal('ring_group_call_timeout', 20, 0)->nullable();
                $table->text('ring_group_forward_destination')->nullable();
                $table->text('ring_group_forward_enabled')->nullable();
                $table->text('ring_group_caller_id_name')->nullable();
                $table->text('ring_group_caller_id_number')->nullable();
                $table->text('ring_group_cid_name_prefix')->nullable();
                $table->text('ring_group_cid_number_prefix')->nullable();
                $table->text('ring_group_strategy')->nullable()->index();
                $table->text('ring_group_timeout_app')->nullable();
                $table->text('ring_group_timeout_data')->nullable();
                $table->text('ring_group_distinctive_ring')->nullable();
                $table->text('ring_group_ringback')->nullable();
                $table->text('ring_group_call_forward_enabled')->nullable();
                $table->text('ring_group_follow_me_enabled')->nullable();
                $table->text('ring_group_missed_call_app')->nullable();
                $table->text('ring_group_missed_call_data')->nullable();
                $table->text('ring_group_enabled')->nullable();
                $table->text('ring_group_description')->nullable()->index();
                $table->uuid('dialplan_uuid')->nullable()->index();
                $table->text('ring_group_forward_toll_allow')->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();

                $table->index(['domain_uuid', 'ring_group_extension']);
            });
        }

        if (!Schema::hasTable('v_ring_group_destinations')) {
            Schema::create('v_ring_group_destinations', function (Blueprint $table) {
                $table->uuid('ring_group_destination_uuid')->primary();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('ring_group_uuid')->nullable()->index();
                $table->text('destination_number')->nullable()->index();
                $table->decimal('destination_delay', 20, 0)->nullable();
                $table->decimal('destination_timeout', 20, 0)->nullable();
                $table->boolean('destination_enabled')->nullable();
                $table->decimal('destination_prompt', 20, 0)->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }

        if (!Schema::hasTable('v_ring_group_users')) {
            Schema::create('v_ring_group_users', function (Blueprint $table) {
                $table->uuid('ring_group_user_uuid')->primary();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('ring_group_uuid')->nullable()->index();
                $table->uuid('user_uuid')->nullable()->index();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    private function ensureIvrMenusSchema(): void
    {
        $this->ensureNaturalSortFunction();

        if (!Schema::hasTable('v_ivr_menus')) {
            Schema::create('v_ivr_menus', function (Blueprint $table) {
                $table->uuid('ivr_menu_uuid')->primary();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('dialplan_uuid')->nullable()->index();
                $table->text('ivr_menu_name')->nullable()->index();
                $table->text('ivr_menu_extension')->nullable()->index();
                $table->uuid('ivr_menu_parent_uuid')->nullable()->index();
                $table->text('ivr_menu_language')->nullable();
                $table->text('ivr_menu_dialect')->nullable();
                $table->text('ivr_menu_voice')->nullable();
                $table->text('ivr_menu_greet_long')->nullable();
                $table->text('ivr_menu_greet_short')->nullable();
                $table->text('ivr_menu_invalid_sound')->nullable();
                $table->text('ivr_menu_exit_sound')->nullable();
                $table->text('ivr_menu_pin_number')->nullable();
                $table->text('ivr_menu_confirm_macro')->nullable();
                $table->text('ivr_menu_confirm_key')->nullable();
                $table->text('ivr_menu_tts_engine')->nullable();
                $table->text('ivr_menu_tts_voice')->nullable();
                $table->decimal('ivr_menu_confirm_attempts', 20, 0)->nullable();
                $table->decimal('ivr_menu_timeout', 20, 0)->nullable();
                $table->text('ivr_menu_exit_app')->nullable();
                $table->text('ivr_menu_exit_data')->nullable();
                $table->decimal('ivr_menu_inter_digit_timeout', 20, 0)->nullable();
                $table->decimal('ivr_menu_max_failures', 20, 0)->nullable();
                $table->decimal('ivr_menu_max_timeouts', 20, 0)->nullable();
                $table->decimal('ivr_menu_digit_len', 20, 0)->nullable();
                $table->text('ivr_menu_direct_dial')->nullable();
                $table->text('ivr_menu_ringback')->nullable();
                $table->text('ivr_menu_cid_prefix')->nullable();
                $table->text('ivr_menu_context')->nullable()->index();
                $table->text('ivr_menu_enabled')->nullable();
                $table->text('ivr_menu_description')->nullable()->index();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();

                $table->index(['domain_uuid', 'ivr_menu_extension']);
            });
        }

        if (!Schema::hasTable('v_ivr_menu_options')) {
            Schema::create('v_ivr_menu_options', function (Blueprint $table) {
                $table->uuid('ivr_menu_option_uuid')->primary();
                $table->uuid('ivr_menu_uuid')->nullable()->index();
                $table->uuid('domain_uuid')->nullable()->index();
                $table->text('ivr_menu_option_digits')->nullable()->index();
                $table->text('ivr_menu_option_action')->nullable();
                $table->text('ivr_menu_option_param')->nullable();
                $table->decimal('ivr_menu_option_order', 20, 0)->nullable();
                $table->text('ivr_menu_option_description')->nullable();
                $table->boolean('ivr_menu_option_enabled')->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    private function ensureFollowMeSchema(): void
    {
        if (!Schema::hasTable('v_follow_me')) {
            Schema::create('v_follow_me', function (Blueprint $table) {
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('follow_me_uuid')->primary();
                $table->text('cid_name_prefix')->nullable();
                $table->text('cid_number_prefix')->nullable();
                $table->text('dial_string')->nullable();
                $table->text('follow_me_enabled')->nullable();
                $table->text('follow_me_ignore_busy')->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }

        if (!Schema::hasTable('v_follow_me_destinations')) {
            Schema::create('v_follow_me_destinations', function (Blueprint $table) {
                $table->uuid('domain_uuid')->nullable()->index();
                $table->uuid('follow_me_uuid')->nullable()->index();
                $table->uuid('follow_me_destination_uuid')->primary();
                $table->text('follow_me_destination')->nullable()->index();
                $table->decimal('follow_me_delay', 20, 0)->nullable();
                $table->decimal('follow_me_timeout', 20, 0)->nullable();
                $table->text('follow_me_prompt')->nullable();
                $table->decimal('follow_me_order', 20, 0)->nullable();
                $table->timestampTz('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestampTz('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }
    }

    private function ensureNaturalSortFunction(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $function = DB::selectOne("select to_regprocedure('public.natural_sort(text)') as function_name");

        if (!empty($function?->function_name)) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.natural_sort(text)
RETURNS bytea LANGUAGE sql IMMUTABLE STRICT AS $f$
SELECT string_agg(convert_to(coalesce(r[2], length(length(r[1])::text) || length(r[1])::text || r[1]), 'UTF8'), '\x00')
FROM regexp_matches($1, '0*([0-9]+)|([^0-9]+)', 'g') r;
$f$;
SQL);
    }

    private function seedExtensionPermissions(): void
    {
        $this->seedPermissions($this->extensionPermissions(), 'Extensions', self::EXTENSIONS_APP_UUID);
    }

    private function seedExtensionDefaultSettings(): void
    {
        $this->seedDefaultSettings($this->extensionDefaultSettings(), self::EXTENSIONS_APP_UUID);
    }

    private function seedRingGroupPermissions(): void
    {
        $this->seedPermissions($this->ringGroupPermissions(), 'Ring Groups', self::RING_GROUPS_APP_UUID);
    }

    private function seedRingGroupDefaultSettings(): void
    {
        $this->seedDefaultSettings($this->ringGroupDefaultSettings(), self::RING_GROUPS_APP_UUID);
    }

    private function seedIvrMenuPermissions(): void
    {
        $this->seedPermissions($this->ivrMenuPermissions(), 'IVR Menus', self::IVR_MENUS_APP_UUID);
    }

    private function seedIvrMenuDefaultSettings(): void
    {
        $this->seedDefaultSettings($this->ivrMenuDefaultSettings(), self::IVR_MENUS_APP_UUID);
    }

    private function seedFollowMePermissions(): void
    {
        $this->seedPermissions($this->followMePermissions(), 'Follow Me', self::FOLLOW_ME_APP_UUID);
    }

    private function seedFollowMeDefaultSettings(): void
    {
        $this->seedDefaultSettings($this->followMeDefaultSettings(), self::FOLLOW_ME_APP_UUID);
    }

    private function seedPermissions(array $permissions, string $applicationName, string $appUuid): void
    {
        if (!Schema::hasTable('v_permissions')) {
            return;
        }

        $permissionNames = array_keys($permissions);
        $now = now();

        $existingPermissions = DB::table('v_permissions')
            ->whereIn('permission_name', $permissionNames)
            ->pluck('permission_name')
            ->all();

        $permissionRows = [];
        foreach ($permissionNames as $permissionName) {
            if (in_array($permissionName, $existingPermissions, true)) {
                continue;
            }

            $row = [
                'permission_uuid' => (string) Str::uuid(),
                'permission_name' => $permissionName,
                'application_name' => $applicationName,
                'insert_date' => $now,
            ];

            if (Schema::hasColumn('v_permissions', 'application_uuid')) {
                $row['application_uuid'] = $appUuid;
            }

            $permissionRows[] = $row;
        }

        if ($permissionRows !== []) {
            DB::table('v_permissions')->insert($permissionRows);
        }

        if (!Schema::hasTable('v_group_permissions') || !Schema::hasTable('v_groups')) {
            return;
        }

        $groups = DB::table('v_groups')
            ->whereIn('group_name', ['superadmin', 'admin', 'user', 'agent'])
            ->pluck('group_uuid', 'group_name');

        if ($groups->isEmpty()) {
            return;
        }

        $existingGroupPermissions = DB::table('v_group_permissions')
            ->whereIn('group_uuid', $groups->values()->all())
            ->whereIn('permission_name', $permissionNames)
            ->get(['group_uuid', 'permission_name'])
            ->mapWithKeys(fn ($row) => [$row->group_uuid . '|' . $row->permission_name => true]);

        $groupPermissionRows = [];
        foreach ($permissions as $permissionName => $groupNames) {
            foreach ($groupNames as $groupName) {
                $groupUuid = $groups[$groupName] ?? null;
                if (!$groupUuid || $existingGroupPermissions->has($groupUuid . '|' . $permissionName)) {
                    continue;
                }

                $row = [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $groupUuid,
                    'group_name' => $groupName,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'false',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ];

                if (Schema::hasColumn('v_group_permissions', 'domain_uuid')) {
                    $row['domain_uuid'] = null;
                }

                $groupPermissionRows[] = $row;
            }
        }

        if ($groupPermissionRows !== []) {
            DB::table('v_group_permissions')->insert($groupPermissionRows);
        }
    }

    private function seedDefaultSettings(array $settings, string $appUuid): void
    {
        if (!Schema::hasTable('v_default_settings')) {
            return;
        }

        foreach ($settings as $setting) {
            $exists = DB::table('v_default_settings')
                ->where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->where('default_setting_name', $setting['default_setting_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            $row = array_merge($setting, ['insert_date' => now()]);

            if (Schema::hasColumn('v_default_settings', 'app_uuid')) {
                $row['app_uuid'] = $appUuid;
            }

            DB::table('v_default_settings')->insert($row);
        }
    }

    private function extensionPermissions(): array
    {
        return [
            'extension_view' => ['superadmin', 'admin'],
            'extension_add' => ['superadmin', 'admin'],
            'extension_edit' => ['superadmin', 'admin'],
            'extension_delete' => ['superadmin', 'admin'],
            'extension_extension' => ['superadmin', 'admin'],
            'number_alias' => [],
            'extension_toll' => ['superadmin'],
            'extension_call_screen' => ['superadmin', 'admin'],
            'extension_import' => ['superadmin'],
            'extension_caller_id' => ['superadmin', 'admin', 'user'],
            'outbound_caller_id_select' => [],
            'extension_domain' => ['superadmin'],
            'extension_enabled' => ['superadmin', 'admin'],
            'extension_user_view' => ['superadmin', 'admin'],
            'extension_user_add' => ['superadmin', 'admin'],
            'extension_user_edit' => ['superadmin', 'admin'],
            'extension_user_delete' => ['superadmin', 'admin'],
            'extension_dial_string' => ['superadmin'],
            'extension_password' => ['superadmin', 'admin'],
            'effective_caller_id_name' => ['superadmin', 'admin'],
            'effective_caller_id_number' => ['superadmin', 'admin'],
            'outbound_caller_id_name' => ['superadmin'],
            'outbound_caller_id_number' => ['superadmin'],
            'emergency_caller_id_name' => ['superadmin'],
            'emergency_caller_id_number' => ['superadmin'],
            'emergency_caller_id_select' => [],
            'emergency_caller_id_select_empty' => ['superadmin'],
            'extension_user_record' => ['superadmin'],
            'extension_missed_call' => ['superadmin', 'admin'],
            'extension_accountcode' => ['superadmin', 'admin'],
            'extension_nibble_account' => [],
            'extension_user_context' => ['superadmin'],
            'extension_cidr' => ['superadmin'],
            'extension_absolute_codec_string' => ['superadmin'],
            'extension_registered' => [],
            'extension_force_ping' => ['superadmin', 'admin'],
            'extension_all' => ['superadmin'],
            'extension_copy' => ['superadmin'],
            'extension_export' => ['superadmin'],
            'extension_advanced' => ['superadmin'],
            'extension_destinations' => ['superadmin', 'admin', 'user'],
            'extension_directory' => ['superadmin', 'admin', 'user'],
            'extension_max_registrations' => ['superadmin'],
            'extension_limit' => ['superadmin', 'admin', 'user'],
            'extension_call_group' => ['superadmin', 'admin', 'user'],
            'extension_hold_music' => ['superadmin', 'admin', 'user'],
            'extension_type' => ['superadmin', 'admin', 'user'],
        ];
    }

    private function ringGroupPermissions(): array
    {
        return [
            'ring_group_view' => ['superadmin', 'admin', 'user'],
            'ring_group_add' => ['superadmin', 'admin'],
            'ring_group_edit' => ['superadmin', 'admin'],
            'ring_group_delete' => ['superadmin', 'admin'],
            'ring_group_forward' => ['superadmin', 'admin'],
            'ring_group_prompt' => ['superadmin', 'admin'],
            'ring_group_destination_view' => ['superadmin', 'admin'],
            'ring_group_destination_add' => ['superadmin', 'admin'],
            'ring_group_destination_edit' => ['superadmin', 'admin'],
            'ring_group_destination_delete' => ['superadmin', 'admin'],
            'ring_group_user_view' => ['superadmin', 'admin'],
            'ring_group_user_add' => ['superadmin', 'admin'],
            'ring_group_user_edit' => ['superadmin', 'admin'],
            'ring_group_user_delete' => ['superadmin', 'admin'],
            'ring_group_missed_call' => ['superadmin', 'admin'],
            'ring_group_forward_toll_allow' => ['superadmin', 'admin'],
            'ring_group_caller_id_name' => ['superadmin'],
            'ring_group_caller_id_number' => ['superadmin'],
            'ring_group_cid_name_prefix' => [],
            'ring_group_cid_number_prefix' => [],
            'ring_group_context' => ['superadmin'],
            'ring_group_domain' => ['superadmin', 'admin'],
            'ring_group_all' => ['superadmin'],
            'ring_group_destinations' => ['superadmin', 'admin'],
        ];
    }

    private function ivrMenuPermissions(): array
    {
        return [
            'ivr_menu_view' => ['admin', 'superadmin'],
            'ivr_menu_add' => ['admin', 'superadmin'],
            'ivr_menu_edit' => ['admin', 'superadmin'],
            'ivr_menu_delete' => ['admin', 'superadmin'],
            'ivr_menu_all' => ['superadmin'],
            'ivr_menu_option_view' => ['superadmin', 'admin'],
            'ivr_menu_option_add' => ['superadmin', 'admin'],
            'ivr_menu_option_edit' => ['superadmin', 'admin'],
            'ivr_menu_option_delete' => ['superadmin', 'admin'],
            'ivr_menu_context' => ['superadmin'],
            'ivr_menu_domain' => ['superadmin'],
            'ivr_menu_destinations' => ['superadmin', 'admin'],
            'ivr_menus_sub_destinations' => ['superadmin', 'admin'],
            'ivr_menus_other_destinations' => ['superadmin', 'admin'],
        ];
    }

    private function followMePermissions(): array
    {
        return [
            'follow_me_view' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_add' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_edit' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_delete' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_destination_view' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_destination_add' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_destination_edit' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_destination_delete' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_ignore_busy' => ['superadmin', 'admin', 'user', 'agent'],
            'follow_me_cid_name_prefix' => [],
            'follow_me_cid_number_prefix' => [],
            'follow_me_prompt' => ['superadmin', 'admin', 'user', 'agent'],
        ];
    }

    private function extensionDefaultSettings(): array
    {
        return [
            [
                'default_setting_uuid' => 'db3290e5-d3fb-4bcd-b2a8-a0061aa02532',
                'default_setting_category' => 'domain',
                'default_setting_subcategory' => 'dial_string',
                'default_setting_name' => 'text',
                'default_setting_value' => '{sip_invite_domain=${domain_name},leg_timeout=${call_timeout},presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(*/${dialed_user}@${dialed_domain})}',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '3eeb3757-f7bb-437a-9021-8ccf3f27c98b',
                'default_setting_category' => 'limit',
                'default_setting_subcategory' => 'extensions',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '3',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '57d81b35-bc24-4e92-8436-4335ab5e9d0b',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_length',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '20',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Set the length for generated passwords for extensions.',
            ],
            [
                'default_setting_uuid' => '0fa21a56-7515-4c65-b5f8-270cc24ea375',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_number',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'false',
                'default_setting_description' => 'Set whether to require at least one number in extension passwords.',
            ],
            [
                'default_setting_uuid' => 'd5f9acbd-857c-42eb-9e9a-92a850fcb734',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_lowercase',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Set whether to require at least one lowercase letter in extension passwords.',
            ],
            [
                'default_setting_uuid' => '256b0d87-a43e-4618-b96b-541e191879c7',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_uppercase',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'false',
                'default_setting_description' => 'Set whether to require at least one uppercase letter in extension passwords.',
            ],
            [
                'default_setting_uuid' => '4209ff1f-6ea8-4b77-81be-bd75e8670785',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_special',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'false',
                'default_setting_description' => 'Set whether to require at least one special character in extension passwords.',
            ],
            [
                'default_setting_uuid' => '718b1641-fa3c-4861-b1f3-40635c951888',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'password_strength',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '4',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Set the strength for generated passwords. Valid Options: 1 - Numeric Only, 2 - Include Lower Alpha, 3 - Include Upper Alpha, 4 - Include Special Characters.',
            ],
            [
                'default_setting_uuid' => '33914c55-9081-4b95-b62e-f1a500088d78',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'session_rotate',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Whether to regenerate the session ID.',
            ],
            [
                'default_setting_uuid' => 'b831ac5a-20f6-4e77-af43-a3c697bfe550',
                'default_setting_category' => 'extension',
                'default_setting_subcategory' => 'user_record_default',
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Default value to set whether to record inbound, outbound, or all calls.',
            ],
            [
                'default_setting_uuid' => '40aac087-c335-44fa-9302-aaddb4bcca00',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'caller_id_chart_color_undefined',
                'default_setting_name' => 'text',
                'default_setting_value' => '#ea4c46',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '103a5aaa-00f8-4cbe-9988-0469a9b9e65d',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'caller_id_chart_color_defined',
                'default_setting_name' => 'text',
                'default_setting_value' => '#d4d4d4',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '71b2a34c-86df-4ada-b7fc-70e4048dd202',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'caller_id_chart_border_color',
                'default_setting_name' => 'text',
                'default_setting_value' => 'rgba(0,0,0,0)',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '7a96f83b-c408-418b-99b5-077fa11b7cf5',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'caller_id_chart_border_width',
                'default_setting_name' => 'text',
                'default_setting_value' => '0',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
        ];
    }

    private function ringGroupDefaultSettings(): array
    {
        return [
            [
                'default_setting_uuid' => '745d8fdc-57bc-4f43-97d7-508fda8f70a8',
                'default_setting_category' => 'ring_group',
                'default_setting_subcategory' => 'destination_add_rows',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '5',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'ddf306c9-6f58-40f7-910e-2f27dc33fa57',
                'default_setting_category' => 'ring_group',
                'default_setting_subcategory' => 'destination_edit_rows',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '1',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '9917d8e3-1c3c-4771-b2c6-e931c448d6e0',
                'default_setting_category' => 'ring_group',
                'default_setting_subcategory' => 'destination_delay_max',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '999',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'c54fc772-7aa5-40de-8da8-39e0e707658e',
                'default_setting_category' => 'ring_group',
                'default_setting_subcategory' => 'destination_timeout_max',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '999',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'de655030-ae71-4b53-8068-5cf0b14cf635',
                'default_setting_category' => 'limit',
                'default_setting_subcategory' => 'ring_groups',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '3',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '79aec8c7-c9ae-48d6-b343-4359f4f867c9',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'ring_group_forward_chart_color_forwarding',
                'default_setting_name' => 'text',
                'default_setting_value' => '#ea4c46',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '3b23cd36-b1c1-45fd-a533-8d9604dcb46a',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'ring_group_forward_chart_color_active',
                'default_setting_name' => 'text',
                'default_setting_value' => '#d4d4d4',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'abc10faf-7277-40cd-a7b3-0df4ea6c6c2b',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'ring_group_forward_chart_border_color',
                'default_setting_name' => 'text',
                'default_setting_value' => 'rgba(0,0,0,0)',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '506d4482-dee8-4047-9e1c-4375c9a521bb',
                'default_setting_category' => 'dashboard',
                'default_setting_subcategory' => 'ring_group_forward_chart_border_width',
                'default_setting_name' => 'text',
                'default_setting_value' => '0',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '88297698-339d-4971-8019-3f7095ec1f33',
                'default_setting_category' => 'ring_group',
                'default_setting_subcategory' => 'destination_range_enabled',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'false',
                'default_setting_description' => 'Enable or disable the feature to add a range of extensions.',
            ],
        ];
    }

    private function ivrMenuDefaultSettings(): array
    {
        return [
            [
                'default_setting_uuid' => 'ce17d7af-650a-49c0-b3e4-3bb8c1dad566',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'option_add_rows',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '5',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '74376817-89de-49e1-bddd-868a8ebb49ec',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'option_edit_rows',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '1',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'ac4ff01d-51a2-433f-9243-be9b6dd8ba9c',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'direct_dial_digits_min',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '2',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '35772199-3dd3-4c8e-9651-a50c885602d9',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'direct_dial_digits_max',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '11',
                'default_setting_enabled' => 'true',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '1012fd92-acf9-4ce9-89da-f02d4cd0e794',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'confirm_attempts',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '1',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '028bab4e-60e3-48d0-8892-4f678c8b72af',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'inter_digit_timeout',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '2000',
                'default_setting_enabled' => 'false',
                'default_setting_description' => 'Digit timeout in milliseconds',
            ],
            [
                'default_setting_uuid' => 'f5829fb7-8bd6-4c42-af16-565882fad7ca',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'max_failures',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '1',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '46dc3c7b-8a8d-4957-b4bd-27708c933af9',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'max_timeouts',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '1',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => '26984efd-2445-4ac9-b459-bb7bda4217c6',
                'default_setting_category' => 'limit',
                'default_setting_subcategory' => 'ivr_menus',
                'default_setting_name' => 'numeric',
                'default_setting_value' => '3',
                'default_setting_enabled' => 'false',
                'default_setting_description' => '',
            ],
            [
                'default_setting_uuid' => 'e04151d4-f055-4cc8-a97c-84dac6add3fa',
                'default_setting_category' => 'ivr_menu',
                'default_setting_subcategory' => 'answer',
                'default_setting_name' => 'boolean',
                'default_setting_value' => 'true',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Add answer to IVR Menu dialplan.',
            ],
        ];
    }

    private function followMeDefaultSettings(): array
    {
        return [
            [
                'default_setting_uuid' => 'ee68b6d6-a510-48c4-bb71-4e65fae8a5e5',
                'default_setting_category' => 'follow_me',
                'default_setting_subcategory' => 'strategy',
                'default_setting_name' => 'text',
                'default_setting_value' => 'enterprise',
                'default_setting_enabled' => 'true',
                'default_setting_description' => 'Options: simultaneous, enterprise ',
            ],
        ];
    }
}
