<?php

namespace Tests\Unit;

use App\Http\Controllers\SwitchModuleController;
use App\Http\Requests\SaveSwitchModuleRequest;
use App\Models\SwitchModule;
use App\Services\FreeswitchEslService;
use App\Services\SwitchModuleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SwitchModuleEditorTest extends TestCase
{
    private string $confDir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        session(['user_uuid' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa']);
        $this->permissions(['module_view', 'module_add', 'module_edit']);

        Schema::create('v_modules', function (Blueprint $table) {
            $table->uuid('module_uuid')->primary();
            foreach (['module_label', 'module_name', 'module_category', 'module_enabled', 'module_default_enabled', 'module_description'] as $column) {
                $table->text($column)->nullable();
            }
            $table->decimal('module_order')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->uuid('insert_user')->nullable();
            $table->uuid('update_user')->nullable();
        });
        Schema::create('v_default_settings', function (Blueprint $table) {
            foreach (['category', 'subcategory', 'name', 'value', 'enabled'] as $column) {
                $table->text('default_setting_'.$column);
            }
        });
        $this->confDir = sys_get_temp_dir().'/fspbx-module-editor-'.Str::uuid();
        File::makeDirectory($this->confDir.'/autoload_configs', 0755, true);
        DB::table('v_default_settings')->insert([
            'default_setting_category' => 'switch', 'default_setting_subcategory' => 'conf',
            'default_setting_name' => 'dir', 'default_setting_enabled' => 'true',
            'default_setting_value' => $this->confDir,
        ]);
        session(['switch.conf.dir' => '/this-session-path-must-not-be-used']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->confDir);
        DB::purge('sqlite');
        Mockery::close();
        parent::tearDown();
    }

    private function permissions(array $names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function values(array $overrides = []): array
    {
        return array_replace([
            'module_label' => 'Shout', 'module_name' => 'mod_shout', 'module_category' => 'Formats',
            'module_order' => null, 'module_enabled' => 'true',
            'module_description' => 'Streaming audio',
        ], $overrides);
    }

    private function esl(bool $connected, mixed $response = '+OK [Success]'): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturn($connected);
        if ($connected) {
            $esl->shouldReceive('executeCommand')->once()->with('reloadxml')->andReturn($response);
        }
        $this->app->instance(FreeswitchEslService::class, $esl);
    }

    public function test_opening_create_returns_a_draft_without_persisting_and_preserves_raw_edit_values(): void
    {
        $controller = new SwitchModuleController();
        $draft = $controller->itemOptions(Request::create('/', 'POST'))->getData(true)['item'];
        $this->assertArrayNotHasKey('module_uuid', $draft);
        $this->assertNull($draft['module_order']);
        $this->assertArrayNotHasKey('module_default_enabled', $draft);
        $this->assertSame(0, SwitchModule::count());

        $module = SwitchModule::create($this->values(['module_uuid' => (string) Str::uuid(), 'module_category' => null]));
        $loaded = $controller->itemOptions(Request::create('/', 'POST', ['itemUuid' => $module->module_uuid]))->getData(true)['item'];
        $this->assertNull($loaded['module_category']);
        $this->assertNull($loaded['module_order']);
        $this->assertSame($module->module_uuid, $loaded['module_uuid']);
        $this->assertArrayNotHasKey('module_default_enabled', $loaded);
    }

    public function test_add_and_edit_permissions_are_separate_for_requests_and_editor(): void
    {
        $this->permissions(['module_add']);
        $this->assertTrue(SaveSwitchModuleRequest::create('/', 'POST')->authorize());
        $this->assertFalse(SaveSwitchModuleRequest::create('/', 'PUT')->authorize());
        $this->permissions(['module_edit']);
        $this->assertFalse(SaveSwitchModuleRequest::create('/', 'POST')->authorize());
        $this->assertTrue(SaveSwitchModuleRequest::create('/', 'PUT')->authorize());

        $this->expectException(HttpException::class);
        (new SwitchModuleController())->itemOptions(Request::create('/', 'POST'));
    }

    /** @dataProvider runtimeStates */
    public function test_module_list_allows_runtime_control_without_autoload_when_status_is_known(bool $available, bool $running, string $status): void
    {
        SwitchModule::create($this->values(['module_uuid' => (string) Str::uuid(), 'module_enabled' => 'false']));
        $service = Mockery::mock(SwitchModuleService::class);
        $service->shouldReceive('syncFromDisk')->once()->andReturn(0);
        $service->shouldReceive('writeXml')->once()->andReturnTrue();
        $service->shouldReceive('activeModuleNames')->once()->andReturn($running ? collect(['mod_shout']) : collect());
        if (! $running) {
            $service->shouldReceive('eventSocketIsAvailable')->once()->andReturn($available);
        }

        $response = (new SwitchModuleController())->getData(Request::create('/api/modules/data'), $service)->getData(true);
        $row = $response['data'][0];
        $this->assertSame('false', $row['module_enabled']);
        $this->assertSame($status, $row['status']);
        $this->assertSame($available, $row['can_control_runtime']);
        $this->assertArrayNotHasKey('module_default_enabled', $row);
    }

    public static function runtimeStates(): array
    {
        return [[true, false, 'stopped'], [true, true, 'running'], [false, false, 'unknown']];
    }

    public function test_validation_rejects_unsafe_names_and_duplicates_but_allows_an_unchanged_name(): void
    {
        $module = SwitchModule::create($this->values(['module_uuid' => (string) Str::uuid()]));
        $request = SaveSwitchModuleRequest::create('/', 'POST');
        $this->assertTrue(Validator::make($this->values(), $request->rules())->errors()->has('module_name'));
        foreach (['mod_shout;shutdown', "mod_shout\n", '../mod_shout', 'mod_shout.so'] as $name) {
            $this->assertTrue(Validator::make($this->values(['module_name' => $name]), $request->rules())->errors()->has('module_name'));
        }

        $route = new Route('PUT', 'modules/{module}', fn () => null);
        $route->bind(Request::create('/modules/'.$module->module_uuid, 'PUT'));
        $route->setParameter('module', $module);
        $request->setRouteResolver(fn () => $route);
        $this->assertTrue(Validator::make($this->values(), $request->rules())->passes());
        $invalid = Validator::make($this->values(['module_label' => '', 'module_order' => 'bad', 'module_enabled' => 'yes']), $request->rules());
        $this->assertEqualsCanonicalizing(['module_label', 'module_order', 'module_enabled'], $invalid->errors()->keys());
    }

    public function test_save_persists_all_fields_and_regenerates_valid_xml_before_reload(): void
    {
        $this->esl(true);
        $result = (new SwitchModuleService())->save($this->values(['module_category' => 'Audio --> & <formats>']));
        $module = $result['item']->fresh();
        $this->assertTrue($result['success']);
        $this->assertTrue(Str::isUuid($module->module_uuid));
        $this->assertNull($module->module_default_enabled);
        $this->assertNull($module->module_order);
        $this->assertSame(session('user_uuid'), $module->insert_user);
        $xml = simplexml_load_file($this->confDir.'/autoload_configs/modules.conf.xml');
        $this->assertSame('mod_shout', (string) $xml->modules->load['module']);
    }

    public function test_update_disables_autoload_without_starting_or_stopping_the_running_module(): void
    {
        $module = SwitchModule::create($this->values([
            'module_uuid' => (string) Str::uuid(),
            'insert_user' => session('user_uuid'),
            'module_default_enabled' => 'true',
        ]));
        $this->esl(true);
        $result = (new SwitchModuleService())->save($this->values(['module_enabled' => 'false', 'module_label' => 'Updated']), $module);
        $this->assertTrue($result['success']);
        $this->assertSame(1, SwitchModule::count());
        $this->assertSame('Updated', $module->fresh()->module_label);
        $this->assertSame(session('user_uuid'), $module->fresh()->update_user);
        $this->assertSame('true', $module->fresh()->module_default_enabled);
        $this->assertArrayNotHasKey('module_default_enabled', $result['item']->toArray());
        $this->assertStringNotContainsString('<load ', File::get($this->confDir.'/autoload_configs/modules.conf.xml'));
    }

    /** @dataProvider legacyAutoloadPayloads */
    public function test_create_does_not_require_or_accept_the_legacy_autoload_field(array $legacyFields): void
    {
        $request = SaveSwitchModuleRequest::create('/', 'POST', $this->values($legacyFields));
        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes());
        $this->assertArrayNotHasKey('module_default_enabled', $validator->validated());
        $request->setValidator($validator);
        $this->esl(true);

        $response = (new SwitchModuleController())->store($request, new SwitchModuleService());

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertArrayNotHasKey('module_default_enabled', $response->getData(true)['item']);
        $this->assertNull(SwitchModule::firstOrFail()->module_default_enabled);
        $xml = simplexml_load_file($this->confDir.'/autoload_configs/modules.conf.xml');
        $this->assertSame('mod_shout', (string) $xml->modules->load['module']);
    }

    public static function legacyAutoloadPayloads(): array
    {
        return [
            'current editor' => [[]],
            'older editor' => [['module_default_enabled' => 'true']],
        ];
    }

    /** @dataProvider reloadFailures */
    public function test_reload_failure_reports_the_saved_record_without_claiming_runtime_success(bool $connected, mixed $response): void
    {
        $this->esl($connected, $response);
        $result = (new SwitchModuleService())->save($this->values());
        $this->assertFalse($result['success']);
        $this->assertSame(1, SwitchModule::count());
        $this->assertTrue($result['item']->exists);
        $this->assertArrayHasKey('error', $result['messages']);
        if (is_string($response) && str_starts_with($response, '-ERR')) {
            $this->assertSame([$response], $result['messages']['error_1']);
        }
    }

    public static function reloadFailures(): array
    {
        return [[false, null], [true, null], [true, '-ERR reload failed']];
    }

    public function test_xml_write_failure_is_reported_without_attempting_reload(): void
    {
        $service = Mockery::mock(SwitchModuleService::class)->makePartial();
        $service->shouldReceive('writeXml')->once()->andReturnFalse();
        $this->app->instance(FreeswitchEslService::class, Mockery::mock(FreeswitchEslService::class));
        $result = $service->save($this->values());
        $this->assertFalse($result['success']);
        $this->assertSame(1, SwitchModule::count());
        $this->assertSame(['modules.conf.xml was not writable.'], $result['messages']['error_1']);
    }
}
