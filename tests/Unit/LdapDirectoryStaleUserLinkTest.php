<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Models\LdapDirectoryUser;
use App\Services\LdapDirectorySyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LdapDirectoryStaleUserLinkTest extends TestCase
{
    private string $originalConnection;
    private string $originalCacheStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        $this->originalCacheStore = config('cache.default');
        config()->set('database.connections.ldap_stale_link_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_stale_link_test');
        config()->set('cache.default', 'array');
        DB::purge('ldap_stale_link_test');

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();

        Schema::create('v_users', function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('username');
            $table->string('user_email')->nullable();
            $table->string('password');
            $table->string('user_enabled');
            $table->string('add_user')->nullable();
            $table->timestamp('add_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_stale_link_test');
        config()->set('database.default', $this->originalConnection);
        config()->set('cache.default', $this->originalCacheStore);

        parent::tearDown();
    }

    public function test_stale_directory_link_recreates_a_deleted_local_user(): void
    {
        $domainUuid = '22222222-2222-4222-8222-222222222222';
        $directory = new LdapDirectory([
            'domain_uuid' => $domainUuid,
            'ad_domain' => 'example.com',
        ]);
        $directory->directory_uuid = '11111111-1111-4111-8111-111111111111';

        $directoryUser = LdapDirectoryUser::query()->create([
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $domainUuid,
            'user_uuid' => '33333333-3333-4333-8333-333333333333',
            'external_id' => 'remote-user-1',
            'username' => 'einstein',
            'email' => 'einstein@example.com',
        ]);

        $profile = [
            'username' => 'einstein',
            'email' => 'einstein@example.com',
            'remote_enabled' => true,
        ];

        DB::table('ldap_directory_user_group_assignments')->insert([
            'directory_user_uuid' => $directoryUser->directory_user_uuid,
            'group_uuid' => '44444444-4444-4444-8444-444444444444',
            'created_membership' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = new ReflectionMethod(LdapDirectorySyncService::class, 'resolveLocalUser');
        $localUser = $method->invoke(new LdapDirectorySyncService(), $directory, $directoryUser, $profile);

        $this->assertNotNull($localUser);
        $this->assertNotSame('33333333-3333-4333-8333-333333333333', $localUser->user_uuid);
        $this->assertSame('einstein', $localUser->username);
        $this->assertNull($directoryUser->user_uuid);
        $this->assertDatabaseMissing('ldap_directory_user_group_assignments', [
            'directory_user_uuid' => $directoryUser->directory_user_uuid,
        ]);
        $this->assertDatabaseHas('v_users', [
            'user_uuid' => $localUser->user_uuid,
            'domain_uuid' => $domainUuid,
            'username' => 'einstein',
        ]);
    }

    public function test_new_directory_user_without_ldap_email_is_created_without_a_synthetic_email(): void
    {
        $domainUuid = '22222222-2222-4222-8222-222222222222';
        $directory = new LdapDirectory([
            'domain_uuid' => $domainUuid,
            'ad_domain' => 'example.com',
        ]);
        $directory->directory_uuid = '11111111-1111-4111-8111-111111111111';

        $directoryUser = new LdapDirectoryUser([
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $domainUuid,
            'external_id' => 'remote-user-without-email',
            'username' => 'noemail',
        ]);

        $profile = [
            'username' => 'noemail',
            'email' => null,
            'remote_enabled' => true,
        ];

        $method = new ReflectionMethod(LdapDirectorySyncService::class, 'resolveLocalUser');
        $localUser = $method->invoke(new LdapDirectorySyncService(), $directory, $directoryUser, $profile);

        $this->assertNotNull($localUser);
        $this->assertNull($localUser->user_email);
        $this->assertDatabaseHas('v_users', [
            'user_uuid' => $localUser->user_uuid,
            'domain_uuid' => $domainUuid,
            'username' => 'noemail',
            'user_email' => null,
        ]);
        $this->assertDatabaseMissing('v_users', [
            'user_email' => 'noemail@example.com',
        ]);
    }
}
