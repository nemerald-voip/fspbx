<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Models\User;
use App\Services\LdapDirectoryDeletionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LdapDirectoryDeletionServiceTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_deletion_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_deletion_test');
        DB::purge('ldap_deletion_test');
        DB::connection()->getPdo()->sqliteCreateFunction(
            'uuid_generate_v4',
            fn (): string => (string) Str::uuid()
        );

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();
        $uuidMigration = require base_path('database/migrations/2026_08_30_000001_recreate_disposable_tables_with_uuid_primary_keys.php');
        $uuidMigration->up();

        $this->createLocalTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_deletion_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_it_deletes_directory_owned_users_and_preserves_matched_local_records(): void
    {
        $domainUuid = '10000000-0000-4000-8000-000000000001';
        $directoryUuid = '20000000-0000-4000-8000-000000000002';
        $ownedUserUuid = '30000000-0000-4000-8000-000000000003';
        $localUserUuid = '40000000-0000-4000-8000-000000000004';
        $directoryGroupUuid = '50000000-0000-4000-8000-000000000005';
        $mappedGroupUuid = '60000000-0000-4000-8000-000000000006';
        $localGroupUuid = '70000000-0000-4000-8000-000000000007';

        $directory = new LdapDirectory([
            'domain_uuid' => $domainUuid,
            'type' => 'active_directory',
            'name' => 'Example Directory',
            'hosts' => 'dc.example.test',
            'bind_username' => 'sync@example.test',
            'ad_domain' => 'example.test',
            'base_dn' => 'dc=example,dc=test',
        ]);
        $directory->directory_uuid = $directoryUuid;
        $directory->save();

        DB::table('v_users')->insert([
            [
                'user_uuid' => $ownedUserUuid,
                'domain_uuid' => $domainUuid,
                'username' => 'imported',
                'user_email' => 'imported@example.test',
                'password' => 'not-used',
                'user_enabled' => 'true',
                'add_user' => 'ldap:' . $directoryUuid,
            ],
            [
                'user_uuid' => $localUserUuid,
                'domain_uuid' => $domainUuid,
                'username' => 'existing',
                'user_email' => 'existing@example.test',
                'password' => 'not-used',
                'user_enabled' => 'true',
                'add_user' => 'admin',
            ],
        ]);

        DB::table('ldap_directory_users')->insert([
            $this->directoryUser('80000000-0000-4000-8000-000000000008', $directoryUuid, $domainUuid, $ownedUserUuid, 'owned'),
            $this->directoryUser('90000000-0000-4000-8000-000000000009', $directoryUuid, $domainUuid, $localUserUuid, 'matched'),
        ]);

        DB::table('ldap_directory_groups')->insert([
            'directory_group_uuid' => $directoryGroupUuid,
            'directory_uuid' => $directoryUuid,
            'domain_uuid' => $domainUuid,
            'external_id' => 'remote-group',
            'name' => 'Remote Group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('v_groups')->insert([
            ['group_uuid' => $mappedGroupUuid, 'group_name' => 'Mapped Role'],
            ['group_uuid' => $localGroupUuid, 'group_name' => 'Local Role'],
        ]);
        DB::table('ldap_directory_group_mappings')->insert([
            'directory_uuid' => $directoryUuid,
            'directory_group_uuid' => $directoryGroupUuid,
            'group_uuid' => $mappedGroupUuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_user_group_assignments')->insert([
            'directory_user_uuid' => '90000000-0000-4000-8000-000000000009',
            'group_uuid' => $mappedGroupUuid,
            'created_membership' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_group_members')->insert([
            'directory_group_uuid' => $directoryGroupUuid,
            'directory_user_uuid' => '90000000-0000-4000-8000-000000000009',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('v_user_groups')->insert([
            $this->userGroup('a0000000-0000-4000-8000-00000000000a', $domainUuid, $localUserUuid, $mappedGroupUuid),
            $this->userGroup('b0000000-0000-4000-8000-00000000000b', $domainUuid, $localUserUuid, $localGroupUuid),
            $this->userGroup('c0000000-0000-4000-8000-00000000000c', $domainUuid, $ownedUserUuid, $mappedGroupUuid),
        ]);
        DB::table('users_adv_fields')->insert(['id' => 'd0000000-0000-4000-8000-00000000000d', 'user_uuid' => $ownedUserUuid]);
        DB::table('v_user_settings')->insert(['user_setting_uuid' => 'e0000000-0000-4000-8000-00000000000e', 'user_uuid' => $ownedUserUuid]);
        DB::table('user_domain_permission')->insert(['id' => 'f0000000-0000-4000-8000-00000000000f', 'user_uuid' => $ownedUserUuid]);
        DB::table('user_domain_group_permissions')->insert(['id' => '11000000-0000-4000-8000-000000000011', 'user_uuid' => $ownedUserUuid]);
        DB::table('locationables')->insert(['locationable_type' => User::class, 'locationable_id' => $ownedUserUuid]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => $ownedUserUuid,
            'name' => 'directory-user-token',
            'token' => hash('sha256', 'directory-user-token'),
        ]);

        DB::table('v_extensions')->insert(['extension_uuid' => '12000000-0000-4000-8000-000000000012', 'user_uuid' => $ownedUserUuid]);
        DB::table('v_voicemails')->insert(['voicemail_uuid' => '13000000-0000-4000-8000-000000000013', 'user_uuid' => $ownedUserUuid]);
        DB::table('v_xml_cdr')->insert(['xml_cdr_uuid' => '14000000-0000-4000-8000-000000000014', 'user_uuid' => $ownedUserUuid]);

        $deletedUserUuids = User::withoutEvents(
            fn () => app(LdapDirectoryDeletionService::class)->delete($directory)
        );

        $this->assertSame([$ownedUserUuid], $deletedUserUuids->all());
        $this->assertDatabaseMissing('v_users', ['user_uuid' => $ownedUserUuid]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $ownedUserUuid]);
        $this->assertDatabaseHas('v_users', ['user_uuid' => $localUserUuid, 'add_user' => 'admin']);
        $this->assertDatabaseMissing('v_user_groups', ['user_uuid' => $localUserUuid, 'group_uuid' => $mappedGroupUuid]);
        $this->assertDatabaseHas('v_user_groups', ['user_uuid' => $localUserUuid, 'group_uuid' => $localGroupUuid]);
        $this->assertDatabaseHas('v_groups', ['group_uuid' => $mappedGroupUuid]);
        $this->assertDatabaseMissing('ldap_directories', ['directory_uuid' => $directoryUuid]);
        $this->assertDatabaseCount('ldap_directory_users', 0);
        $this->assertDatabaseCount('ldap_directory_groups', 0);
        $this->assertDatabaseCount('ldap_directory_group_mappings', 0);
        $this->assertDatabaseHas('v_extensions', ['user_uuid' => $ownedUserUuid]);
        $this->assertDatabaseHas('v_voicemails', ['user_uuid' => $ownedUserUuid]);
        $this->assertDatabaseHas('v_xml_cdr', ['user_uuid' => $ownedUserUuid]);
    }

    private function directoryUser(string $uuid, string $directoryUuid, string $domainUuid, string $userUuid, string $externalId): array
    {
        return [
            'directory_user_uuid' => $uuid,
            'directory_uuid' => $directoryUuid,
            'domain_uuid' => $domainUuid,
            'user_uuid' => $userUuid,
            'external_id' => $externalId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function userGroup(string $uuid, string $domainUuid, string $userUuid, string $groupUuid): array
    {
        return [
            'user_group_uuid' => $uuid,
            'domain_uuid' => $domainUuid,
            'user_uuid' => $userUuid,
            'group_uuid' => $groupUuid,
        ];
    }

    private function createLocalTables(): void
    {
        Schema::create('v_users', function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('username');
            $table->string('user_email');
            $table->string('password');
            $table->string('user_enabled');
            $table->string('add_user')->nullable();
            $table->timestamp('add_date')->nullable();
        });
        Schema::create('users_adv_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('v_user_settings', function (Blueprint $table) {
            $table->uuid('user_setting_uuid')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('v_user_groups', function (Blueprint $table) {
            $table->uuid('user_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('user_uuid');
            $table->uuid('group_uuid');
        });
        Schema::create('user_domain_permission', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('user_domain_group_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('locationables', function (Blueprint $table) {
            $table->string('locationable_type');
            $table->uuid('locationable_id');
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64);
        });
        Schema::create('v_groups', function (Blueprint $table) {
            $table->uuid('group_uuid')->primary();
            $table->string('group_name');
        });
        Schema::create('v_extensions', function (Blueprint $table) {
            $table->uuid('extension_uuid')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('v_voicemails', function (Blueprint $table) {
            $table->uuid('voicemail_uuid')->primary();
            $table->uuid('user_uuid');
        });
        Schema::create('v_xml_cdr', function (Blueprint $table) {
            $table->uuid('xml_cdr_uuid')->primary();
            $table->uuid('user_uuid');
        });
    }
}
