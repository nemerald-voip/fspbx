<?php

namespace Tests\Unit;

use App\Events\ExtensionDeleted;
use App\Jobs\DeleteCallCenterAgent;
use App\Listeners\DeleteAgentWhenExtensionIsDeleted;
use App\Models\CallCenterAgents;
use App\Models\FusionCache;
use App\Services\BasicQueueService;
use App\Services\CallCenterAgentDeletionService;
use App\Services\FreeswitchEslService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class CallCenterAgentDeletionTest extends TestCase
{
    private string $cacheDirectory;
    private array $previousCache = [];
    private array $commands = [];

    public function createApplication()
    {
        if ($this->getName(false) !== 'test_basic_deletion_and_extension_listener_work_without_optional_modules') {
            return parent::createApplication();
        }

        // In a separate PHP process, hide module autoload/discovery without touching installed files.
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $paths) {
                if (str_starts_with($prefix, 'Modules\\')) {
                    $loader->setPsr4($prefix, []);
                }
            }
            $map = array_filter($loader->getClassMap(), fn ($name) => ! str_starts_with($name, 'Modules\\'), ARRAY_FILTER_USE_KEY);
            (new ReflectionProperty($loader, 'classMap'))->setValue($loader, $map);
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set('modules.paths.modules', '/tmp/fspbx-no-optional-modules');
            $app['config']->set('modules.scan.enabled', false);
            $app['config']->set('modules.cache.enabled', false);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-agent-delete-'.bin2hex(random_bytes(5));
        mkdir($this->cacheDirectory);
        foreach (['cacheType' => 'file', 'cacheLocation' => $this->cacheDirectory] as $property => $value) {
            $reflection = new ReflectionProperty(FusionCache::class, $property);
            $this->previousCache[$property] = $reflection->getValue();
            $reflection->setValue(null, $value);
        }
        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'number_alias', 'user_context'],
            'v_call_center_agents' => ['call_center_agent_uuid', 'domain_uuid', 'agent_id', 'agent_contact'],
            'v_call_center_queues' => ['call_center_queue_uuid', 'domain_uuid', 'queue_extension'],
            'v_call_center_tiers' => ['call_center_tier_uuid', 'domain_uuid', 'call_center_agent_uuid', 'call_center_queue_uuid', 'tier_level', 'tier_position'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                $table->primary($columns[0]);
            });
        }
        foreach (['a', 'b'] as $account) {
            DB::table('v_domains')->insert(['domain_uuid' => $account, 'domain_name' => $account.'.test']);
            DB::table('v_call_center_agents')->insert([
                'call_center_agent_uuid' => 'agent-'.$account, 'domain_uuid' => $account,
                'agent_id' => 'custom', 'agent_contact' => 'user/201@'.$account.'.test',
            ]);
            DB::table('v_call_center_queues')->insert([
                'call_center_queue_uuid' => 'queue-'.$account, 'domain_uuid' => $account, 'queue_extension' => '8200',
            ]);
            DB::table('v_call_center_tiers')->insert([
                'call_center_tier_uuid' => 'tier-'.$account, 'domain_uuid' => $account,
                'call_center_agent_uuid' => 'agent-'.$account, 'call_center_queue_uuid' => 'queue-'.$account,
            ]);
        }
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel()) {
            DB::rollBack();
        }
        DB::purge('sqlite');
        foreach ($this->previousCache as $property => $value) {
            (new ReflectionProperty(FusionCache::class, $property))->setValue(null, $value);
        }
        File::deleteDirectory($this->cacheDirectory);
        parent::tearDown();
    }

    private function cache(string $key): string
    {
        $path = $this->cacheDirectory.'/'.str_replace(':', '.', $key);
        file_put_contents($path, 'cached');
        return $path;
    }

    private function service(?int $failAt = null, bool $connected = true, $failure = '-ERR test failure'): CallCenterAgentDeletionService
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn($connected);
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldReceive('executeCommand')->andReturnUsing(function ($command, $disconnect) use ($failAt, $failure) {
            $this->assertFalse($disconnect);
            $this->commands[] = $command;
            if (str_contains($command, 'queue reload')) {
                $this->cache('configuration:callcenter.conf:queue-a');
            }
            $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-a']);
            $this->assertDatabaseHas('v_call_center_tiers', ['call_center_tier_uuid' => 'tier-a']);
            return count($this->commands) === $failAt ? $failure : '+OK';
        });
        return new CallCenterAgentDeletionService($esl);
    }

    public function test_deletion_preserves_contact_center_command_order_and_other_account(): void
    {
        $directory = $this->cache('directory:201@a.test');
        $foreign = $this->cache('directory:201@b.test');
        $xml = $this->cache('configuration:callcenter.conf:queue-a');
        Cache::put('contact_center:dashboard:agents:queue-a', ['stale']);
        Cache::put('contact_center:dashboard:agents:queue-b', ['keep']);
        session(['domain_uuid' => 'b', 'domain_name' => 'b.test']);
        $this->assertTrue($this->service()->delete('agent-a', 'a'));
        $this->assertSame([
            'callcenter_config tier del 8200@a.test agent-a',
            'callcenter_config queue reload 8200@a.test',
            'callcenter_config agent del agent-a',
        ], $this->commands);
        $this->assertDatabaseMissing('v_call_center_agents', ['call_center_agent_uuid' => 'agent-a']);
        $this->assertDatabaseMissing('v_call_center_tiers', ['call_center_tier_uuid' => 'tier-a']);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-b']);
        $this->assertDatabaseHas('v_call_center_tiers', ['call_center_tier_uuid' => 'tier-b']);
        $this->assertFileDoesNotExist($directory);
        $this->assertFileDoesNotExist($xml);
        $this->assertFileExists($foreign);
        if (class_exists(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class)) {
            $this->assertNull(Cache::get('contact_center:dashboard:agents:queue-a'));
            $this->assertSame(['keep'], Cache::get('contact_center:dashboard:agents:queue-b'));
        }
    }

    /** @dataProvider commandFailures */
    public function test_runtime_failure_keeps_database_rows_for_retry(int $step, $response): void
    {
        try {
            $this->service($step, true, $response)->delete('agent-a', 'a');
            $this->fail('Expected a runtime failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('FreeSWITCH', $exception->getMessage());
        }
        $this->assertCount($step, $this->commands);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-a']);
        $this->assertDatabaseHas('v_call_center_tiers', ['call_center_tier_uuid' => 'tier-a']);
        $this->commands = [];
        $this->assertTrue($this->service()->delete('agent-a', 'a'));
    }

    public static function commandFailures(): array
    {
        return [[1, '-ERR rejected'], [2, '-ERR rejected'], [3, '-ERR rejected'], [3, null]];
    }

    public function test_disconnected_switch_does_not_delete_database_agent(): void
    {
        try {
            $this->service(null, false)->delete('agent-a', 'a');
            $this->fail('Expected a connection failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('connect', $exception->getMessage());
        }
        $this->assertSame([], $this->commands);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-a']);
    }

    public function test_bulk_deletion_finishes_all_reloads_before_deleting_runtime_agents(): void
    {
        DB::table('v_call_center_agents')->insert([
            'call_center_agent_uuid' => 'agent-a2', 'domain_uuid' => 'a', 'agent_contact' => 'user/202@a.test',
        ]);
        DB::table('v_call_center_tiers')->insert([
            'call_center_tier_uuid' => 'tier-a2', 'domain_uuid' => 'a',
            'call_center_agent_uuid' => 'agent-a2', 'call_center_queue_uuid' => 'queue-a',
        ]);
        $this->assertSame(2, $this->service()->deleteMany(['agent-a2', 'agent-b', 'agent-a'], 'a'));
        $this->assertSame([
            'callcenter_config tier del 8200@a.test agent-a',
            'callcenter_config queue reload 8200@a.test',
            'callcenter_config tier del 8200@a.test agent-a2',
            'callcenter_config queue reload 8200@a.test',
            'callcenter_config agent del agent-a',
            'callcenter_config agent del agent-a2',
        ], $this->commands);
        $this->assertDatabaseCount('v_call_center_agents', 1);
        $this->assertDatabaseCount('v_call_center_tiers', 1);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-b']);
    }

    public function test_stale_or_wrong_domain_jobs_do_not_delete_agents(): void
    {
        $this->assertFalse($this->service()->delete('agent-a', 'b'));
        $this->assertFalse($this->service()->delete('missing', 'a'));
        $this->assertFalse($this->service()->delete('agent-a', 'a', 'user/202@a.test'));
        $this->assertSame([], $this->commands);
        $this->assertDatabaseCount('v_call_center_agents', 2);
    }

    /** @dataProvider foreignAssignments */
    public function test_foreign_assignments_are_rejected_before_runtime_changes(array $attributes): void
    {
        DB::table('v_call_center_tiers')->where('call_center_tier_uuid', 'tier-a')->update($attributes);
        try {
            $this->service()->delete('agent-a', 'a');
            $this->fail('Expected an account mismatch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('account', $exception->getMessage());
        }
        $this->assertSame([], $this->commands);
        $this->assertDatabaseCount('v_call_center_agents', 2);
        $this->assertDatabaseCount('v_call_center_tiers', 2);
    }

    public static function foreignAssignments(): array
    {
        return [[['domain_uuid' => 'b']], [['call_center_queue_uuid' => 'queue-b']]];
    }

    public function test_extension_listener_matches_contact_and_alias_only_in_original_domain(): void
    {
        Bus::fake();
        DB::table('v_call_center_agents')->insert([
            ['call_center_agent_uuid' => 'alias', 'domain_uuid' => 'a', 'agent_id' => 'another', 'agent_contact' => 'user/2001@a.test'],
            ['call_center_agent_uuid' => 'id-only', 'domain_uuid' => 'a', 'agent_id' => '201', 'agent_contact' => 'user/202@a.test'],
            ['call_center_agent_uuid' => 'custom', 'domain_uuid' => 'a', 'agent_id' => '201', 'agent_contact' => 'sofia/internal/201@a.test'],
            ['call_center_agent_uuid' => 'foreign', 'domain_uuid' => 'b', 'agent_id' => '201', 'agent_contact' => 'user/201@a.test'],
        ]);
        session(['domain_uuid' => 'b']);
        (new DeleteAgentWhenExtensionIsDeleted())->handle(new ExtensionDeleted([], [
            'extension' => '201', 'number_alias' => '2001', 'domain_uuid' => 'a',
        ], null));
        Bus::assertDispatchedTimes(DeleteCallCenterAgent::class, 2);
        foreach (['agent-a', 'alias'] as $uuid) {
            Bus::assertDispatched(DeleteCallCenterAgent::class, fn ($job) => $job->agentUuid === $uuid
                && $job->domainUuid === 'a' && $job->afterCommit === true);
        }
    }

    public function test_job_serialization_keeps_original_domain_and_contact_and_uses_shared_service(): void
    {
        $job = unserialize(serialize(new DeleteCallCenterAgent(CallCenterAgents::findOrFail('agent-a'))));
        DB::table('v_call_center_agents')->where('call_center_agent_uuid', 'agent-a')->update(['domain_uuid' => 'b']);
        $throttle = Mockery::mock();
        Redis::shouldReceive('throttle')->with('system')->andReturn($throttle);
        $throttle->shouldReceive('allow')->with(2)->andReturnSelf();
        $throttle->shouldReceive('every')->with(1)->andReturnSelf();
        $throttle->shouldReceive('then')->andReturnUsing(fn ($callback) => $callback());
        $deletion = Mockery::mock(CallCenterAgentDeletionService::class);
        $deletion->shouldReceive('delete')->once()->with('agent-a', 'a', 'user/201@a.test')->andReturn(false);
        $job->handle($deletion);
    }

    public function test_legacy_job_without_domain_snapshot_cannot_delete_any_agent(): void
    {
        $job = (new \ReflectionClass(DeleteCallCenterAgent::class))->newInstanceWithoutConstructor();
        $job->agent = CallCenterAgents::findOrFail('agent-a');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no account snapshot');
        $job->handle(Mockery::mock(CallCenterAgentDeletionService::class));
    }

    public function test_basic_queues_use_the_shared_service_with_the_selected_domain(): void
    {
        session(['domain_uuid' => 'a', 'domain_name' => 'a.test']);
        $service = Mockery::mock(CallCenterAgentDeletionService::class);
        $service->shouldReceive('deleteMany')->once()->with(['agent-a', 'agent-b'], 'a')->andReturn(1);
        $this->app->instance(CallCenterAgentDeletionService::class, $service);
        $dialplan = Mockery::mock(\App\Services\DialplanService::class);
        $dialplan->shouldReceive('clearDialplanCache')->with('a.test');
        $this->app->instance(\App\Services\DialplanService::class, $dialplan);
        $this->assertSame(1, (new BasicQueueService())->deleteAgents(CallCenterAgents::all()));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_basic_deletion_and_extension_listener_work_without_optional_modules(): void
    {
        $this->assertFalse(class_exists(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class));
        $this->assertContains(DeleteAgentWhenExtensionIsDeleted::class,
            (new \App\Providers\EventServiceProvider($this->app))->listens()[ExtensionDeleted::class]);
        $this->test_extension_listener_matches_contact_and_alias_only_in_original_domain();
        session(['domain_uuid' => 'a', 'domain_name' => 'a.test']);
        $this->app->bind(CallCenterAgentDeletionService::class, fn () => $this->service());
        $dialplan = Mockery::mock(\App\Services\DialplanService::class);
        $dialplan->shouldReceive('clearDialplanCache')->with('a.test');
        $this->app->instance(\App\Services\DialplanService::class, $dialplan);
        $this->assertSame(1, (new BasicQueueService())->deleteAgents(CallCenterAgents::whereKey('agent-a')->get()));
        $this->assertDatabaseMissing('v_call_center_agents', ['call_center_agent_uuid' => 'agent-a']);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => 'agent-b']);
        $this->assertSame('callcenter_config agent del agent-a', end($this->commands));
    }

    public function test_jobs_wait_for_commit_and_are_discarded_on_rollback(): void
    {
        Bus::fake();
        $listener = new DeleteAgentWhenExtensionIsDeleted();
        $event = new ExtensionDeleted([], ['extension' => '201', 'domain_uuid' => 'a'], null);
        DB::beginTransaction();
        $listener->handle($event);
        Bus::assertNothingDispatched();
        DB::rollBack();
        Bus::assertNothingDispatched();
        DB::beginTransaction();
        $listener->handle($event);
        Bus::assertNothingDispatched();
        DB::commit();
        Bus::assertDispatchedTimes(DeleteCallCenterAgent::class, 1);
    }

    public function test_contact_center_action_checks_tenant_before_invoking_shared_service(): void
    {
        if (! class_exists(\Modules\ContactCenter\Http\Controllers\SettingsController::class)) {
            $this->markTestSkipped('Contact Center is optional.');
        }
        session(['domain_uuid' => 'b', 'permissions' => [(object) ['permission_name' => 'contact_center_settings_edit']]]);
        $controller = new \Modules\ContactCenter\Http\Controllers\SettingsController(
            Mockery::mock(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class),
        );
        $service = Mockery::mock(CallCenterAgentDeletionService::class);
        $this->app->instance(CallCenterAgentDeletionService::class, $service);
        $this->assertSame(403, $controller->destroyAgent(CallCenterAgents::findOrFail('agent-a'))->status());
        session(['domain_uuid' => 'a']);
        $service->shouldReceive('delete')->once()->with('agent-a', 'a')->andReturn(true);
        $this->assertSame(200, $controller->destroyAgent(CallCenterAgents::findOrFail('agent-a'))->status());
    }
}
