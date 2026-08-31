<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiProviderIntegration;
use App\Models\AiToolInvocation;
use App\Services\AiTools\AiSendEmailToolService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAgentSchemaMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ai_agent_schema_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ai_agent_schema_test');
        DB::purge('ai_agent_schema_test');
        DB::connection()->getPdo()->sqliteCreateFunction(
            'uuid_generate_v4',
            fn (): string => (string) Str::uuid()
        );
    }

    protected function tearDown(): void
    {
        DB::disconnect('ai_agent_schema_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_one_migration_creates_the_complete_ai_agent_schema(): void
    {
        $migration = require base_path('database/migrations/2026_08_11_000001_create_ai_agents_tables.php');

        $migration->up();

        foreach (['ai_provider_integrations', 'ai_agents', 'ai_provider_tool_syncs', 'ai_tool_invocations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to be created.");
        }

        $this->assertTrue(Schema::hasColumn('ai_tool_invocations', 'domain_uuid'));
        $this->assertTrue(Schema::hasColumn('ai_tool_invocations', 'provider_call_id'));
        $this->assertTrue(Schema::hasColumn('ai_tool_invocations', 'request_payload'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2026_08_16_000001_create_ai_provider_tool_sync_tables.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2026_08_17_000001_add_domain_uuid_to_ai_tool_invocations_table.php'));

        $migration->down();

        foreach (['ai_tool_invocations', 'ai_provider_tool_syncs', 'ai_agents', 'ai_provider_integrations'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected {$table} to be removed.");
        }
    }

    public function test_provider_integrations_are_recreated_with_a_uuid_primary_key(): void
    {
        $createMigration = require base_path('database/migrations/2026_08_11_000001_create_ai_agents_tables.php');
        $createMigration->up();

        DB::table('ai_provider_integrations')->insert([
            'provider' => 'retell',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_08_30_000001_recreate_disposable_tables_with_uuid_primary_keys.php');
        $migration->up();

        $this->assertSame(0, DB::table('ai_provider_integrations')->count());
        $this->assertTrue(Schema::hasColumn('ai_provider_integrations', 'ai_provider_integration_uuid'));

        $columns = collect(DB::select("PRAGMA table_info('ai_provider_integrations')"))
            ->keyBy('name');

        $this->assertSame(1, $columns->get('ai_provider_integration_uuid')->pk);
        $this->assertSame(0, $columns->get('provider')->pk);

        $integration = AiProviderIntegration::query()->create([
            'provider' => 'retell',
            'enabled' => false,
        ]);

        $this->assertNotNull($integration->ai_provider_integration_uuid);
        $this->assertTrue(Str::isUuid($integration->ai_provider_integration_uuid));
        $this->assertSame('ai_provider_integration_uuid', $integration->getKeyName());
        $this->assertDatabaseHas('ai_provider_integrations', [
            'ai_provider_integration_uuid' => $integration->ai_provider_integration_uuid,
            'provider' => 'retell',
        ]);
    }

    public function test_send_email_tool_records_the_validated_invocation_payload(): void
    {
        $migration = require base_path('database/migrations/2026_08_11_000001_create_ai_agents_tables.php');
        $migration->up();
        config()->set('cache.default', 'array');
        Bus::fake();

        $agent = new AiAgent([
            'ai_agent_uuid' => '11111111-1111-4111-8111-111111111111',
            'domain_uuid' => '22222222-2222-4222-8222-222222222222',
            'provider' => 'retell',
        ]);

        $result = app(AiSendEmailToolService::class)->queue($agent, 'call_payload_test', [
            'recipient' => ' Team@example.com ',
            'subject' => 'Caller follow-up',
            'fields' => [
                ['label' => 'Callback', 'value' => '555-0100'],
            ],
            'notes' => 'Call after 2 PM.',
        ]);

        $invocation = AiToolInvocation::query()->findOrFail($result['invocation']->getKey());

        $this->assertSame([
            'recipient' => 'team@example.com',
            'subject' => 'Caller follow-up',
            'fields' => [
                ['label' => 'Callback', 'value' => '555-0100'],
            ],
            'notes' => 'Call after 2 PM.',
        ], $invocation->request_payload);
    }
}
