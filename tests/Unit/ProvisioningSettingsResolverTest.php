<?php

namespace Tests\Unit;

use App\Services\Provisioning\ProvisioningSettingsResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisioningSettingsResolverTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-provision-settings-' . bin2hex(random_bytes(8)) . '.sqlite';
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
            $table->string('default_setting_order')->nullable();
            $table->string('default_setting_enabled')->nullable();
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

    public function test_domain_array_settings_replace_default_arrays(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('provision', 'cidr', 'array', '192.0.2.0/24', '10');
        $this->insertDefault('provision', 'cidr', 'array', '198.51.100.0/24', '20');
        $this->insertDomain($domainUuid, 'provision', 'cidr', 'array', '203.0.113.10/32', '10');

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame(['203.0.113.10/32'], $settings['cidr']);
    }

    public function test_defaults_are_used_when_no_enabled_domain_array_exists(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('provision', 'cidr', 'array', '192.0.2.0/24');
        $this->insertDomain($domainUuid, 'provision', 'cidr', 'array', '203.0.113.10/32', null, false);

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame(['192.0.2.0/24'], $settings['cidr']);
    }

    public function test_scalar_domain_settings_override_defaults_even_when_blank(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('provision', 'http_auth_username', 'text', 'default-user');
        $this->insertDomain($domainUuid, 'provision', 'http_auth_username', 'text', '');

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame('', $settings['http_auth_username']);
    }

    public function test_only_provision_category_settings_are_loaded(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('other', 'cidr', 'array', '0.0.0.0/0');
        $this->insertDefault('provision', 'cidr', 'array', '192.0.2.10/32');

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame(['192.0.2.10/32'], $settings['cidr']);
    }

    public function test_multiple_provisioning_passwords_are_preserved(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('provision', 'http_auth_password', 'array', 'first', '10');
        $this->insertDefault('provision', 'http_auth_password', 'array', 'second', '20');

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame(['first', 'second'], $settings['http_auth_password']);
    }

    public function test_legacy_text_value_is_kept_alongside_array_rows(): void
    {
        $domainUuid = (string) Str::uuid();
        $this->insertDefault('provision', 'cidr', 'text', '192.0.2.10/32', '10');
        $this->insertDefault('provision', 'cidr', 'array', '198.51.100.0/24', '20');

        $settings = (new ProvisioningSettingsResolver())->resolve($domainUuid);

        $this->assertSame(['192.0.2.10/32', '198.51.100.0/24'], $settings['cidr']);
    }

    private function insertDefault(
        string $category,
        string $subcategory,
        string $name,
        string $value,
        ?string $order = null,
        bool $enabled = true
    ): void {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => $category,
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => $name,
            'default_setting_value' => $value,
            'default_setting_order' => $order,
            'default_setting_enabled' => $enabled ? 'true' : 'false',
        ]);
    }

    private function insertDomain(
        string $domainUuid,
        string $category,
        string $subcategory,
        string $name,
        string $value,
        ?string $order = null,
        bool $enabled = true
    ): void {
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'domain_setting_category' => $category,
            'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => $name,
            'domain_setting_value' => $value,
            'domain_setting_order' => $order,
            'domain_setting_enabled' => $enabled ? 'true' : 'false',
        ]);
    }
}
