<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DateTimeFormatHelpersTest extends TestCase
{
    private string $databasePath;
    private string $domain = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-datetime-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    private function defaultSetting(string $subcategory, string $value): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => bin2hex(random_bytes(16)), 'default_setting_category' => 'domain',
            'default_setting_subcategory' => $subcategory, 'default_setting_name' => 'text',
            'default_setting_value' => $value, 'default_setting_enabled' => 'true',
        ]);
    }

    private function domainSetting(string $subcategory, string $value): void
    {
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => bin2hex(random_bytes(16)), 'domain_uuid' => $this->domain,
            'domain_setting_category' => 'domain', 'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => 'text', 'domain_setting_value' => $value, 'domain_setting_enabled' => 'true',
        ]);
    }

    public function test_without_settings_the_existing_us_notation_is_kept(): void
    {
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('3:04:05 PM Jan 31, 2026', $at->format(local_datetime_format($this->domain)));
        $this->assertSame('Jan 31, 2026', $at->format(local_date_format($this->domain)));
        $this->assertSame('January 31, 2026', $at->format(local_date_format($this->domain, true)));
        $this->assertSame('3:04 PM', $at->format(local_time_format($this->domain, false)));
        $this->assertSame('3 PM', $at->format(local_hour_format($this->domain)));
    }

    public function test_installation_default_applies_to_every_account(): void
    {
        $this->defaultSetting('date_format', 'eu');
        $this->defaultSetting('time_format', '24h');
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('31/01/2026 15:04:05', $at->format(local_datetime_format($this->domain)));
        $this->assertSame('31 January 2026', $at->format(local_date_format($this->domain, true)));
        $this->assertSame('15:00', $at->format(local_hour_format($this->domain)));
    }

    public function test_account_setting_overrides_the_installation_default(): void
    {
        $this->defaultSetting('date_format', 'eu');
        $this->defaultSetting('time_format', '24h');
        $this->domainSetting('date_format', 'iso');
        $this->domainSetting('time_format', '12h');
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('2026-01-31 3:04:05 PM', $at->format(local_datetime_format($this->domain)));
        $this->assertSame(['date_format' => 'iso', 'time_format' => '12h'], get_local_date_time_formats($this->domain));
    }

    public function test_unknown_values_fall_back_to_us_notation(): void
    {
        $this->defaultSetting('date_format', 'dd-mm-yyyy');
        $this->defaultSetting('time_format', 'military');

        $this->assertSame(['date_format' => 'us', 'time_format' => '12h'], get_local_date_time_formats($this->domain));
    }
}
