<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LdapDirectoryLatestSyncRunTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_latest_sync_run_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_latest_sync_run_test');
        DB::purge('ldap_latest_sync_run_test');

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_latest_sync_run_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_latest_sync_run_is_selected_without_aggregating_the_uuid_primary_key(): void
    {
        $directoryUuid = '11111111-1111-4111-8111-111111111111';
        $domainUuid = '22222222-2222-4222-8222-222222222222';

        DB::table('ldap_directories')->insert([
            'directory_uuid' => $directoryUuid,
            'domain_uuid' => $domainUuid,
            'name' => 'Company Directory',
            'hosts' => 'ldap.example.com',
            'bind_username' => 'ldap-reader',
            'ad_domain' => 'example.com',
            'base_dn' => 'dc=example,dc=com',
            'created_at' => '2026-09-04 10:00:00',
            'updated_at' => '2026-09-04 10:00:00',
        ]);
        DB::table('ldap_sync_runs')->insert([
            [
                'sync_run_uuid' => '33333333-3333-4333-8333-333333333333',
                'directory_uuid' => $directoryUuid,
                'domain_uuid' => $domainUuid,
                'status' => 'completed',
                'started_at' => '2026-09-04 10:00:00',
                'created_at' => '2026-09-04 10:00:00',
                'updated_at' => '2026-09-04 10:01:00',
            ],
            [
                'sync_run_uuid' => '44444444-4444-4444-8444-444444444444',
                'directory_uuid' => $directoryUuid,
                'domain_uuid' => $domainUuid,
                'status' => 'running',
                'started_at' => '2026-09-04 11:00:00',
                'created_at' => '2026-09-04 11:00:00',
                'updated_at' => '2026-09-04 11:00:00',
            ],
        ]);

        $directory = LdapDirectory::query()->with('latestSyncRun')->findOrFail($directoryUuid);

        $this->assertSame('44444444-4444-4444-8444-444444444444', $directory->latestSyncRun->sync_run_uuid);
        $this->assertStringNotContainsString('max(', strtolower($directory->latestSyncRun()->toBase()->toSql()));
    }
}
