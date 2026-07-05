<?php

namespace Tests\Unit;

use App\Services\Settings\SettingsManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsManagementServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-settings-service-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_order')->nullable();
            $table->string('default_setting_enabled')->nullable();
            $table->text('default_setting_description')->nullable();
        });

        Schema::create('v_domains', function (Blueprint $table) {
            $table->string('domain_uuid')->primary();
            $table->string('domain_name')->nullable();
            $table->string('domain_description')->nullable();
            $table->string('domain_enabled')->nullable();
        });

        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_order')->nullable();
            $table->string('domain_setting_enabled')->nullable();
            $table->text('domain_setting_description')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_saving_scheduled_job_default_setting_clears_scheduler_cache(): void
    {
        Cache::put('scheduled_jobs_settings', ['wake_up_calls' => 'false'], 120);

        $this->service()->saveDefault([
            'default_setting_category' => 'scheduled_jobs',
            'default_setting_subcategory' => 'wake_up_calls',
            'default_setting_name' => 'boolean',
            'default_setting_value' => 'true',
            'default_setting_order' => null,
            'default_setting_enabled' => true,
            'default_setting_description' => null,
        ]);

        $this->assertFalse(Cache::has('scheduled_jobs_settings'));
    }

    public function test_toggling_scheduled_job_default_setting_clears_scheduler_cache(): void
    {
        $settingUuid = $this->insertDefaultSetting('scheduled_jobs');
        Cache::put('scheduled_jobs_settings', ['wake_up_calls' => 'true'], 120);

        $this->service()->toggleDefault([$settingUuid]);

        $this->assertFalse(Cache::has('scheduled_jobs_settings'));
    }

    public function test_deleting_scheduled_job_default_setting_clears_scheduler_cache(): void
    {
        $settingUuid = $this->insertDefaultSetting('scheduled_jobs');
        Cache::put('scheduled_jobs_settings', ['wake_up_calls' => 'true'], 120);

        $this->service()->deleteDefaults([$settingUuid]);

        $this->assertFalse(Cache::has('scheduled_jobs_settings'));
    }

    public function test_saving_default_time_zone_setting_clears_all_domain_time_zone_caches(): void
    {
        $domainUuid = $this->insertDomain('alpha.example.com');
        $otherDomainUuid = $this->insertDomain('bravo.example.com');
        Cache::put("{$domainUuid}_timeZone", 'America/New_York', 86400);
        Cache::put("{$otherDomainUuid}_timeZone", 'America/Chicago', 86400);

        $this->service()->saveDefault([
            'default_setting_category' => 'domain',
            'default_setting_subcategory' => 'time_zone',
            'default_setting_name' => 'name',
            'default_setting_value' => 'America/Denver',
            'default_setting_order' => null,
            'default_setting_enabled' => true,
            'default_setting_description' => null,
        ]);

        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));
        $this->assertFalse(Cache::has("{$otherDomainUuid}_timeZone"));
    }

    public function test_toggling_default_time_zone_setting_clears_all_domain_time_zone_caches(): void
    {
        $settingUuid = $this->insertDefaultSetting('domain', 'time_zone', 'name');
        $domainUuid = $this->insertDomain('alpha.example.com');
        Cache::put("{$domainUuid}_timeZone", 'America/New_York', 86400);

        $this->service()->toggleDefault([$settingUuid]);

        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));
    }

    public function test_deleting_default_time_zone_setting_clears_all_domain_time_zone_caches(): void
    {
        $settingUuid = $this->insertDefaultSetting('domain', 'time_zone', 'name');
        $domainUuid = $this->insertDomain('alpha.example.com');
        Cache::put("{$domainUuid}_timeZone", 'America/New_York', 86400);

        $this->service()->deleteDefaults([$settingUuid]);

        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));
    }

    public function test_domain_time_zone_setting_changes_clear_that_domain_time_zone_cache(): void
    {
        $domainUuid = $this->insertDomain('alpha.example.com');
        $otherDomainUuid = $this->insertDomain('bravo.example.com');
        $domain = \App\Models\Domain::query()->findOrFail($domainUuid);

        Cache::put("{$domainUuid}_timeZone", 'America/New_York', 86400);
        Cache::put("{$otherDomainUuid}_timeZone", 'America/Chicago', 86400);

        $setting = $this->service()->saveDomainOverride($domain, [
            'domain_setting_category' => 'domain',
            'domain_setting_subcategory' => 'time_zone',
            'domain_setting_name' => 'name',
            'domain_setting_value' => 'America/Denver',
            'domain_setting_order' => null,
            'domain_setting_enabled' => true,
            'domain_setting_description' => null,
        ]);

        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));
        $this->assertTrue(Cache::has("{$otherDomainUuid}_timeZone"));

        Cache::put("{$domainUuid}_timeZone", 'America/Denver', 86400);
        $this->service()->toggleDomain($domain, [$setting->domain_setting_uuid]);
        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));

        Cache::put("{$domainUuid}_timeZone", 'UTC', 86400);
        $this->service()->revertDomain($domain, [$setting->domain_setting_uuid]);
        $this->assertFalse(Cache::has("{$domainUuid}_timeZone"));
    }

    private function service(): SettingsManagementService
    {
        return app(SettingsManagementService::class);
    }

    private function insertDefaultSetting(string $category, string $subcategory = 'wake_up_calls', string $name = 'boolean'): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => $uuid,
            'default_setting_category' => $category,
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => $name,
            'default_setting_value' => 'true',
            'default_setting_order' => null,
            'default_setting_enabled' => 'true',
            'default_setting_description' => null,
        ]);

        return $uuid;
    }

    private function insertDomain(string $domainName): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_domains')->insert([
            'domain_uuid' => $uuid,
            'domain_name' => $domainName,
            'domain_description' => null,
            'domain_enabled' => 'true',
        ]);

        return $uuid;
    }
}
