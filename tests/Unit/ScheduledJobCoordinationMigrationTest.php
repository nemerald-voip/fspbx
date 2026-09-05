<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScheduledJobCoordinationMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config()->set('database.connections.coordination_migration_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'coordination_migration_test');
        DB::purge('coordination_migration_test');
        Schema::create('ldap_sync_runs', function (Blueprint $table) {
            $table->uuid('sync_run_uuid')->primary();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('coordination_migration_test');
        config()->set('database.default', $this->originalConnection);
        parent::tearDown();
    }

    public function test_coordination_tables_and_ldap_execution_link_are_reversible_without_heartbeat_columns(): void
    {
        $migration = require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('ldap_sync_runs', [
            'node_name', 'scheduled_job_execution_uuid', 'node_id', 'ownership_generation',
        ]));
        $this->assertTrue(Schema::hasTable('scheduled_job_nodes'));
        $this->assertTrue(Schema::hasTable('scheduled_job_handoffs'));
        $this->assertTrue(Schema::hasTable('scheduled_job_executions'));
        $this->assertFalse(Schema::hasColumn('scheduled_job_nodes', 'heartbeat_at'));
        $this->assertFalse(Schema::hasColumn('scheduled_job_nodes', 'last_seen_at'));

        $migration->down();

        $this->assertFalse(Schema::hasTable('scheduled_job_nodes'));
        $this->assertFalse(Schema::hasColumn('ldap_sync_runs', 'node_name'));
        $this->assertFalse(Schema::hasColumn('ldap_sync_runs', 'scheduled_job_execution_uuid'));
    }

    public function test_existing_ldap_node_names_survive_the_combined_migration(): void
    {
        Schema::table('ldap_sync_runs', function (Blueprint $table) {
            $table->string('node_name')->nullable();
        });
        DB::table('ldap_sync_runs')->insert([
            'sync_run_uuid' => 'd9a0c942-df24-4c97-8c36-3b7ee4357441',
            'node_name' => 'pbx-a',
        ]);

        $migration = require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php');
        $migration->up();

        $this->assertSame('pbx-a', DB::table('ldap_sync_runs')->value('node_name'));
        $this->assertTrue(Schema::hasColumn('ldap_sync_runs', 'scheduled_job_execution_uuid'));
    }
}
