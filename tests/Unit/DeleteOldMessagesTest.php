<?php

namespace Tests\Unit;

use App\Jobs\DeleteOldMessages;
use App\Console\Kernel;
use App\Models\Messages;
use App\Models\ScheduledJobExecution;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageRetentionService;
use Illuminate\Config\Repository;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\TestCase;

class DeleteOldMessagesTest extends TestCase
{
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;
    private MessageMediaObjectStorageService $storage;
    private ActiveNodeResolver $owner;
    private ScheduledJobExecution $execution;
    private MessageRetentionService $retention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository());
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstances();
        $db = new Manager($app);
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $db->bootEloquent();
        Model::unsetEventDispatcher();
        $app->instance('db', $db->getDatabaseManager());
        $app->instance('db.schema', $db->getConnection()->getSchemaBuilder());
        Carbon::setTestNow('2026-11-09 12:00:00');
        Log::spy();

        Schema::create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary();
            $table->uuid('domain_uuid');
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('message_group_uuid')->nullable();
            $table->foreign('message_group_uuid')->references('message_group_uuid')->on('message_groups');
            $table->string('status')->nullable();
            $table->json('media')->nullable();
            $table->timestamps();
        });
        foreach (['message_user_reads', 'message_user_hides', 'ringotel_message_syncs', 'ringotel_message_deliveries',
            'message_api_requests', 'outbound_message_webhook_events'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->uuid('message_uuid');
                $table->foreign('message_uuid')->references('message_uuid')->on('messages');
                if ($name === 'outbound_message_webhook_events') $table->string('status')->default('delivered');
                if ($name === 'message_api_requests') $table->timestamp('expires_at')->nullable();
            });
        }

        $this->storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $this->owner = Mockery::mock(ActiveNodeResolver::class);
        $this->execution = new ScheduledJobExecution();
        $this->owner->shouldReceive('assertExecution')->byDefault();
        $this->owner->shouldReceive('withExecution')->byDefault()
            ->andReturnUsing(fn ($execution, $write) => DB::transaction($write));
        $this->retention = new MessageRetentionService($this->storage, $this->owner);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            Carbon::setTestNow();
            DB::disconnect();
            if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
            else Model::unsetConnectionResolver();
            if ($this->previousDispatcher) Model::setEventDispatcher($this->previousDispatcher);
            Container::setInstance($this->previousContainer);
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Facade::clearResolvedInstances();
            parent::tearDown();
        }
    }

    public function test_retention_uses_created_at_and_preserves_the_boundary_and_pending_messages(): void
    {
        $old = $this->message(['created_at' => now()->subDays(90)->subSecond(), 'updated_at' => now()]);
        $boundary = $this->message(['created_at' => now()->subDays(90)]);
        $recent = $this->message(['created_at' => now()->subDay()]);
        $queued = $this->message(['status' => 'queued']);
        $this->runRetention();
        $this->assertFalse(Messages::whereKey($old)->exists());
        $this->assertEqualsCanonicalizing([$boundary, $recent, $queued], Messages::pluck('message_uuid')->all());
    }

    public function test_deletes_stored_media_and_compression_originals_including_legacy_double_encoding(): void
    {
        $item = $this->object('compressed.jpg');
        $item['photo_compression']['original'] = $this->object('original.png');
        $id = $this->message(['media' => json_encode(json_encode([$item, $item, 'https://carrier.example/photo']))]);
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->with('account-a', 'media', 'message-media/compressed.jpg');
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->with('account-a', 'media', 'message-media/original.png');
        $this->runRetention();
        $this->assertFalse(Messages::whereKey($id)->exists());
    }

    public function test_shared_objects_survive_until_the_last_message_expires_even_across_accounts(): void
    {
        $object = $this->object('shared.jpg');
        $old = $this->message(['media' => json_encode([$object])]);
        $new = $this->message(['domain_uuid' => 'account-b', 'created_at' => now(),
            'media' => json_encode(json_encode([['photo_compression' => ['original' => $object]]]))]);
        $this->runRetention();
        $this->assertFalse(Messages::whereKey($old)->exists());
        $this->assertTrue(Messages::whereKey($new)->exists());

        DB::table('messages')->where('message_uuid', $new)->update(['created_at' => now()->subDays(91)]);
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->with('account-b', 'media', 'message-media/shared.jpg');
        $this->runRetention();
        $this->assertSame(0, Messages::count());
    }

    public function test_old_messages_sharing_one_object_delete_it_only_once(): void
    {
        $this->message(['media' => json_encode([$this->object('shared.jpg')])]);
        $this->message(['media' => json_encode([$this->object('shared.jpg')])]);
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->with('account-a', 'media', 'message-media/shared.jpg');
        $this->runRetention();
        $this->assertSame(0, Messages::count());
    }

    public function test_storage_failure_keeps_the_message_and_related_rows_for_retry(): void
    {
        $id = $this->message(['media' => json_encode([$this->object('retry.jpg')])]);
        DB::table('message_user_reads')->insert(['message_uuid' => $id]);
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        $this->runRetention();
        $this->assertTrue(Messages::whereKey($id)->exists());
        $this->assertSame(1, DB::table('message_user_reads')->count());
        $this->storage->shouldReceive('deleteObjectForDomain')->once()->andReturnNull();
        $this->runRetention();
        $this->assertFalse(Messages::whereKey($id)->exists());
        $this->assertSame(0, DB::table('message_user_reads')->count());
    }

    public function test_related_rows_and_only_empty_groups_are_removed(): void
    {
        DB::table('message_groups')->insert([
            ['message_group_uuid' => 'empty', 'domain_uuid' => 'account-a'],
            ['message_group_uuid' => 'active', 'domain_uuid' => 'account-a'],
        ]);
        $id = $this->message(['message_group_uuid' => 'empty']);
        $this->message(['message_group_uuid' => 'active']);
        $recent = $this->message(['message_group_uuid' => 'active', 'created_at' => now()]);
        foreach (['message_user_reads', 'message_user_hides', 'ringotel_message_syncs', 'ringotel_message_deliveries',
            'message_api_requests', 'outbound_message_webhook_events'] as $table) {
            DB::table($table)->insert([['message_uuid' => $id], ['message_uuid' => $recent]]);
        }
        $this->runRetention();
        $this->assertSame(['active'], DB::table('message_groups')->pluck('message_group_uuid')->all());
        $this->assertSame([$recent], DB::table('message_api_requests')->pluck('message_uuid')->all());
        $this->assertSame([$recent], DB::table('outbound_message_webhook_events')->pluck('message_uuid')->all());
    }

    public function test_url_only_media_is_not_fetched_or_deleted_remotely(): void
    {
        $this->message(['media' => json_encode(['https://carrier.example/photo', ['url' => 'https://other.example/photo'], null])]);
        $this->runRetention();
        $this->assertSame(0, Messages::count());
    }

    public function test_missing_optional_tables_do_not_prevent_cleanup(): void
    {
        Schema::drop('message_api_requests');
        Schema::drop('outbound_message_webhook_events');
        $this->message();
        $this->runRetention();
        $this->assertSame(0, Messages::count());
    }

    public function test_non_positive_retention_is_rejected_without_deleting(): void
    {
        $id = $this->message();
        foreach ([0, -1] as $days) {
            try {
                $this->retention->deleteExpired($days, $this->execution);
                $this->fail('Invalid retention must be rejected.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertTrue(Messages::whereKey($id)->exists());
            }
        }
    }

    public function test_pending_webhooks_and_unexpired_api_requests_preserve_messages_and_media(): void
    {
        $webhook = $this->message(['media' => json_encode([$this->object('webhook.jpg')])]);
        $request = $this->message(['media' => json_encode([$this->object('request.jpg')])]);
        DB::table('outbound_message_webhook_events')->insert(['message_uuid' => $webhook, 'status' => 'queued']);
        DB::table('message_api_requests')->insert(['message_uuid' => $request, 'expires_at' => now()->addHour()]);
        $this->runRetention();
        $this->assertSame(2, Messages::count());
    }

    public function test_worker_finishes_its_claim_after_success(): void
    {
        $this->owner->shouldReceive('claimExecution')->once()->with('message_retention', 'messages', 300)->andReturn($this->execution);
        $this->owner->shouldReceive('finishExecution')->once()->with($this->execution);
        $this->message();
        (new DeleteOldMessages())->handle($this->retention, $this->owner);
        $this->assertSame(0, Messages::count());
    }

    public function test_standby_job_does_not_start_cleanup(): void
    {
        $this->owner->shouldReceive('claimExecution')->once()->with('message_retention', 'messages', 300)->andReturnNull();
        $retention = Mockery::mock(MessageRetentionService::class);
        (new DeleteOldMessages())->handle($retention, $this->owner);
        $this->assertSame(90, (new DeleteOldMessages())->days);
    }

    public function test_object_deletion_uses_account_settings_and_the_stored_bucket_with_bounded_requests(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('deleteObject')->once()->with([
            'Bucket' => 'stored-bucket', 'Key' => 'message-media/photo.jpg',
            '@http' => ['connect_timeout' => 5, 'timeout' => 20], '@retries' => 0,
        ]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $settings = ['bucket' => 'current-bucket'];
        $storage->shouldReceive('getSettingsForDomain')->once()->with('account-a')->andReturn($settings);
        $storage->shouldReceive('getClientForSettings')->once()->with($settings)->andReturn($client);
        $storage->deleteObjectForDomain('account-a', 'stored-bucket', 'message-media/photo.jpg');
        $this->addToAssertionCount(1);
    }

    public function test_object_deletion_rejects_unrelated_storage_paths(): void
    {
        $storage = Mockery::mock(MessageMediaObjectStorageService::class)->makePartial();
        $this->expectException(\RuntimeException::class);
        $storage->deleteObjectForDomain('account-a', 'media', 'call-recordings/recording.wav');
    }

    public function test_revoked_execution_stops_before_deleting_media_or_rows(): void
    {
        $id = $this->message(['media' => json_encode([$this->object('keep.jpg')])]);
        $this->owner->shouldReceive('assertExecution')->andThrow(new \RuntimeException('Revoked', 409));
        try {
            $this->runRetention();
            $this->fail('Revoked execution must stop.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
            $this->assertTrue(Messages::whereKey($id)->exists());
        }
    }

    public function test_keyset_paging_does_not_skip_messages_during_deletion(): void
    {
        for ($i = 0; $i < 205; $i++) $this->message();
        $this->runRetention();
        $this->assertSame(0, Messages::count());
    }

    public function test_defaults_are_disabled_and_ninety_days_without_overwriting_existing_choices(): void
    {
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->uuid('default_setting_uuid')->primary();
            foreach (['category', 'subcategory', 'name', 'value', 'description'] as $field) $table->text('default_setting_'.$field);
            $table->boolean('default_setting_enabled');
        });
        $seeder = new class extends \Database\Seeders\DatabaseSeeder {
            public function getMacAddress() { return 'test-mac'; }
        };
        $method = new \ReflectionMethod(\Database\Seeders\DatabaseSeeder::class, 'createDefaultSettings');
        $method->setAccessible(true);
        $method->invoke($seeder);
        $settings = DB::table('v_default_settings')->where('default_setting_category', 'scheduled_jobs');
        $values = (clone $settings)->pluck('default_setting_value', 'default_setting_subcategory');
        $this->assertSame('false', $values['delete_old_messages']);
        $this->assertSame('90', $values['days_keep_messages']);
        foreach (array_keys(\App\Jobs\DeleteOldMessagingRecords::TABLES) as $table) {
            $this->assertSame('true', $values['delete_old_' . $table]);
            $this->assertSame('30', $values['days_keep_' . $table]);
            $this->assertTrue((bool) (clone $settings)->where('default_setting_subcategory', 'delete_old_' . $table)->value('default_setting_enabled'));
        }
        (clone $settings)->where('default_setting_subcategory', 'delete_old_messages')->update(['default_setting_value' => 'true']);
        (clone $settings)->where('default_setting_subcategory', 'days_keep_messages')->update(['default_setting_value' => '125']);
        $method->invoke($seeder);
        $values = (clone $settings)->pluck('default_setting_value', 'default_setting_subcategory');
        $this->assertSame('true', $values['delete_old_messages']);
        $this->assertSame('125', $values['days_keep_messages']);
        $this->assertSame(2, (clone $settings)->whereIn('default_setting_subcategory', ['delete_old_messages', 'days_keep_messages'])->count());
    }

    /** @dataProvider scheduleSettings */
    public function test_schedule_respects_enablement_and_validates_retention(array $settings, ?int $expectedDays): void
    {
        Cache::shouldReceive('remember')->once()->andReturn($settings);
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('dailyAt', 'daily', 'timezone', 'when', 'withoutOverlapping', 'everyFiveMinutes')->andReturnSelf();
        $schedule = Mockery::mock(Schedule::class);
        $schedule->shouldReceive('command', 'call')->andReturn($event);
        $jobs = [];
        $schedule->shouldReceive('job')->andReturnUsing(function ($job) use (&$jobs, $event) {
            if ($job instanceof DeleteOldMessages) $jobs[] = $job;
            return $event;
        });
        $kernel = (new \ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);
        $this->assertSame($expectedDays === null ? [] : [$expectedDays], array_map(fn ($job) => $job->days, $jobs));
    }

    public static function scheduleSettings(): array
    {
        return [
            [[], null],
            [['delete_old_messages' => 'false', 'days_keep_messages' => '90'], null],
            [['delete_old_messages' => 'true'], 90],
            [['delete_old_messages' => 'true', 'days_keep_messages' => '125'], 125],
            [['delete_old_messages' => 'true', 'days_keep_messages' => '0'], null],
            [['delete_old_messages' => 'true', 'days_keep_messages' => '-1'], null],
            [['delete_old_messages' => 'true', 'days_keep_messages' => 'invalid'], null],
        ];
    }

    private function runRetention(): void
    {
        $this->assertNull($this->retention->deleteExpired(90, $this->execution));
    }

    private function object(string $name): array
    {
        return ['bucket' => 'media', 'object_key' => 'message-media/'.$name];
    }

    private function message(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('messages')->insert(array_replace([
            'message_uuid' => $id, 'domain_uuid' => 'account-a', 'message_group_uuid' => null,
            'status' => 'delivered', 'media' => null, 'created_at' => now()->subDays(91), 'updated_at' => now()->subDays(91),
        ], $attributes));
        return $id;
    }
}
