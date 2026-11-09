<?php

namespace Tests\Unit;

use App\Http\Controllers\FlagsController;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Http\Requests\UpdateVirtualReceptionistRequest;
use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Flag;
use App\Models\FlagOverride;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Models\User;
use App\Services\FlagDialplanRenderer;
use App\Services\FlagFeature;
use App\Services\FlagRuntimeService;
use App\Services\FlagService;
use App\Services\Settings\SettingsManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class FlagFeatureIntegrationTest extends TestCase
{
    private ?string $schema = null;
    private FlagRuntimeService $runtime;
    private $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('FSPBX_FLAGS_PG_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Run bash tests/Unit/run-flags-lab.sh for the disposable PostgreSQL tests.');
        }
        if (! str_starts_with($socket, '/tmp/fspbx-flags-lab.')) {
            throw new \RuntimeException('Use the disposable Flags lab, never an application database.');
        }
        $this->schema = 'flags_'.bin2hex(random_bytes(8));
        config([
            'cache.default' => 'array', 'session.driver' => 'array', 'database.default' => 'flags_test',
            'database.connections.flags_test' => [
                'driver' => 'pgsql', 'host' => $socket, 'port' => 16559,
                'database' => 'flags_test', 'username' => 'postgres', 'password' => '',
                'charset' => 'utf8', 'search_path' => $this->schema.',public',
            ],
        ]);
        DB::purge('flags_test');
        DB::statement('CREATE SCHEMA '.$this->schema);
        $this->migration = require base_path('database/migrations/2026_11_09_000002_create_flags_tables.php');
        $this->migration->up();

        $this->table('v_default_settings', 'default_setting_uuid', (new DefaultSettings())->getFillable());
        $this->table('v_dialplans', 'dialplan_uuid', (new Dialplans())->getFillable());
        $this->table('v_dialplan_details', 'dialplan_detail_uuid', (new DialplanDetails())->getFillable());
        $this->table('v_ring_groups', 'ring_group_uuid', (new RingGroups())->getFillable());
        $this->table('v_ivr_menus', 'ivr_menu_uuid', (new IvrMenus())->getFillable());
        $this->table('v_permissions', 'permission_uuid', ['application_name', 'permission_name', 'insert_date']);
        $this->table('v_groups', 'group_uuid', ['group_name']);
        $this->table('v_group_permissions', 'group_permission_uuid', [
            'group_uuid', 'group_name', 'permission_name', 'permission_protected', 'permission_assigned', 'insert_date',
        ]);
        $this->runtime = new class extends FlagRuntimeService {
            public array $refreshes = [];
            protected function refresh(array $contexts, bool $globalChanged): void
            {
                $this->refreshes[] = [$contexts, $globalChanged];
            }
        };
        $this->app->instance(FlagRuntimeService::class, $this->runtime);
        session(['domain_uuid' => (string) Str::uuid(), 'domain_name' => 'operator.example.test']);
    }

    protected function tearDown(): void
    {
        if ($this->schema !== null) {
            DB::statement('DROP SCHEMA '.$this->schema.' CASCADE');
            DB::purge('flags_test');
        }
        parent::tearDown();
    }

    public function test_fresh_and_repeated_seeding_never_enables_flags_or_grants_permissions(): void
    {
        foreach (['superadmin', 'admin', 'user'] as $group) {
            DB::table('v_groups')->insert(['group_uuid' => (string) Str::uuid(), 'group_name' => $group]);
        }
        $this->seedCatalog();
        $this->seedCatalog();
        $setting = DefaultSettings::where('default_setting_category', 'flags')->sole();
        $this->assertSame('false', $setting->default_setting_value);
        $this->assertFalse(app(FlagFeature::class)->enabled());
        $this->assertSame(6, DB::table('v_permissions')->where('permission_name', 'like', 'flags_%')->count());
        $this->assertSame(0, DB::table('v_group_permissions')->where('permission_name', 'like', 'flags_%')->count());
        $this->assertSame(0, Dialplans::count());
        $this->assertSame([], $this->runtime->refreshes);
        $this->assertStringNotContainsString('/flags', json_encode(\App\Support\DefaultMenu::categories()));

        $this->setting('true', $setting);
        DB::table('v_group_permissions')->insert([
            'group_permission_uuid' => (string) Str::uuid(), 'group_uuid' => DB::table('v_groups')->value('group_uuid'),
            'permission_name' => 'flags_list_view', 'permission_assigned' => 'true',
        ]);
        $this->seedCatalog();
        $this->assertTrue(app(FlagFeature::class)->enabled());
        $this->assertSame(1, DB::table('v_group_permissions')->where('permission_name', 'like', 'flags_%')->count());
    }

    public function test_switch_rebuilds_only_assigned_routes_across_accounts_and_preserves_data(): void
    {
        $setting = $this->setting();
        [$ring, $flag] = $this->owner('ring_group');
        [$ivr] = $this->owner('ivr_menu');
        [$unassigned] = $this->owner('ring_group', false);
        $normal = DB::table('v_dialplans')->orderBy('dialplan_uuid')->pluck('dialplan_xml', 'dialplan_uuid')->all();

        $this->setting('true', $setting);
        $this->assertTrue(app(FlagFeature::class)->enabled());
        $this->assertSame(1, count($this->runtime->refreshes));
        $this->assertSame(2, DialplanDetails::where('dialplan_uuid', FlagRuntimeService::TOGGLE_UUID)->count());
        foreach ([$ring, $ivr] as $owner) {
            $this->assertStringContainsString('flag_check.lua '.$owner->domain_uuid, Dialplans::find($owner->dialplan_uuid)->dialplan_xml);
        }
        $this->assertSame($normal[$unassigned->dialplan_uuid], Dialplans::find($unassigned->dialplan_uuid)->dialplan_xml);

        $this->setting('false', $setting);
        $this->assertFalse(app(FlagFeature::class)->enabled());
        $this->assertNull(Dialplans::find(FlagRuntimeService::TOGGLE_UUID));
        $this->assertSame(0, DialplanDetails::count());
        $this->assertSame($normal, DB::table('v_dialplans')->orderBy('dialplan_uuid')->pluck('dialplan_xml', 'dialplan_uuid')->all());
        $this->assertSame(2, FlagOverride::count());
        $this->assertTrue(Flag::find($flag->uuid)->active);

        $this->setting('true', $setting);
        $this->assertSame(3, count($this->runtime->refreshes));
        $this->setting('true', $setting);
        $this->assertSame(3, count($this->runtime->refreshes), 'Repeated saves must not reload unchanged dialplans.');
    }

    /** @dataProvider deactivations */
    public function test_disabling_deleting_or_renaming_the_setting_deactivates_saved_routes(string $operation): void
    {
        [$owner] = $this->owner('ring_group');
        $setting = $this->setting('true');
        $service = app(SettingsManagementService::class);
        if ($operation === 'delete') {
            $service->deleteDefaults([$setting->getKey()]);
        } elseif ($operation === 'toggle') {
            $service->toggleDefault([$setting->getKey()]);
        } else {
            $data = $setting->getAttributes();
            $data['default_setting_subcategory'] = 'renamed';
            $service->saveDefault($data, $setting);
        }
        $this->assertFalse(app(FlagFeature::class)->enabled());
        $this->assertNull(Dialplans::find(FlagRuntimeService::TOGGLE_UUID));
        $this->assertStringNotContainsString('flag_check', Dialplans::find($owner->dialplan_uuid)->dialplan_xml);
        $this->assertSame(1, FlagOverride::count());
        $this->assertSame(2, count($this->runtime->refreshes));
    }

    public static function deactivations(): array
    {
        return [['delete'], ['toggle'], ['rename']];
    }

    public function test_off_path_needs_no_flags_schema_and_ignores_session_overrides(): void
    {
        $this->setting();
        foreach (['flag_overrides', 'flag_extensions', 'flag_rules', 'flags'] as $table) {
            Schema::drop($table);
        }
        session(['flags.enabled.boolean' => 'true']);
        $this->assertFalse(app(FlagFeature::class)->enabled());
        $ring = new RingGroups(['domain_uuid' => session('domain_uuid'), 'ring_group_extension' => '9000', 'ring_group_name' => 'Sales']);
        $ivr = new IvrMenus(['domain_uuid' => session('domain_uuid'), 'ivr_menu_extension' => '9100', 'ivr_menu_name' => 'Main']);
        $renderer = app(FlagDialplanRenderer::class);
        $this->assertStringNotContainsString('flag_', $renderer->ringGroup($ring));
        $this->assertStringNotContainsString('flag_', $renderer->ivr($ivr));
        app(FlagService::class)->copyOverrides('ring_group', (string) Str::uuid(), (string) Str::uuid(), session('domain_uuid'));
        app(FlagService::class)->deleteOverrides('ring_group', [(string) Str::uuid()], session('domain_uuid'));

        try {
            $this->setting('true', DefaultSettings::where('default_setting_category', 'flags')->first());
            $this->fail('Enabling without migrations must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('default_setting_value', $error->errors());
        }
        $this->assertFalse(app(FlagFeature::class)->enabled());
        Schema::drop('v_default_settings');
        $this->assertFalse(app(FlagFeature::class)->enabled());
    }

    public function test_every_flags_endpoint_requires_the_switch_and_its_permission(): void
    {
        $routes = [
            ['GET', 'index', []], ['GET', 'getData', []], ['POST', 'store', []],
            ['PUT', 'update', []], ['POST', 'getItemOptions', []], ['POST', 'selectAll', []],
            ['POST', 'bulkDelete', ['items' => [(string) Str::uuid()]]],
            ['POST', 'bulkToggle', ['field' => 'active', 'items' => [(string) Str::uuid()]]],
        ];
        foreach ($routes as [$verb, $action, $payload]) {
            $this->app['router']->match([$verb], '/__flags/'.$action, [FlagsController::class, $action]);
        }
        $this->permissions(['flags_list_view', 'flags_create', 'flags_update', 'flags_delete', 'flags_toggle']);
        foreach ($routes as [$verb, $action, $payload]) {
            $this->json($verb, '/__flags/'.$action, $payload)->assertNotFound();
        }
        $this->setting('true');
        $this->permissions([]);
        foreach ($routes as [$verb, $action, $payload]) {
            $response = $this->json($verb, '/__flags/'.$action, $payload);
            $action === 'index' ? $response->assertRedirect('/') : $response->assertForbidden();
        }
    }

    public function test_assignment_requires_both_permissions_and_rejects_foreign_flags(): void
    {
        $this->actingAs(new User(['username' => 'operator']));
        foreach ([UpdateRingGroupRequest::class => 'ring_group_edit', UpdateVirtualReceptionistRequest::class => 'ivr_menu_edit'] as $requestClass => $edit) {
            $request = $requestClass::create('/', 'PUT', ['flag_overrides' => []]);
            $ordinary = $requestClass::create('/', 'PUT', []);
            $this->permissions([$edit]);
            $this->assertFalse($request->authorize());
            $this->assertTrue($ordinary->authorize());
            $this->permissions([$edit, 'flags_assign']);
            $this->assertFalse($request->authorize());
        }
        $this->setting('true');
        $this->permissions(['ring_group_edit', 'ivr_menu_edit', 'flags_assign']);
        foreach ([UpdateRingGroupRequest::class, UpdateVirtualReceptionistRequest::class] as $requestClass) {
            $this->assertTrue($requestClass::create('/', 'PUT', ['flag_overrides' => []])->authorize());
        }
        [$owner, $flag] = $this->owner('ring_group');
        $validator = Validator::make(['flag_overrides' => [['flag_uuid' => $flag->uuid, 'action' => 'hangup']]], FlagService::overrideRules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('flag_overrides.0.flag_uuid', $validator->errors()->toArray());
        session(['domain_uuid' => $owner->domain_uuid]);
        $validator = Validator::make(['flag_overrides' => [['flag_uuid' => $flag->uuid, 'action' => 'hangup']]], FlagService::overrideRules());
        $this->assertFalse($validator->fails(), json_encode($validator->errors()));
    }

    public function test_flag_endpoints_keep_accounts_isolated_and_scheduled_flags_cannot_be_toggled(): void
    {
        $this->setting('true');
        $this->permissions(['flags_list_view', 'flags_update', 'flags_delete', 'flags_toggle']);
        $own = Flag::create(['domain_uuid' => session('domain_uuid'), 'name' => 'Own', 'flag_number' => '1']);
        $foreign = Flag::create(['domain_uuid' => (string) Str::uuid(), 'name' => 'Foreign', 'flag_number' => '1']);
        $scheduled = Flag::create(['domain_uuid' => session('domain_uuid'), 'name' => 'Schedule', 'flag_number' => '2', 'type' => 'scheduled']);
        $service = \Mockery::mock(FlagService::class, [app(\App\Services\FlagStateService::class)])->makePartial();
        $service->shouldReceive('notifyBlf')->once();
        $this->app->instance(FlagService::class, $service);
        foreach (['selectAll', 'getItemOptions', 'bulkDelete', 'bulkToggle'] as $action) {
            $this->app['router']->post('/__flags/'.$action, [FlagsController::class, $action]);
        }
        $this->postJson('/__flags/selectAll')->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonMissing([$foreign->uuid]);
        $this->postJson('/__flags/getItemOptions', ['item_uuid' => $foreign->uuid])->assertNotFound();
        $this->postJson('/__flags/bulkDelete', ['items' => [$foreign->uuid]])->assertOk();
        $this->postJson('/__flags/bulkToggle', ['items' => [$foreign->uuid], 'field' => 'active'])->assertStatus(422);
        $this->postJson('/__flags/bulkToggle', ['items' => [$scheduled->uuid], 'field' => 'active'])->assertStatus(422);
        $this->assertFalse($foreign->fresh()->active);
        $this->assertFalse($scheduled->fresh()->active);

        $this->postJson('/__flags/bulkToggle', ['items' => [$own->uuid], 'field' => 'active'])->assertOk();
        $this->assertTrue($own->fresh()->active);
        $this->permissions(['flags_toggle']);
        $this->postJson('/__flags/bulkToggle', ['items' => [$own->uuid], 'field' => 'enabled'])->assertForbidden();
    }

    public function test_consolidated_migration_adopts_old_and_partial_schemas_without_losing_rows(): void
    {
        [$owner, $flag] = $this->owner('ring_group');
        $original = DB::table('flags')->first();
        Schema::table('flag_overrides', function (Blueprint $table) {
            $table->dropUnique(['owner_type', 'owner_uuid', 'flag_uuid', 'match_state']);
            $table->dropColumn('match_state');
            $table->unique(['owner_type', 'owner_uuid', 'flag_uuid']);
        });
        Schema::table('flags', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::drop('flag_extensions');
        $this->migration->up();
        $this->migration->up();
        $this->assertEquals($original, DB::table('flags')->first());
        $this->assertSame('active', FlagOverride::first()->match_state);
        $copy = FlagOverride::first()->replicate();
        $copy->uuid = (string) Str::uuid();
        $copy->match_state = 'inactive';
        $copy->save();
        $this->migration->up();
        $this->assertSame(2, FlagOverride::count());
        $this->assertSame(1, Flag::count());
        $this->assertSame(0, Dialplans::where('dialplan_uuid', FlagRuntimeService::TOGGLE_UUID)->count());
    }

    public function test_ordinary_route_edits_preserve_hidden_assignments(): void
    {
        $setting = $this->setting();
        [$ring] = $this->owner('ring_group');
        [$ivr] = $this->owner('ivr_menu');
        $saved = FlagOverride::orderBy('uuid')->get()->toArray();
        $this->permissions(['ring_group_edit', 'ivr_menu_edit']);

        foreach (['false', 'true'] as $enabled) {
            $this->setting($enabled, $setting);
            session(['domain_uuid' => $ring->domain_uuid]);
            $request = \Mockery::mock(UpdateRingGroupRequest::class);
            $request->shouldReceive('validated')->once()->andReturn(['ring_group_name' => 'Renamed Sales']);
            // Avoid live ESL through the legacy observer; XML has separate rendering tests.
            $response = RingGroups::withoutEvents(fn () => app(\App\Http\Controllers\RingGroupsController::class)->update($request, $ring));
            $this->assertSame(200, $response->status(), $response->getContent());
            $this->assertSame('Renamed Sales', $ring->fresh()->ring_group_name);

            session(['domain_uuid' => $ivr->domain_uuid]);
            $request = \Mockery::mock(UpdateVirtualReceptionistRequest::class);
            $request->shouldReceive('route')->with('virtual_receptionist')->andReturn($ivr->ivr_menu_uuid);
            $request->shouldReceive('validated')->once()->andReturn([
                'ivr_menu_name' => 'Renamed Main', 'ivr_menu_extension' => '9150', 'ivr_menu_enabled' => 'true',
                'digit_length' => '5', 'prompt_timeout' => '3', 'ring_back_tone' => null,
                'invalid_input_message' => null, 'exit_message' => null, 'direct_dial' => 'false',
                'repeat_prompt' => '3', 'caller_id_prefix' => '', 'exit_action' => 'hangup',
            ]);
            $response = app(\App\Http\Controllers\VirtualReceptionistController::class)->update($request);
            $this->assertSame(200, $response->status(), $response->getContent());
            $this->assertSame('Renamed Main', $ivr->fresh()->ivr_menu_name);
            $this->assertSame($saved, FlagOverride::orderBy('uuid')->get()->toArray());
        }
    }

    private function table(string $name, string $primary, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($primary, $columns) {
            $table->string($primary)->primary();
            foreach (array_unique($columns) as $column) {
                if ($column !== $primary) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    private function seedCatalog(): void
    {
        foreach (['createPermissions', 'createGroupPermissions', 'createDefaultSettings'] as $method) {
            (new ReflectionMethod(DatabaseSeeder::class, $method))->invoke(new DatabaseSeeder());
        }
    }

    private function permissions(array $permissions): void
    {
        session(['permissions' => array_map(fn ($permission) => (object) ['permission_name' => $permission], $permissions)]);
    }

    private function setting(string $value = 'false', ?DefaultSettings $existing = null): DefaultSettings
    {
        return app(SettingsManagementService::class)->saveDefault([
            'default_setting_category' => 'flags', 'default_setting_subcategory' => 'enabled',
            'default_setting_name' => 'boolean', 'default_setting_value' => $value,
            'default_setting_enabled' => true,
        ], $existing);
    }

    private function owner(string $type, bool $assigned = true): array
    {
        $domain = (string) Str::uuid();
        $uuid = (string) Str::uuid();
        $dialplan = (string) Str::uuid();
        if ($type === 'ring_group') {
            DB::table('v_ring_groups')->insert([
                'ring_group_uuid' => $uuid, 'domain_uuid' => $domain, 'dialplan_uuid' => $dialplan,
                'ring_group_name' => 'Sales', 'ring_group_extension' => '9000', 'ring_group_context' => $domain.'.example.test',
            ]);
            $owner = RingGroups::find($uuid);
            $xml = app(FlagDialplanRenderer::class)->ringGroup($owner);
        } else {
            DB::table('v_ivr_menus')->insert([
                'ivr_menu_uuid' => $uuid, 'domain_uuid' => $domain, 'dialplan_uuid' => $dialplan,
                'ivr_menu_name' => 'Main', 'ivr_menu_extension' => '9100', 'ivr_menu_context' => $domain.'.example.test',
            ]);
            $owner = IvrMenus::find($uuid);
            $xml = app(FlagDialplanRenderer::class)->ivr($owner);
        }
        DB::table('v_dialplans')->insert([
            'dialplan_uuid' => $dialplan, 'domain_uuid' => $domain,
            'dialplan_context' => $domain.'.example.test', 'dialplan_xml' => $xml,
        ]);
        $flag = null;
        if ($assigned) {
            $flag = Flag::create(['domain_uuid' => $domain, 'name' => 'Closed', 'flag_number' => '1', 'active' => true]);
            FlagOverride::create([
                'domain_uuid' => $domain, 'owner_type' => $type, 'owner_uuid' => $uuid,
                'flag_uuid' => $flag->uuid, 'override_app' => 'transfer', 'override_data' => '1000 XML '.$domain.'.example.test',
            ]);
        }

        return [$owner, $flag];
    }
}
