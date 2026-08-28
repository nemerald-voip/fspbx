<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LdapDirectorySchemaMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_directory_schema_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_directory_schema_test');
        DB::purge('ldap_directory_schema_test');
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_directory_schema_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_optional_user_attribute_mappings_accept_null(): void
    {
        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();

        DB::table('ldap_directories')->insert([
            'directory_uuid' => '11111111-1111-4111-8111-111111111111',
            'domain_uuid' => '22222222-2222-4222-8222-222222222222',
            'name' => 'Generic LDAP Test',
            'hosts' => 'ldap.example.com',
            'bind_username' => 'cn=reader,dc=example,dc=com',
            'bind_password' => null,
            'ad_domain' => 'example.com',
            'base_dn' => 'dc=example,dc=com',
            'user_title_attribute' => null,
            'user_company_attribute' => null,
            'user_department_attribute' => null,
            'user_home_phone_attribute' => null,
            'user_work_phone_attribute' => null,
            'user_cell_phone_attribute' => null,
            'user_fax_attribute' => null,
            'user_extension_attribute' => null,
        ]);

        $this->assertDatabaseHas('ldap_directories', [
            'directory_uuid' => '11111111-1111-4111-8111-111111111111',
            'user_title_attribute' => null,
            'user_work_phone_attribute' => null,
            'user_extension_attribute' => null,
        ]);

        $columns = collect(Schema::getConnection()->getSchemaBuilder()->getColumns('ldap_directories'))
            ->keyBy('name');

        foreach ([
            'user_title_attribute',
            'user_company_attribute',
            'user_department_attribute',
            'user_home_phone_attribute',
            'user_work_phone_attribute',
            'user_cell_phone_attribute',
            'user_fax_attribute',
            'user_extension_attribute',
        ] as $column) {
            $this->assertTrue($columns->get($column)['nullable']);
        }
    }
}
