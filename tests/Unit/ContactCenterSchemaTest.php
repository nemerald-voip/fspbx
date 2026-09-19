<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\{DB, Facade, Schema};
use PHPUnit\Framework\TestCase;

class ContactCenterSchemaTest extends TestCase
{
    private Application $app;
    private Migrator $migrator;
    private array $paths;

    protected function setUp(): void
    {
        // No module providers, models, or helpers are loaded to install this schema.
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $db = new Manager($this->app);
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $manager = $db->getDatabaseManager();
        $this->app->instance('db', $manager);
        $this->app->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
        $repository = new DatabaseMigrationRepository($manager, 'migrations');
        $repository->createRepository();
        $this->migrator = new Migrator($repository, $manager, new Filesystem());
        $this->paths = [
            database_path('migrations/2026_08_04_000001_add_contact_center_dashboard_cdr_indexes.php'),
            database_path('migrations/2026_09_09_000001_create_contact_center_ha_and_callbacks.php'),
        ];
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_main_migrator_installs_and_rolls_back_contact_center_without_the_module(): void
    {
        $discovered = $this->migrator->getMigrationFiles([database_path('migrations')]);
        foreach ($this->paths as $path) {
            $this->assertContains($path, $discovered);
        }
        $this->migrator->run($this->paths);
        foreach (['changes', 'status_events', 'node_state', 'callback_settings', 'callbacks', 'callback_attempts', 'webhook_calls'] as $table) {
            $this->assertTrue(Schema::hasTable('contact_center_'.$table));
        }
        $this->assertSame(2, DB::table('migrations')->count());
        $this->migrator->rollback($this->paths);
        $this->assertFalse(Schema::hasTable('contact_center_callbacks'));
        $this->assertSame(0, DB::table('migrations')->count());
    }

    public function test_previously_applied_filenames_are_not_run_again_after_moving(): void
    {
        $schema = require $this->paths[1];
        $schema->up();
        DB::table('migrations')->insert([
            'migration' => '2026_09_09_000001_create_contact_center_ha_and_callbacks',
            'batch' => 1,
        ]);
        DB::table('contact_center_status_events')->insert([
            'contact_center_status_event_uuid' => '3b57fd17-56a4-4670-9ad2-309a0e893fa8',
            'domain_uuid' => '95d36a71-cff3-4c6c-a6b4-ea3c75a23f43',
            'agent_uuid' => '7dbd7c5b-dbc8-424e-b536-9ba002896c71',
            'source_node' => '1001', 'status' => 'Available', 'source' => 'explicit', 'clock' => '{"1001":1}',
        ]);
        $this->migrator->run($this->paths);
        $this->assertSame(2, DB::table('migrations')->count());
        $this->assertSame('Available', DB::table('contact_center_status_events')->value('status'));
    }
}
