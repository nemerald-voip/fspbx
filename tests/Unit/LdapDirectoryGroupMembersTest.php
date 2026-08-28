<?php

namespace Tests\Unit;

use App\Http\Controllers\LdapDirectoryController;
use App\Models\LdapDirectory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LdapDirectoryGroupMembersTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_group_members_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_group_members_test');
        DB::purge('ldap_group_members_test');

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();

        Schema::create('v_groups', function (Blueprint $table) {
            $table->uuid('group_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->string('group_name');
            $table->text('group_description')->nullable();
            $table->integer('group_level')->default(0);
        });
        Schema::create('v_users', function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('user_email')->nullable();
        });

        session([
            'domain_uuid' => '10000000-0000-4000-8000-000000000001',
            'permissions' => [(object) ['permission_name' => 'ldap_directory_map_groups']],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_group_members_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_it_returns_members_for_a_group_in_the_current_account(): void
    {
        $directory = $this->createDirectory(
            '20000000-0000-4000-8000-000000000002',
            '10000000-0000-4000-8000-000000000001'
        );
        $groupUuid = '30000000-0000-4000-8000-000000000003';
        $directoryUserUuid = '40000000-0000-4000-8000-000000000004';

        DB::table('ldap_directory_groups')->insert([
            'directory_group_uuid' => $groupUuid,
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $directory->domain_uuid,
            'external_id' => 'scientists',
            'name' => 'Scientists',
            'description' => 'Research group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => $directoryUserUuid,
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $directory->domain_uuid,
            'user_uuid' => '50000000-0000-4000-8000-000000000005',
            'external_id' => 'einstein',
            'username' => 'einstein',
            'email' => 'einstein@example.test',
            'first_name' => 'Albert',
            'last_name' => 'Einstein',
            'display_name' => '',
            'extension' => '1001',
            'remote_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ldap_directory_group_members')->insert([
            'directory_group_uuid' => $groupUuid,
            'directory_user_uuid' => $directoryUserUuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = (new LdapDirectoryController())->groupMembers($directory->directory_uuid, $groupUuid);
        $data = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('Scientists', $data['group']['name']);
        $this->assertSame(1, $data['member_count']);
        $this->assertSame('Albert Einstein', $data['members'][0]['name']);
        $this->assertSame('einstein', $data['members'][0]['username']);
        $this->assertSame('einstein@example.test', $data['members'][0]['email']);
        $this->assertSame('1001', $data['members'][0]['extension']);
        $this->assertTrue($data['members'][0]['enabled']);

        $mappingData = (new LdapDirectoryController())->mappings($directory->directory_uuid)->getData(true);
        $this->assertSame(1, $mappingData['directory_groups'][0]['directory_users_count']);
        $this->assertFalse($mappingData['manage_groups_locally']);
        $this->assertStringContainsString(
            "/ldap-directories/{$directory->directory_uuid}/groups/{$groupUuid}/members",
            $mappingData['directory_groups'][0]['routes']['members']
        );
    }

    public function test_it_rejects_a_group_from_another_directory(): void
    {
        $directory = $this->createDirectory(
            '60000000-0000-4000-8000-000000000006',
            '10000000-0000-4000-8000-000000000001'
        );
        $otherDirectory = $this->createDirectory(
            '70000000-0000-4000-8000-000000000007',
            '10000000-0000-4000-8000-000000000001'
        );
        $otherGroupUuid = '80000000-0000-4000-8000-000000000008';

        DB::table('ldap_directory_groups')->insert([
            'directory_group_uuid' => $otherGroupUuid,
            'directory_uuid' => $otherDirectory->directory_uuid,
            'domain_uuid' => $otherDirectory->domain_uuid,
            'external_id' => 'other-group',
            'name' => 'Other Group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        (new LdapDirectoryController())->groupMembers($directory->directory_uuid, $otherGroupUuid);
    }

    public function test_group_members_route_is_registered(): void
    {
        $route = app('router')->getRoutes()->getByName('ldap-directories.groups.members');

        $this->assertNotNull($route);
        $this->assertSame('api/ldap-directories/{directory}/groups/{group}/members', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_it_rejects_ineffective_mappings_when_roles_are_managed_locally(): void
    {
        $directory = $this->createDirectory(
            '90000000-0000-4000-8000-000000000009',
            '10000000-0000-4000-8000-000000000001'
        );
        $directory->manage_groups_locally = true;
        $directory->save();

        $request = Request::create('/api/ldap-directories/'.$directory->directory_uuid.'/mappings', 'PUT', [
            'mappings' => [],
        ]);
        $response = (new LdapDirectoryController())->updateMappings($request, $directory->directory_uuid);

        $this->assertSame(422, $response->status());
        $this->assertSame(
            'Set Manage groups locally to No before mapping directory groups to local roles.',
            $response->getData(true)['message']
        );
    }

    private function createDirectory(string $directoryUuid, string $domainUuid): LdapDirectory
    {
        $directory = new LdapDirectory([
            'domain_uuid' => $domainUuid,
            'type' => 'active_directory',
            'name' => 'Directory ' . $directoryUuid,
            'hosts' => 'dc.example.test',
            'bind_username' => 'sync@example.test',
            'ad_domain' => 'example.test',
            'base_dn' => 'dc=example,dc=test',
        ]);
        $directory->directory_uuid = $directoryUuid;
        $directory->save();

        return $directory;
    }
}
