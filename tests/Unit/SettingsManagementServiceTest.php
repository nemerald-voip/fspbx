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

    private function service(): SettingsManagementService
    {
        return app(SettingsManagementService::class);
    }

    private function insertDefaultSetting(string $category): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => $uuid,
            'default_setting_category' => $category,
            'default_setting_subcategory' => 'wake_up_calls',
            'default_setting_name' => 'boolean',
            'default_setting_value' => 'true',
            'default_setting_order' => null,
            'default_setting_enabled' => 'true',
            'default_setting_description' => null,
        ]);

        return $uuid;
    }
}
