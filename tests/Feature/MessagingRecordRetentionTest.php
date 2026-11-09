<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Jobs\DeleteOldMessagingRecords;
use App\Models\ScheduledJobExecution;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MessagingRecordRetentionTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_09_000001_create_outbound_messaging_tables.php';
    private ActiveNodeResolver $owner;
    private ScheduledJobExecution $execution;
    private ?string $schema = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'messaging_retention_test',
            'database.connections.messaging_retention_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $lab = getenv('FSPBX_OUTBOUND_MESSAGE_LAB');
        if ($lab) {
            if (!str_starts_with(realpath($lab) ?: '', '/tmp/fspbx-message-settings.')) {
                throw new \RuntimeException('Disposable PostgreSQL required.');
            }
            $this->schema = 'retention_' . bin2hex(random_bytes(8));
            config(['database.connections.messaging_retention_test' => [
                'driver' => 'pgsql', 'host' => $lab, 'port' => 16551, 'database' => 'postgres',
                'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '',
                'search_path' => $this->schema, 'sslmode' => 'disable',
            ]]);
        }
        DB::purge('messaging_retention_test');
        if ($lab) {
            $this->assertSame($lab . '/data', DB::selectOne('show data_directory')->data_directory);
            DB::statement('CREATE SCHEMA ' . $this->schema);
        }
        (require base_path(self::MIGRATION))->up();
        $this->travelTo(now()->startOfSecond());
        $this->execution = new ScheduledJobExecution;
        $this->owner = Mockery::mock(ActiveNodeResolver::class);
        $this->owner->shouldReceive('claimExecution')->byDefault()->andReturn($this->execution);
        $this->owner->shouldReceive('withExecution')->byDefault()->andReturnUsing(fn ($execution, $write) => DB::transaction($write));
        $this->owner->shouldReceive('finishExecution')->byDefault();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        if ($this->schema) DB::statement('DROP SCHEMA ' . $this->schema . ' CASCADE');
        DB::purge('messaging_retention_test');
        parent::tearDown();
    }

    public function test_combined_migration_creates_all_tables_and_preserves_existing_data_on_rerun(): void
    {
        $ids = [];
        foreach (array_keys(DeleteOldMessagingRecords::TABLES) as $table) $ids[$table] = $this->record($table);
        (require base_path(self::MIGRATION))->up();
        foreach ($ids as $table => $id) {
            $this->assertSame([$id], DB::table($table)->pluck(DeleteOldMessagingRecords::TABLES[$table])->all());
        }

        // Covers an installation where only the earlier API migration ran.
        Schema::drop('outbound_webhook_logs');
        (require base_path(self::MIGRATION))->up();
        $this->assertTrue(Schema::hasTable('outbound_webhook_logs'));
        $this->assertSame(1, DB::table('message_api_requests')->count());
        $this->assertSame(1, DB::table('outbound_message_webhook_events')->count());
        (require base_path(self::MIGRATION))->down();
        foreach (array_keys(DeleteOldMessagingRecords::TABLES) as $table) $this->assertFalse(Schema::hasTable($table));
    }

    public function test_api_cleanup_only_deletes_old_expired_requests(): void
    {
        $table = 'message_api_requests';
        $old = $this->record($table);
        $boundary = $this->record($table, ['created_at' => now()->subDays(30)]);
        $recent = $this->record($table, ['created_at' => now()->subDay()]);
        $active = $this->record($table, ['expires_at' => now()->addDay()]);
        (new DeleteOldMessagingRecords($table))->handle($this->owner);
        $this->assertFalse(DB::table($table)->where('message_api_request_uuid', $old)->exists());
        $this->assertEqualsCanonicalizing([$boundary, $recent, $active], DB::table($table)->pluck('message_api_request_uuid')->all());
    }

    /** @dataProvider webhookTables */
    public function test_webhook_cleanup_preserves_active_deliveries_and_recent_history(string $table): void
    {
        $this->record($table, ['status' => 'delivered']);
        $this->record($table, ['status' => 'failed']);
        $keep = [
            $this->record($table, ['status' => 'pending']),
            $this->record($table, ['status' => 'queued']),
            $this->record($table, ['status' => 'retrying']),
            $this->record($table, ['created_at' => now()->subDays(30)]),
            $this->record($table, ['created_at' => now()->subDays(29)]),
        ];
        (new DeleteOldMessagingRecords($table))->handle($this->owner);
        $this->assertEqualsCanonicalizing($keep, DB::table($table)->pluck(DeleteOldMessagingRecords::TABLES[$table])->all());
    }

    public static function webhookTables(): array
    {
        return [['outbound_message_webhook_events'], ['outbound_webhook_logs']];
    }

    public function test_cleanup_does_not_delete_message_history(): void
    {
        Schema::create('messages', fn (Blueprint $table) => $table->uuid('message_uuid')->primary());
        $id = (string) Str::uuid();
        DB::table('messages')->insert(['message_uuid' => $id]);
        foreach (array_keys(DeleteOldMessagingRecords::TABLES) as $table) {
            $this->record($table);
            (new DeleteOldMessagingRecords($table))->handle($this->owner);
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame([$id], DB::table('messages')->pluck('message_uuid')->all());
    }

    public function test_multiple_batches_are_deleted_without_skipping_records(): void
    {
        for ($i = 0; $i < 501; $i++) $this->record('message_api_requests');
        (new DeleteOldMessagingRecords('message_api_requests'))->handle($this->owner);
        $this->assertSame(0, DB::table('message_api_requests')->count());
    }

    public function test_standby_does_not_delete_records(): void
    {
        $this->record('outbound_webhook_logs');
        $this->owner->shouldReceive('claimExecution')->once()->with('messaging_record_retention', 'outbound_webhook_logs', 300)->andReturnNull();
        $this->owner->shouldNotReceive('withExecution');
        (new DeleteOldMessagingRecords('outbound_webhook_logs'))->handle($this->owner);
        $this->assertSame(1, DB::table('outbound_webhook_logs')->count());
    }

    public function test_revocation_stops_cleanup_and_finishes_the_claim_as_failed(): void
    {
        $this->record('message_api_requests');
        $this->owner->shouldReceive('withExecution')->once()->andThrow(new \RuntimeException('Revoked', 409));
        $this->owner->shouldReceive('finishExecution')->once()->with($this->execution, 'failed', Mockery::type('string'));
        try {
            (new DeleteOldMessagingRecords('message_api_requests'))->handle($this->owner);
            $this->fail('Revocation must stop deletion.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
            $this->assertSame(1, DB::table('message_api_requests')->count());
        }
    }

    public function test_missing_table_is_skipped_before_claiming_work(): void
    {
        Schema::drop('outbound_webhook_logs');
        $this->owner->shouldNotReceive('claimExecution');
        (new DeleteOldMessagingRecords('outbound_webhook_logs'))->handle($this->owner);
        $this->assertFalse(Schema::hasTable('outbound_webhook_logs'));
    }

    /** @dataProvider invalidPolicies */
    public function test_invalid_retention_and_unrelated_tables_are_rejected(string $table, int $days): void
    {
        $this->owner->shouldNotReceive('claimExecution');
        $this->expectException(\InvalidArgumentException::class);
        (new DeleteOldMessagingRecords($table, $days))->handle($this->owner);
    }

    public static function invalidPolicies(): array
    {
        return [['messages', 30], ['outbound_webhook_logs', 0], ['outbound_webhook_logs', -1]];
    }

    public function test_scheduler_applies_each_enable_switch_and_retention_value_independently(): void
    {
        Cache::shouldReceive('remember')->once()->andReturn([
            'delete_old_message_api_requests' => 'true',
            'delete_old_outbound_message_webhook_events' => 'true', 'days_keep_outbound_message_webhook_events' => '45',
            'delete_old_outbound_webhook_logs' => 'false',
        ]);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('dailyAt', 'daily', 'timezone', 'when', 'withoutOverlapping', 'everyFiveMinutes')->andReturnSelf();
        $schedule = Mockery::mock(Schedule::class);
        $schedule->shouldReceive('command', 'call')->andReturn($event);
        $jobs = [];
        $schedule->shouldReceive('job')->andReturnUsing(function ($job) use (&$jobs, $event) {
            if ($job instanceof DeleteOldMessagingRecords) $jobs[$job->table] = $job->days;
            return $event;
        });
        $method = new \ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke((new \ReflectionClass(Kernel::class))->newInstanceWithoutConstructor(), $schedule);
        $this->assertSame(['message_api_requests' => 30, 'outbound_message_webhook_events' => 45], $jobs);
    }

    private function record(string $table, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        $domain = '11111111-1111-4111-8111-111111111111';
        $common = ['domain_uuid' => $domain, 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)];
        $row = match ($table) {
            'message_api_requests' => ['message_api_request_uuid' => $id, 'user_uuid' => $domain, 'operation' => 'send',
                'key_hash' => hash('sha256', $id), 'fingerprint' => hash('sha256', $id), 'message_uuid' => $id,
                'response' => '{}', 'expires_at' => now()->subDays(30)],
            'outbound_message_webhook_events' => ['outbound_message_webhook_event_uuid' => $id, 'message_uuid' => $id,
                'event_type' => 'message.sent', 'fingerprint' => hash('sha256', $id), 'endpoint_url' => 'https://example.test/hook',
                'payload' => '{}', 'media_items' => '[]', 'status' => 'delivered'],
            'outbound_webhook_logs' => ['outbound_webhook_log_uuid' => $id, 'delivery_key' => hash('sha256', $id), 'event_id' => $id,
                'event_type' => 'message.sent', 'http_method' => 'POST', 'endpoint_url' => 'https://example.test/hook',
                'status' => 'delivered', 'attempt_history' => '[]'],
        };
        DB::table($table)->insert(array_replace($common, $row, $attributes));
        return $id;
    }
}
