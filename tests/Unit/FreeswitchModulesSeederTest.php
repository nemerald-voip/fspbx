<?php

namespace Tests\Unit;

use App\Models\SwitchModule;
use App\Services\FreeswitchEslService;
use App\Services\SwitchModuleService;
use Database\Seeders\FreeswitchModulesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FreeswitchModulesSeederTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

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

        $this->directory = sys_get_temp_dir().'/fspbx-module-install-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->directory.'/conf/autoload_configs');
        File::ensureDirectoryExists($this->directory.'/mod');
        File::put($this->xmlPath(), 'original startup configuration');
        foreach (['conf', 'mod'] as $name) {
            DB::table('v_default_settings')->insert([
                'default_setting_category' => 'switch',
                'default_setting_subcategory' => $name,
                'default_setting_name' => 'dir',
                'default_setting_value' => $this->directory.'/'.$name,
                'default_setting_enabled' => 'true',
            ]);
        }

        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('isConnected');
        $esl->shouldNotReceive('executeCommand');
        $this->app->instance(FreeswitchEslService::class, $esl);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_fresh_setup_loads_loggers_early_and_preserves_other_legacy_defaults_without_esl(): void
    {
        $defaults = json_decode(File::get(resource_path('freeswitch_modules.json')), true, 512, JSON_THROW_ON_ERROR);
        $baseline = json_decode(File::get(base_path('tests/Fixtures/freeswitch/legacy-module-defaults.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (array_keys($defaults) as $name) {
            File::put($this->directory.'/mod/'.$name.'.so', '');
        }

        $this->artisan('db:seed', ['--class' => FreeswitchModulesSeeder::class, '--force' => true])->assertExitCode(0);

        $catalog = [];
        foreach (SwitchModule::query()->get() as $module) {
            $row = $module->only(['module_label', 'module_name', 'module_order', 'module_enabled', 'module_default_enabled', 'module_description', 'module_category']);
            $row['module_order'] = (int) $row['module_order'];
            if (in_array($module->module_name, ['mod_console', 'mod_logfile', 'mod_syslog'], true)) {
                $this->assertSame(50, $row['module_order']);
                // The captured legacy catalog remains unchanged; only logger order differs.
                $row['module_order'] = 400;
            }
            ksort($row);
            $catalog[$module->module_name] = $row;
        }
        ksort($catalog);
        $this->assertCount($baseline['module_count'], $catalog);
        $this->assertSame($baseline['catalog_sha256'], hash('sha256', json_encode($catalog, JSON_UNESCAPED_SLASHES)));

        $autoload = $this->autoloadNames();
        $loggers = array_slice($autoload, 0, 3);
        sort($loggers);
        $this->assertSame(['mod_console', 'mod_logfile', 'mod_syslog'], $loggers);
        $this->assertSame(['mod_commands', 'mod_memcache', 'mod_hiredis', 'mod_lua', 'mod_sofia'], array_slice($autoload, 3, 5));
        sort($autoload);
        $this->assertSame($baseline['autoload'], $autoload);
    }

    public function test_existing_choices_are_preserved_and_only_missing_orders_are_filled(): void
    {
        $commands = SwitchModule::create([
            'module_uuid' => (string) Str::uuid(),
            'module_name' => 'mod_commands',
            'module_label' => 'Custom label',
            'module_category' => 'Custom category',
            'module_order' => null,
            'module_enabled' => 'false',
            'module_default_enabled' => 'false',
            'module_description' => 'Keep this',
        ]);
        $lua = SwitchModule::create([
            'module_uuid' => (string) Str::uuid(),
            'module_name' => 'mod_lua',
            'module_order' => 17,
            'module_enabled' => 'true',
            'module_default_enabled' => 'false',
        ]);
        $before = $commands->fresh()->toArray();
        $luaBefore = $lua->fresh()->toArray();
        foreach (['mod_commands.so', 'mod_Custom_plugin.dll', 'README.txt'] as $filename) {
            File::put($this->directory.'/mod/'.$filename, '');
        }

        (new FreeswitchModulesSeeder)->run();

        $this->assertSame(array_replace($before, ['module_order' => 100]), $commands->fresh()->toArray());
        $this->assertSame($luaBefore, $lua->fresh()->toArray());
        $unknown = SwitchModule::where('module_name', 'mod_Custom_plugin')->firstOrFail();
        $this->assertSame('Custom Plugin', $unknown->module_label);
        $this->assertSame('Auto', $unknown->module_category);
        $this->assertSame('false', $unknown->module_enabled);
        $this->assertSame('false', $unknown->module_default_enabled);
        $this->assertSame(['mod_lua'], $this->autoloadNames());
        $this->assertSame(3, SwitchModule::count());

        $rows = SwitchModule::all()->toArray();
        $xml = File::get($this->xmlPath());
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new FreeswitchModulesSeeder)->run();
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete)\b/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $this->assertSame($rows, SwitchModule::all()->toArray());
        $this->assertSame($xml, File::get($this->xmlPath()));
    }

    public function test_normal_module_page_discovery_keeps_new_modules_disabled(): void
    {
        File::put($this->directory.'/mod/mod_hiredis.so', '');

        $this->assertSame(1, (new SwitchModuleService)->syncFromDisk());
        $module = SwitchModule::firstOrFail();
        $this->assertSame('false', $module->module_enabled);
        $this->assertSame('false', $module->module_default_enabled);
        $this->assertSame('original startup configuration', File::get($this->xmlPath()));
    }

    public function test_missing_module_directory_fails_before_changing_startup_xml(): void
    {
        File::deleteDirectory($this->directory.'/mod');
        try {
            (new FreeswitchModulesSeeder)->run();
            $this->fail('Expected the missing module directory to fail installation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('module directory is missing', $exception->getMessage());
        }
        $this->assertSame(0, SwitchModule::count());
        $this->assertSame('original startup configuration', File::get($this->xmlPath()));
    }

    private function xmlPath(): string
    {
        return $this->directory.'/conf/autoload_configs/modules.conf.xml';
    }

    private function autoloadNames(): array
    {
        $xml = simplexml_load_file($this->xmlPath());
        $this->assertNotFalse($xml);
        $names = [];
        foreach ($xml->modules->load as $load) {
            $names[] = (string) $load['module'];
        }

        return $names;
    }
}
