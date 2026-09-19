<?php

namespace Tests\Unit;

use App\Events\ExtensionUpdated;
use App\Jobs\RefreshCallCenterAgent;
use App\Listeners\UpdateAgentWhenExtensionIsUpdated;
use App\Models\CallCenterAgents;
use App\Models\Extensions;
use App\Models\FusionCache;
use App\Observers\ExtensionObserver;
use App\Services\FreeswitchEslService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class CallCenterAgentUpdateTest extends TestCase
{
    private string $domain = 'c5c00594-8444-4c5c-a49c-bd62169db58b';
    private string $foreignDomain = 'd99b8f92-e47e-4654-aa7b-afc66073bd9a';
    private string $agentUuid;
    private string $cacheDirectory;
    private array $previousCache = [];

    public function createApplication()
    {
        if ($this->getName(false) !== 'test_extension_updates_work_without_optional_modules') {
            return parent::createApplication();
        }
        // Hide optional modules only in this separate test process.
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $paths) {
                if (str_starts_with($prefix, 'Modules\\')) { $loader->setPsr4($prefix, []); }
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
        Bus::fake();
        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-agent-update-'.bin2hex(random_bytes(5));
        mkdir($this->cacheDirectory);
        foreach (['cacheType' => 'file', 'cacheLocation' => $this->cacheDirectory] as $property => $value) {
            $reflection = new ReflectionProperty(FusionCache::class, $property);
            $this->previousCache[$property] = $reflection->getValue();
            $reflection->setValue(null, $value);
        }
        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'number_alias', 'user_context'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id'],
            'v_call_center_agents' => ['call_center_agent_uuid', 'domain_uuid', 'agent_id', 'agent_password', 'agent_name', 'agent_contact', 'agent_type', 'agent_status'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) { $table->string($column)->nullable(); }
                $table->primary($columns[0]);
            });
        }
        DB::table('v_domains')->insert([
            ['domain_uuid' => $this->domain, 'domain_name' => 'office201.test'],
            ['domain_uuid' => $this->foreignDomain, 'domain_name' => 'other.test'],
        ]);
        $this->agentUuid = $this->agent();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel()) { DB::rollBack(); }
        DB::purge('sqlite');
        foreach ($this->previousCache as $property => $value) {
            (new ReflectionProperty(FusionCache::class, $property))->setValue(null, $value);
        }
        File::deleteDirectory($this->cacheDirectory);
        parent::tearDown();
    }

    private function agent(array $attributes = []): string
    {
        $row = $attributes + ['call_center_agent_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->domain,
            'agent_id' => 'custom', 'agent_password' => 'custom-password', 'agent_name' => 'Ken',
            'agent_contact' => 'user/201@office201.test', 'agent_type' => 'callback', 'agent_status' => 'On Break'];
        DB::table('v_call_center_agents')->insert($row);
        return $row['call_center_agent_uuid'];
    }

    private function event(array $old = [], array $new = []): ExtensionUpdated
    {
        $old += ['extension_uuid' => 'fc02c55e-17b8-4caa-a1ae-e5a8c15f74a6', 'domain_uuid' => $this->domain,
            'extension' => '201', 'number_alias' => null, 'effective_caller_id_name' => 'Ken'];
        return new ExtensionUpdated($new + ['extension' => '205', 'effective_caller_id_name' => 'Ken Dever'] + $old, $old, null);
    }

    private function update(?ExtensionUpdated $event = null): void
    {
        (new UpdateAgentWhenExtensionIsUpdated())->handle($event ?? $this->event());
    }

    private function switch($reply = '+OK', $rows = null, bool $connected = true): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturn($connected);
        $esl->shouldReceive('disconnect')->once();
        if ($connected) {
            $esl->shouldReceive('executeCommand')->once()->with('callcenter_config agent reload '.$this->agentUuid, false)->andReturn($reply);
            if ($reply === '+OK') {
                $esl->shouldReceive('executeCommand')->once()->with('callcenter_config agent list '.$this->agentUuid, false)
                    ->andReturn($rows ?? [['name' => $this->agentUuid, 'type' => 'callback',
                        'contact' => '{call_timeout=20,domain_name=office201.test,sip_h_caller_destination=${caller_destination},execute_on_pre_bridge=record_session ${uuid}.wav}user/205@office201.test']]);
            }
        }
        $this->app->instance(FreeswitchEslService::class, $esl);
    }

    public function test_all_matching_contacts_update_without_touching_other_accounts_or_custom_contacts(): void
    {
        $second = $this->agent(['agent_id' => 'another-custom-id']);
        $untouched = [
            $this->agent(['domain_uuid' => $this->foreignDomain, 'agent_id' => '201']),
            $this->agent(['agent_id' => '201', 'agent_contact' => 'user/201@other.test']),
            $this->agent(['agent_id' => '201', 'agent_contact' => 'sofia/gateway/trunk/201']),
            $this->agent(['agent_id' => '201', 'agent_contact' => 'user/2010@office201.test']),
        ];
        $before = CallCenterAgents::whereKey($untouched)->get()->keyBy('call_center_agent_uuid')->toArray();
        $this->update();
        foreach ([$this->agentUuid, $second] as $id) {
            $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $id,
                'agent_contact' => 'user/205@office201.test', 'agent_name' => 'Ken Dever',
                'agent_id' => '205', 'agent_password' => '205', 'agent_status' => 'On Break']);
        }
        $this->assertSame($before, CallCenterAgents::whereKey($untouched)->get()->keyBy('call_center_agent_uuid')->toArray());
        Bus::assertDispatchedTimes(RefreshCallCenterAgent::class, 2);
    }

    public function test_name_only_changes_keep_existing_credential_reset_behavior(): void
    {
        $this->update($this->event([], ['extension' => '201']));
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid,
            'agent_contact' => 'user/201@office201.test', 'agent_name' => 'Ken Dever', 'agent_id' => '201', 'agent_password' => '201']);
    }

    /** @dataProvider aliasChanges */
    public function test_alias_contacts_follow_alias_changes_or_fall_back_to_extension(?string $alias, string $target): void
    {
        $id = $this->agent(['agent_contact' => 'user/901@office201.test']);
        $this->update($this->event(['number_alias' => '901'], ['number_alias' => $alias]));
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $id, 'agent_contact' => 'user/'.$target.'@office201.test']);
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid, 'agent_contact' => 'user/205@office201.test']);
        Bus::assertDispatchedTimes(RefreshCallCenterAgent::class, 2);
    }

    public static function aliasChanges(): array { return [['902', '902'], ['901', '901'], [null, '205']]; }

    public function test_noop_and_cross_account_events_do_not_update_agents_or_queue_work(): void
    {
        $before = CallCenterAgents::findOrFail($this->agentUuid)->getAttributes();
        $this->update($this->event([], ['extension' => '201', 'effective_caller_id_name' => 'Ken']));
        $this->update($this->event([], ['domain_uuid' => $this->foreignDomain]));
        $this->assertSame($before, CallCenterAgents::findOrFail($this->agentUuid)->getAttributes());
        Bus::assertNothingDispatched();
    }

    public function test_agent_updates_and_dispatch_follow_the_extension_transaction(): void
    {
        DB::beginTransaction();
        $this->update();
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid, 'agent_id' => '205']);
        Bus::assertNothingDispatched();
        DB::rollBack();
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid, 'agent_id' => 'custom']);
        Bus::assertNothingDispatched();
        DB::beginTransaction();
        $this->update();
        Bus::assertNothingDispatched();
        DB::commit();
        Bus::assertDispatched(RefreshCallCenterAgent::class, fn ($job) => $job->agentUuid === $this->agentUuid && $job->domainUuid === $this->domain);
    }

    public function test_runtime_job_verifies_the_latest_saved_contact_and_clears_xml_cache(): void
    {
        $job = unserialize(serialize(new RefreshCallCenterAgent($this->agentUuid, $this->domain)));
        $this->update();
        $cache = $this->cacheDirectory.'/configuration.callcenter.conf.test';
        file_put_contents($cache, 'stale XML');
        $this->switch();
        $job->handle();
        $this->assertFileDoesNotExist($cache);
    }

    /** @dataProvider runtimeFailures */
    public function test_runtime_errors_retry_after_contact_has_already_changed($reply, $rows, bool $connected): void
    {
        $this->update();
        $job = new RefreshCallCenterAgent($this->agentUuid, $this->domain);
        $this->switch($reply, $rows, $connected);
        try {
            $job->handle();
            $this->fail('Expected runtime verification to fail.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('FreeSWITCH', $error->getMessage());
        }
        Mockery::close();
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid, 'agent_contact' => 'user/205@office201.test']);
        $this->switch();
        unserialize(serialize($job))->handle();
    }

    public static function runtimeFailures(): array
    {
        return [['-ERR failed', null, true], [null, null, true], ['+OK', [], true],
            ['+OK', [['name' => 'different-agent', 'type' => 'callback', 'contact' => 'user/205@office201.test']], true],
            ['+OK', null, false]];
    }

    public function test_old_runtime_job_uses_current_state_after_another_extension_rename(): void
    {
        $this->update();
        $job = new RefreshCallCenterAgent($this->agentUuid, $this->domain);
        $this->update($this->event(['extension' => '205'], ['extension' => '209']));
        $this->switch('+OK', [['name' => $this->agentUuid, 'type' => 'callback', 'contact' => '{call_timeout=20}user/209@office201.test']]);
        $job->handle();
        $this->assertDatabaseHas('v_call_center_agents', ['call_center_agent_uuid' => $this->agentUuid, 'agent_contact' => 'user/209@office201.test']);
    }

    public function test_successful_reload_with_a_stale_contact_still_fails_verification(): void
    {
        $this->update();
        $this->switch('+OK', [['name' => $this->agentUuid, 'type' => 'callback', 'contact' => '{call_timeout=20}user/201@office201.test']]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contact does not match');
        (new RefreshCallCenterAgent($this->agentUuid, $this->domain))->handle();
    }

    public function test_deleted_and_foreign_agents_do_not_connect_to_freeswitch(): void
    {
        $this->app->bind(FreeswitchEslService::class, function () { $this->fail('No runtime connection is needed.'); });
        (new RefreshCallCenterAgent($this->agentUuid, $this->foreignDomain))->handle();
        DB::table('v_call_center_agents')->where('call_center_agent_uuid', $this->agentUuid)->delete();
        (new RefreshCallCenterAgent($this->agentUuid, $this->domain))->handle();
        $this->assertDatabaseCount('v_call_center_agents', 0);
    }

    public function test_extension_observer_includes_old_and_new_alias_in_the_event(): void
    {
        Event::fake([ExtensionUpdated::class]);
        $event = $this->event(['number_alias' => '901'], ['extension' => '201', 'number_alias' => '902']);
        $extension = new Extensions();
        $extension->setRawAttributes($event->originalAttributes, true);
        $extension->setRawAttributes($event->extension);
        $extension->syncChanges();
        (new ExtensionObserver())->updated($extension);
        Event::assertDispatched(ExtensionUpdated::class, fn ($sent) => $sent->originalAttributes['number_alias'] === '901'
            && $sent->extension['number_alias'] === '902');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_extension_updates_work_without_optional_modules(): void
    {
        $this->assertFalse(class_exists(\Modules\ContactCenter\Services\Ha\HaSettings::class));
        $listeners = (new \App\Providers\EventServiceProvider($this->app))->listens()[ExtensionUpdated::class];
        $this->assertSame(1, count(array_filter($listeners, fn ($listener) => $listener === UpdateAgentWhenExtensionIsUpdated::class)));
        $this->test_all_matching_contacts_update_without_touching_other_accounts_or_custom_contacts();
        $this->switch();
        (new RefreshCallCenterAgent($this->agentUuid, $this->domain))->handle();
    }
}
