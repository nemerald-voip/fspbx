<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Services\Auth\UserSessionInvalidationService;
use App\Services\LdapDirectorySyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LdapDirectoryGroupMappingTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_group_mapping_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_group_mapping_test');
        DB::purge('ldap_group_mapping_test');
        DB::connection()->getPdo()->sqliteCreateFunction(
            'uuid_generate_v4',
            fn (): string => (string) Str::uuid()
        );

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();
        $uuidMigration = require base_path('database/migrations/2026_08_30_000001_recreate_disposable_tables_with_uuid_primary_keys.php');
        $uuidMigration->up();

        Schema::create('v_groups', function (Blueprint $table) {
            $table->uuid('group_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->string('group_name');
        });
        Schema::create('v_user_groups', function (Blueprint $table) {
            $table->uuid('user_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('group_uuid');
            $table->uuid('user_uuid');
            $table->string('group_name');
        });

        $this->app->instance(UserSessionInvalidationService::class, new class extends UserSessionInvalidationService
        {
            public function invalidateByUserUuids(iterable $userUuids): void
            {
                // Session cache behavior is covered separately.
            }
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_group_mapping_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_it_applies_and_removes_mapped_local_roles_without_another_directory_sync(): void
    {
        $directory = $this->directory(false);
        $this->seedMembership($directory);

        $service = app(LdapDirectorySyncService::class);

        $this->assertSame(1, $service->applyMappedLocalGroups($directory));
        $this->assertDatabaseHas('v_user_groups', [
            'user_uuid' => '50000000-0000-4000-8000-000000000005',
            'group_uuid' => '60000000-0000-4000-8000-000000000006',
        ]);

        DB::table('ldap_directory_group_mappings')->delete();

        $service->applyMappedLocalGroups($directory);
        $this->assertDatabaseMissing('v_user_groups', [
            'user_uuid' => '50000000-0000-4000-8000-000000000005',
            'group_uuid' => '60000000-0000-4000-8000-000000000006',
        ]);
    }

    public function test_it_does_not_apply_mappings_when_roles_are_managed_locally(): void
    {
        $directory = $this->directory(true);
        $this->seedMembership($directory);

        $this->assertSame(0, app(LdapDirectorySyncService::class)->applyMappedLocalGroups($directory));
        $this->assertDatabaseCount('v_user_groups', 0);
    }

    private function directory(bool $manageGroupsLocally): LdapDirectory
    {
        $directory = new LdapDirectory([
            'domain_uuid' => '10000000-0000-4000-8000-000000000001',
            'type' => 'active_directory',
            'name' => 'Test Directory',
            'hosts' => 'ldap.example.test',
            'bind_username' => 'sync@example.test',
            'ad_domain' => 'example.test',
            'base_dn' => 'dc=example,dc=test',
            'manage_groups_locally' => $manageGroupsLocally,
        ]);
        $directory->directory_uuid = '20000000-0000-4000-8000-000000000002';
        $directory->save();

        return $directory;
    }

    private function seedMembership(LdapDirectory $directory): void
    {
        DB::table('v_groups')->insert([
            'group_uuid' => '60000000-0000-4000-8000-000000000006',
            'domain_uuid' => $directory->domain_uuid,
            'group_name' => 'user',
        ]);
        DB::table('ldap_directory_groups')->insert([
            'directory_group_uuid' => '30000000-0000-4000-8000-000000000003',
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $directory->domain_uuid,
            'external_id' => 'scientists',
            'name' => 'Scientists',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => '40000000-0000-4000-8000-000000000004',
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $directory->domain_uuid,
            'user_uuid' => '50000000-0000-4000-8000-000000000005',
            'external_id' => 'einstein',
            'username' => 'einstein',
            'remote_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_group_members')->insert([
            'directory_group_uuid' => '30000000-0000-4000-8000-000000000003',
            'directory_user_uuid' => '40000000-0000-4000-8000-000000000004',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_group_mappings')->insert([
            'directory_uuid' => $directory->directory_uuid,
            'directory_group_uuid' => '30000000-0000-4000-8000-000000000003',
            'group_uuid' => '60000000-0000-4000-8000-000000000006',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
