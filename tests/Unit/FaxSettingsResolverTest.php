<?php

namespace Tests\Unit;

use App\Services\FaxSettingsResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FaxSettingsResolverTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-fax-settings-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

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
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_it_defaults_to_off_when_the_setting_is_missing(): void
    {
        $this->assertFalse($this->resolver()->boolean('send_confirmation', (string) Str::uuid()));
    }

    public function test_it_uses_the_global_fax_setting(): void
    {
        $this->insertDefault('true');

        $this->assertTrue($this->resolver()->boolean('send_confirmation', (string) Str::uuid()));
    }

    public function test_domain_fax_setting_overrides_the_global_setting(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('true');
        $this->insertDomain($domainUuid, 'false');

        $this->assertFalse($this->resolver()->boolean('send_confirmation', $domainUuid));
    }

    public function test_disabled_domain_setting_falls_back_to_the_global_setting(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('true');
        $this->insertDomain($domainUuid, 'false', false);

        $this->assertTrue($this->resolver()->boolean('send_confirmation', $domainUuid));
    }

    public function test_blank_enabled_domain_setting_still_overrides_the_global_setting(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('true');
        $this->insertDomain($domainUuid, null);

        $this->assertFalse($this->resolver()->boolean('send_confirmation', $domainUuid));
    }

    private function resolver(): FaxSettingsResolver
    {
        return new FaxSettingsResolver();
    }

    private function insertDefault(string $value): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'fax',
            'default_setting_subcategory' => 'send_confirmation',
            'default_setting_name' => 'boolean',
            'default_setting_value' => $value,
            'default_setting_enabled' => 'true',
        ]);
    }

    private function insertDomain(string $domainUuid, ?string $value, bool $enabled = true): void
    {
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'domain_setting_category' => 'fax',
            'domain_setting_subcategory' => 'send_confirmation',
            'domain_setting_name' => 'boolean',
            'domain_setting_value' => $value,
            'domain_setting_enabled' => $enabled ? 'true' : 'false',
        ]);
    }
}
