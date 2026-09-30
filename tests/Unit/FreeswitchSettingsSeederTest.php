<?php

namespace Tests\Unit;

use App\Console\Commands\PrepareFreeswitchRestart;
use App\Models\DefaultSettings;
use App\Models\FusionCache;
use App\Models\SwitchVariable;
use App\Services\FreeswitchEslService;
use Database\Seeders\FreeswitchSettingsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class FreeswitchSettingsSeederTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_name');
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
            $table->text('default_setting_description')->nullable();
        });

        Schema::create('v_vars', function (Blueprint $table) {
            $table->string('var_uuid')->primary();
            $table->string('var_category');
            $table->string('var_name');
            $table->text('var_value');
            $table->string('var_command')->nullable();
            $table->string('var_hostname')->nullable();
            $table->string('var_enabled');
            $table->integer('var_order')->nullable();
        });

        $this->directory = sys_get_temp_dir().'/fspbx-fresh-settings-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        mkdir($this->directory.'/cache');
        $this->setCacheSetting('cacheType', 'file');
        $this->setCacheSetting('cacheLocation', $this->directory.'/cache');

        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturnFalse();
        $esl->shouldNotReceive('executeCommand');
        $this->app->instance(FreeswitchEslService::class, $esl);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::deleteDirectory($this->directory);
        $this->setCacheSetting('cacheType', null);
        $this->setCacheSetting('cacheLocation', null);
        parent::tearDown();
    }

    public function test_empty_database_gets_installer_paths_without_a_running_switch(): void
    {
        $this->artisan('db:seed', ['--class' => FreeswitchSettingsSeeder::class, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame('/etc/freeswitch', $this->setting('conf')->default_setting_value);
        $this->assertSame('/usr/lib/freeswitch/mod', $this->setting('mod')->default_setting_value);
        $this->assertSame('/usr/share/freeswitch/scripts', $this->setting('scripts')->default_setting_value);
        $this->assertSame('/var/lib/freeswitch/db', $this->setting('db')->default_setting_value);
        $this->assertSame('/var/lib/freeswitch/storage/voicemail', $this->setting('voicemail')->default_setting_value);
        $this->assertSame('true', $this->setting('conf')->default_setting_enabled);
        foreach (['call_center', 'dialplan', 'extensions', 'sip_profiles'] as $name) {
            $this->assertSame('false', $this->setting($name)->default_setting_enabled);
        }

        $rows = DefaultSettings::all()->toArray();
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new FreeswitchSettingsSeeder)->run();
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete)\b/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $this->assertSame($rows, DefaultSettings::all()->toArray());
    }

    public function test_all_settings_match_the_captured_legacy_output(): void
    {
        // Captured by executing the original class with the Debian installer's
        // FreeSWITCH globals and a fake database, before removing the class.
        $fixture = json_decode(File::get(base_path('tests/Fixtures/freeswitch/legacy-directory-settings.json')), true, 512, JSON_THROW_ON_ERROR);
        (new FreeswitchSettingsSeeder)->run();

        $columns = array_keys($fixture['settings'][0]);
        $actual = DefaultSettings::query()->get($columns)->keyBy('default_setting_subcategory')->sortKeys()->toArray();
        $expected = collect($fixture['settings'])->keyBy('default_setting_subcategory')->sortKeys()->toArray();

        $this->assertCount(17, $actual);
        $this->assertSame($expected, $actual);
    }

    public function test_retry_repairs_empty_paths_left_by_offline_legacy_discovery(): void
    {
        $conf = $this->createSetting('conf', '');
        $this->createSetting('storage', null);
        $this->createSetting('mod', '  ');
        $this->createSetting('call_center', '/autoload_configs', 'false');
        $this->createSetting('dialplan', '/dialplan', 'false');
        $this->createSetting('extensions', '/directory', 'false');
        $this->createSetting('languages', '/languages');
        $this->createSetting('sip_profiles', '/sip_profiles', 'false');
        $this->createSetting('voicemail', '/voicemail');
        $this->createSetting('db', '/dev/shm');
        $this->createSetting('bin', '', 'false');

        (new FreeswitchSettingsSeeder)->run();

        $this->assertSame($conf->getKey(), $this->setting('conf')->getKey());
        $this->assertSame('/etc/freeswitch', $this->setting('conf')->default_setting_value);
        $this->assertSame('/usr/lib/freeswitch/mod', $this->setting('mod')->default_setting_value);
        foreach (['call_center' => 'autoload_configs', 'dialplan' => 'dialplan', 'extensions' => 'directory', 'languages' => 'languages', 'sip_profiles' => 'sip_profiles'] as $name => $suffix) {
            $this->assertSame('/etc/freeswitch/'.$suffix, $this->setting($name)->default_setting_value);
        }
        $this->assertSame('/var/lib/freeswitch/storage/voicemail', $this->setting('voicemail')->default_setting_value);
        $this->assertSame('false', $this->setting('sip_profiles')->default_setting_enabled);
        $this->assertSame('/dev/shm', $this->setting('db')->default_setting_value);
    }

    public function test_existing_paths_and_unrelated_settings_are_preserved(): void
    {
        $this->createSetting('conf', $this->directory.'/conf');
        $this->createSetting('storage', $this->directory.'/storage');
        $this->createSetting('recordings', '/srv/recordings');
        $this->createSetting('mod', '/opt/freeswitch/mod');
        $this->createSetting('db', '/dev/shm');
        $this->createSetting('dialplan', '/dialplan', 'false');
        $this->createSetting('custom', '/srv/custom', 'false');
        $rows = DefaultSettings::all()->toArray();

        (new FreeswitchSettingsSeeder)->run();

        foreach ($rows as $row) {
            $this->assertSame($row, DefaultSettings::findOrFail($row['default_setting_uuid'])->toArray());
        }
        $this->assertSame($this->directory.'/conf/languages', $this->setting('languages')->default_setting_value);
        $this->assertSame($this->directory.'/storage/voicemail', $this->setting('voicemail')->default_setting_value);
    }

    public function test_seeded_paths_allow_real_variable_generation_and_cache_cleanup_without_esl(): void
    {
        $this->createSetting('conf', '');
        file_put_contents($this->directory.'/cache/configuration.sofia.conf', 'stale XML');
        $this->assertSame(1, $this->prepareRestart());
        $this->assertFileExists($this->directory.'/cache/configuration.sofia.conf');

        (new FreeswitchSettingsSeeder)->run();
        // Redirect the repaired path into this test's filesystem before writing XML.
        $this->setting('conf')->update(['default_setting_value' => $this->directory.'/conf']);
        foreach (['dsn' => 'sqlite:///dev/shm/core.db', 'dsn_callcenter' => 'sqlite:///dev/shm/callcenter.db'] as $name => $value) {
            SwitchVariable::create([
                'var_uuid' => (string) Str::uuid(),
                'var_category' => 'DSN',
                'var_name' => $name,
                'var_value' => $value,
                'var_command' => 'set',
                'var_enabled' => 'true',
            ]);
        }

        $this->assertSame(0, $this->prepareRestart());
        $xml = simplexml_load_string('<variables>'.file_get_contents($this->directory.'/conf/vars.xml').'</variables>');
        $this->assertNotFalse($xml);
        $this->assertCount(2, $xml->{'X-PRE-PROCESS'});
        $this->assertSame('dsn=sqlite:///dev/shm/core.db', (string) $xml->{'X-PRE-PROCESS'}[0]['data']);
        $this->assertSame('dsn_callcenter=sqlite:///dev/shm/callcenter.db', (string) $xml->{'X-PRE-PROCESS'}[1]['data']);
        $this->assertFileDoesNotExist($this->directory.'/cache/configuration.sofia.conf');
    }

    private function setting(string $name): DefaultSettings
    {
        return DefaultSettings::where('default_setting_subcategory', $name)->firstOrFail();
    }

    private function createSetting(string $name, ?string $path, string $enabled = 'true'): DefaultSettings
    {
        return DefaultSettings::create([
            'default_setting_category' => 'switch',
            'default_setting_subcategory' => $name,
            'default_setting_name' => 'dir',
            'default_setting_value' => $path,
            'default_setting_enabled' => $enabled,
        ]);
    }

    private function prepareRestart(): int
    {
        $command = new PrepareFreeswitchRestart;
        $command->setLaravel($this->app);

        return $command->run(new ArrayInput([]), new BufferedOutput);
    }

    private function setCacheSetting(string $name, ?string $value): void
    {
        $property = new ReflectionProperty(FusionCache::class, $name);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
