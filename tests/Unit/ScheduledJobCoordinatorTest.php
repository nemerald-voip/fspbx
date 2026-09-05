<?php

namespace Tests\Unit;

use App\Models\ScheduledJobHandoff;
use App\Models\ScheduledJobNode;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\Ha\ScheduledJobPeerAuthenticator;
use App\Services\Ha\ScheduledJobPeerClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ScheduledJobCoordinatorTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.scheduled_job_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'scheduled_job_test');
        config()->set('cache.default', 'array');
        config()->set('app.url', 'https://pbx-a.example.test');
        DB::purge('scheduled_job_test');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->uuid('default_setting_uuid')->primary();
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->integer('default_setting_order')->nullable();
            $table->boolean('default_setting_enabled')->default(false);
            $table->text('default_setting_description')->nullable();
        });
        $migration = require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php');
        $migration->up();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disconnect('scheduled_job_test');
        config()->set('database.default', $this->originalConnection);
        Mockery::close();
        parent::tearDown();
    }

    public function test_standalone_node_runs_without_a_persisted_registry_heartbeat(): void
    {
        $resolver = $this->resolver('1001', []);

        $this->assertTrue($resolver->resolve()['active']);
        $this->assertSame('standalone', $resolver->resolve()['source']);
        $this->assertDatabaseCount('scheduled_job_nodes', 0);
    }

    public function test_subscription_names_do_not_affect_cluster_detection(): void
    {
        $resolver = $this->resolver('1001', ['https://10.0.0.2'], [
            ['name' => 'anything_at_all', 'enabled' => false, 'hosts' => ['10.0.0.2']],
        ]);

        $decision = $resolver->resolve();

        $this->assertFalse($decision['active']);
        $this->assertSame('unconfigured', $decision['source']);
        $this->assertSame('anything_at_all', $decision['subscription_discovery']['subscriptions'][0]['name']);
    }

    public function test_keyword_and_uri_subscription_hosts_are_parsed_without_using_names(): void
    {
        $resolver = $this->resolver('1001', []);
        $method = new ReflectionMethod($resolver, 'hostsFromConninfo');

        $keywordHosts = $method->invoke($resolver, "host='pbx-b.example.test,10.0.0.3' port=5432 dbname=fspbx");
        $uriHosts = $method->invoke($resolver, 'postgresql://replicator@pbx-c.example.test:5432/fspbx');

        $this->assertSame(['pbx-b.example.test', '10.0.0.3'], $keywordHosts);
        $this->assertSame(['pbx-c.example.test'], $uriHosts);
    }

    public function test_duplicate_system_identifier_from_multiple_endpoints_fails_closed_in_discovery(): void
    {
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->andReturnUsing(fn (string $endpoint) => [
            'system_identifier' => '2002', 'hostname' => $endpoint,
        ]);
        $resolver = $this->resolver('1001', ['https://pbx-a.example.test', 'https://pbx-b.example.test'], [], $peer);

        $candidates = collect($resolver->discover());

        $this->assertCount(2, $candidates);
        $this->assertTrue($candidates->every(fn (array $candidate) => $candidate['duplicate_identity']));
    }

    public function test_only_the_approved_system_identifier_owner_is_active(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '7', 'numeric');

        $active = $this->resolver('1001', ['https://pbx-b.example.test'])->resolve();
        $standby = $this->resolver('2002', ['https://pbx-a.example.test'])->resolve();

        $this->assertTrue($active['active']);
        $this->assertSame(7, $active['generation']);
        $this->assertFalse($standby['active']);
        $this->assertSame('standby', $standby['status']);
    }

    public function test_ownership_decision_reads_settings_and_membership_once(): void
    {
        $resolver = $this->configuredPair();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $decision = $resolver->resolve();
            $queries = collect(DB::getQueryLog())->pluck('query');
        } finally {
            DB::disableQueryLog();
        }

        $this->assertTrue($decision['active']);
        $this->assertSame([
            'settings' => 1,
            'membership' => 1,
        ], [
            'settings' => $queries->filter(fn ($sql) => str_contains($sql, 'from "v_default_settings"'))->count(),
            'membership' => $queries->filter(fn ($sql) => str_contains($sql, 'from "scheduled_job_nodes"'))->count(),
        ]);
        $this->assertCount(0, $queries->filter(fn ($sql) => preg_match('/^\s*(insert|update|delete)/i', $sql)));
    }

    public function test_same_resolver_rechecks_owner_generation_draining_and_membership(): void
    {
        $resolver = $this->configuredPair();
        $this->assertTrue($resolver->resolve()['active']);

        DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node_generation')->update(['default_setting_value' => '5']);
        $this->assertSame(5, $resolver->resolve()['generation']);

        DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node')->update(['default_setting_value' => '2002']);
        $this->assertSame('standby', $resolver->resolve()['status']);

        DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node')->update(['default_setting_value' => '1001']);
        $this->assertTrue($resolver->resolve()['active']);
        ScheduledJobHandoff::query()->create([
            'idempotency_key' => (string) Str::uuid(), 'from_node_id' => '1001', 'to_node_id' => '2002',
            'expected_generation' => 5, 'status' => 'draining', 'requested_at' => now(),
        ]);
        $this->assertSame('draining', $resolver->resolve()['status']);

        ScheduledJobNode::query()->where('system_identifier', '1001')->update(['status' => 'retired']);
        $this->assertSame('unrecognized', $resolver->resolve()['source']);
    }

    public function test_a_retired_or_unknown_identifier_fails_closed(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test', 'retired');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '0', 'numeric');

        $decision = $this->resolver('1001', ['https://pbx-b.example.test'])->resolve();

        $this->assertFalse($decision['active']);
        $this->assertFalse($decision['recognized']);
    }

    public function test_a_legacy_hostname_is_only_a_visible_migration_hint_after_approval(): void
    {
        $this->node('1001', 'pbx-a.example.test', 'https://pbx-a.example.test');
        $this->setting('active_node', 'pbx-a.example.test');
        $this->setting('active_node_generation', '0', 'numeric');

        $decision = $this->resolver('1001', ['https://pbx-b.example.test'])->resolve();

        $this->assertFalse($decision['active']);
        $this->assertTrue($decision['legacy_match']);
        $this->assertSame('pbx-a.example.test', DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node')->value('default_setting_value'));
    }

    public function test_draining_rejects_new_execution_claims(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '3', 'numeric');
        ScheduledJobHandoff::query()->create([
            'idempotency_key' => (string) Str::uuid(), 'from_node_id' => '1001', 'to_node_id' => '2002',
            'expected_generation' => 3, 'status' => 'draining', 'requested_at' => now(),
        ]);

        $claim = $this->resolver('1001', ['https://pbx-b.example.test'])->claimExecution('ldap_directory_sync', 'directory-1', 600);

        $this->assertNull($claim);
        $this->assertDatabaseCount('scheduled_job_executions', 0);
    }

    public function test_execution_claim_records_node_and_generation_and_deduplicates_running_work(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '9', 'numeric');
        $resolver = $this->resolver('1001', ['https://pbx-b.example.test']);

        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory-1', 600, fn () => true);
        $duplicate = $resolver->claimExecution('ldap_directory_sync', 'directory-1', 600, fn () => true);

        $this->assertNotNull($execution);
        $this->assertSame('1001', $execution->node_id);
        $this->assertSame(9, $execution->ownership_generation);
        $this->assertNull($duplicate);
    }

    public function test_handoff_is_idempotent_and_waits_for_running_execution_to_finish(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '4', 'numeric');
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->once()->with('https://pbx-b.example.test')->andReturn([
            'system_identifier' => '2002', 'hostname' => 'pbx-b', 'host_fingerprint' => hash('sha256', '2002'),
        ]);
        $resolver = $this->resolver('1001', ['https://pbx-b.example.test'], [], $peer);
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory-1', 600);
        $idempotency = (string) Str::uuid();
        $payload = [
            'target_node_id' => '2002', 'target_endpoint' => 'https://pbx-b.example.test',
            'expected_generation' => 4, 'idempotency_key' => $idempotency,
        ];

        $first = $resolver->prepareHandoff($payload);
        $retry = $resolver->prepareHandoff($payload);

        $this->assertSame('draining', $first['status']);
        $this->assertSame($first['handoff']['id'], $retry['handoff']['id']);
        $this->assertSame('1001', $resolver->configuredNode());
        $resolver->finishExecution($execution);
        $resolver->finalizePendingHandoff();
        $this->assertSame('2002', $resolver->configuredNode());
        $this->assertSame(5, $resolver->generation());
    }

    public function test_wrong_owner_prepare_returns_conflict_without_forwarding(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '2002');
        $this->setting('active_node_generation', '2', 'numeric');
        $resolver = $this->resolver('1001', ['https://pbx-b.example.test']);

        try {
            $resolver->prepareHandoff([
                'target_node_id' => '1001', 'target_endpoint' => 'https://pbx-a.example.test',
                'expected_generation' => 2, 'idempotency_key' => (string) Str::uuid(),
            ]);
            $this->fail('Expected the non-owner to reject the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertDatabaseCount('scheduled_job_handoffs', 0);
    }

    public function test_forced_takeover_records_fenced_endpoint_and_advances_generation(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '6', 'numeric');
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->once()->with('https://pbx-b.example.test')->andReturn([
            'system_identifier' => '2002', 'hostname' => 'pbx-b', 'host_fingerprint' => hash('sha256', '2002'),
        ]);
        $resolver = $this->resolver('2002', ['https://pbx-a.example.test'], [], $peer);

        $handoff = $resolver->forceTakeover('2002', 6, 'https://pbx-a.example.test', '11111111-1111-4111-8111-111111111111');

        $this->assertTrue($handoff->forced);
        $this->assertSame('https://pbx-a.example.test', $handoff->fenced_endpoint);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $handoff->requested_by);
        $this->assertSame('2002', $resolver->configuredNode());
        $this->assertSame(7, $resolver->generation());
    }

    public function test_expired_worker_cannot_write_or_resurrect_its_execution(): void
    {
        $resolver = $this->configuredPair();
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        $this->travel(601)->seconds();
        $resolver->finalizePendingHandoff();
        $resolver->finishExecution($execution);
        $this->assertSame('expired', $execution->fresh()->status);
        try {
            $resolver->withExecution($execution, fn () => $this->setting('should_not_exist', 'bad'));
            $this->fail('Expired execution wrote data.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertDatabaseMissing('v_default_settings', ['default_setting_subcategory' => 'should_not_exist']);
    }

    public function test_batch_crossing_deadline_rolls_back_all_its_writes(): void
    {
        $resolver = $this->configuredPair();
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        try {
            $resolver->withExecution($execution, function () {
                $this->setting('must_rollback', 'bad');
                $this->travel(601)->seconds();
            });
            $this->fail('Deadline did not roll back the write transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertDatabaseMissing('v_default_settings', ['default_setting_subcategory' => 'must_rollback']);
    }

    public function test_draining_worker_may_finish_but_revoked_generation_may_not_write(): void
    {
        $resolver = $this->configuredPair();
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        $payload = $this->payload();
        $resolver->prepareHandoff($payload);
        $this->assertSame('ok', $resolver->withExecution($execution, fn () => 'ok'));
        $this->assertNotNull($this->resolver('2002', [])->resolve()['handoff']);
        $resolver->finishExecution($execution);
        $this->assertSame('2002', $resolver->configuredNode());
        $this->expectExceptionCode(409);
        $resolver->withExecution($execution, fn () => $this->fail('Revoked worker executed.'));
    }

    public function test_completed_retry_returns_original_result_and_rejects_changed_payload(): void
    {
        $resolver = $this->configuredPair();
        $payload = $this->payload();
        $first = $resolver->prepareHandoff($payload);
        $retry = $resolver->prepareHandoff($payload);
        $this->assertSame('completed', $retry['status']);
        $this->assertSame($first, $retry);
        $payload['expected_generation']++;
        $this->expectExceptionCode(409);
        $resolver->prepareHandoff($payload);
    }

    public function test_duplicate_settings_fail_closed_instead_of_picking_a_row(): void
    {
        $resolver = $this->configuredPair();
        $this->setting('active_node', '2002');
        $this->assertFalse($resolver->resolve()['active']);
        $this->assertSame('inconsistent', $resolver->resolve()['source']);
        $this->expectExceptionCode(409);
        $resolver->requestHandoff('2002', 4, null);
    }

    public function test_database_clone_with_different_host_identity_cannot_claim(): void
    {
        $resolver = $this->configuredPair();
        ScheduledJobNode::query()->where('system_identifier', '1001')->update(['host_fingerprint' => hash('sha256', 'different-machine')]);
        $this->assertSame('identity_mismatch', $resolver->resolve()['source']);
        $this->assertNull($resolver->claimExecution('ldap_directory_sync', 'directory', 600));
    }

    public function test_same_host_at_two_endpoints_is_an_alias_not_a_clone(): void
    {
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->andReturn(['system_identifier' => '1001', 'host_fingerprint' => hash('sha256', '1001')]);
        $candidates = $this->resolver('1001', ['https://alias.example.test'], [], $peer)->discover();
        $this->assertCount(2, $candidates);
        $this->assertFalse($candidates[0]['duplicate_identity']);
        $this->assertFalse($candidates[1]['duplicate_identity']);
    }

    public function test_unknown_owner_is_never_bootstrapped_without_fencing(): void
    {
        $resolver = $this->configuredPair();
        DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node')->update(['default_setting_value' => 'removed-host']);
        try {
            $resolver->requestHandoff('2002', 4, null);
            $this->fail('Unknown ownership was bootstrapped.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertDatabaseCount('scheduled_job_handoffs', 0);
    }

    public function test_legacy_normalization_preserves_generation_and_drains_older_claims(): void
    {
        $resolver = $this->configuredPair();
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        DB::table('v_default_settings')->where('default_setting_subcategory', 'active_node')->update(['default_setting_value' => 'pbx-a']);
        $resolver->requestHandoff('1001', 4, null);
        $this->assertSame(4, $resolver->generation());
        $execution->forceFill(['ownership_generation' => 3])->save();
        $this->assertSame('draining', $resolver->prepareHandoff($this->payload())['status']);
    }

    public function test_forced_takeover_supersedes_old_drain_and_preserves_requester(): void
    {
        $resolver = $this->configuredPair();
        $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        $payload = $this->payload() + ['requested_by' => '11111111-1111-4111-8111-111111111111'];
        $first = $resolver->prepareHandoff($payload);
        $peer = $this->peer();
        $target = $this->resolver('2002', [], [], $peer);
        $forced = $target->forceTakeover('2002', 4, 'https://pbx-a.example.test', '22222222-2222-4222-8222-222222222222');
        $old = ScheduledJobHandoff::query()->findOrFail($first['handoff']['id']);
        $this->assertSame('superseded', $old->status);
        $this->assertSame($payload['requested_by'], $old->requested_by);
        $this->assertSame('22222222-2222-4222-8222-222222222222', $forced->forced_by);
        $back = ['target_node_id' => '1001', 'target_endpoint' => 'https://pbx-a.example.test', 'expected_generation' => 5, 'idempotency_key' => (string) Str::uuid()];
        $this->assertSame('completed', $target->prepareHandoff($back)['status']);
    }

    public function test_retired_endpoint_can_be_reused_by_a_replacement_identity(): void
    {
        $this->configuredPair();
        ScheduledJobNode::query()->where('system_identifier', '2002')->update(['status' => 'retired', 'retired_at' => now()]);
        $peer = $this->peer('3003');
        $resolver = $this->resolver('1001', ['https://pbx-b.example.test'], [], $peer);
        $node = $resolver->approveNode('https://pbx-b.example.test', '3003', null);
        $this->assertSame('3003', $node->system_identifier);
        $this->assertSame('retired', ScheduledJobNode::query()->where('system_identifier', '2002')->value('status'));
        $this->assertSame($node->getKey(), collect($resolver->statusContext()['nodes'])->firstWhere('id', '3003')['registry_uuid']);
    }

    public function test_status_and_idle_maintenance_do_not_write_heartbeats(): void
    {
        $resolver = $this->configuredPair();
        DB::enableQueryLog();
        $resolver->statusContext();
        $resolver->finalizePendingHandoff();
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete)/i', $query['query']));
        $this->assertCount(0, $writes);
        DB::disableQueryLog();
    }

    public function test_first_approval_uses_one_elected_writer_and_requires_a_pair(): void
    {
        $peer = $this->peer();
        $other = $this->resolver('2002', ['https://pbx-a.example.test', 'https://pbx-b.example.test'], [], $peer);
        try {
            $other->approveNode('https://pbx-b.example.test', '2002', null);
            $this->fail('Both nodes could register initial membership independently.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $first = $this->resolver('1001', ['https://pbx-b.example.test'], [], $peer);
        $node = $first->approveNode('https://pbx-a.example.test', '1001', null);
        $this->assertSame('1001', $node->registered_on_node_id);
        $this->assertDatabaseCount('scheduled_job_nodes', 1);
    }

    public function test_bootstrap_requires_matching_signed_snapshots_and_only_initial_writer(): void
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $resolver = $this->resolver('1001', ['https://pbx-b.example.test'], [], $peer);
        $snapshot = $resolver->coordinationSnapshot();
        $peer->shouldReceive('identify')->andReturnUsing(function ($endpoint) use ($snapshot) {
            $id = str_contains($endpoint, 'pbx-a') ? '1001' : '2002';
            return ['system_identifier' => $id, 'host_fingerprint' => hash('sha256', $id), 'coordination' => $snapshot];
        });
        $other = $this->resolver('2002', [], [], $peer);
        try {
            $other->requestHandoff('2002', 0, null);
            $this->fail('The other node initialized ownership.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $result = $resolver->requestHandoff('1001', 0, null);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $resolver->generation());
        $this->assertSame('1001', $resolver->configuredNode());
    }

    public function test_stale_queued_ldap_job_does_not_advance_next_due_time(): void
    {
        $this->configuredPair();
        Schema::create('ldap_directories', function (Blueprint $table) {
            $table->uuid('directory_uuid')->primary();
            $table->boolean('enabled');
            $table->timestamp('next_sync_at');
        });
        $id = (string) Str::uuid();
        $due = now()->subMinute()->format('Y-m-d H:i:s');
        DB::table('ldap_directories')->insert(['directory_uuid' => $id, 'enabled' => true, 'next_sync_at' => $due]);
        $service = Mockery::mock(\App\Services\LdapDirectorySyncService::class);
        $service->shouldNotReceive('sync');
        (new \App\Jobs\SyncLdapDirectory($id))->handle($service, $this->resolver('2002', []));
        $this->assertSame($due, DB::table('ldap_directories')->value('next_sync_at'));
        $this->assertDatabaseCount('scheduled_job_executions', 0);
    }

    public function test_failed_target_verification_never_begins_a_handoff(): void
    {
        $this->configuredPair();
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->andThrow(new RuntimeException('Unreachable peer.'));
        $resolver = $this->resolver('1001', [], [], $peer);
        try {
            $resolver->prepareHandoff($this->payload());
            $this->fail('Unverified target accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unreachable peer.', $exception->getMessage());
        }
        $this->assertDatabaseCount('scheduled_job_handoffs', 0);
        $this->assertSame('1001', $resolver->configuredNode());
    }

    public function test_manual_ldap_dry_run_uses_claim_and_records_node_visibility(): void
    {
        $resolver = $this->configuredPair();
        $this->app->instance(ActiveNodeResolver::class, $resolver);
        $directory = $this->ldapFixture();
        $client = Mockery::mock(\App\Services\ActiveDirectoryClient::class);
        $client->shouldReceive('users')->once()->andReturn([['samaccountname' => ['alice']]]);
        $client->shouldReceive('groups')->once()->andReturn([]);
        $this->app->bind(\App\Services\ActiveDirectoryClient::class, fn () => $client);

        $this->artisan('ldap:sync', ['directory' => $directory->getKey(), '--dry-run' => true])->assertExitCode(0);
        $run = \App\Models\LdapSyncRun::query()->with('execution')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('completed', $run->execution->status);
        $this->assertSame('1001', $run->node_id);
        $this->assertSame(4, $run->ownership_generation);
        $this->assertSame('pbx-a.example.test', $run->node_name);
        $this->assertNull($directory->fresh()->last_sync_at);
    }

    public function test_ldap_failure_after_revocation_cannot_update_directory_or_retry_state(): void
    {
        $resolver = $this->configuredPair();
        $this->app->instance(ActiveNodeResolver::class, $resolver);
        $directory = $this->ldapFixture();
        $dueAfterClaim = null;
        $client = Mockery::mock(\App\Services\ActiveDirectoryClient::class);
        $client->shouldReceive('users')->once()->andReturnUsing(function () use ($directory, &$dueAfterClaim) {
            $dueAfterClaim = $directory->fresh()->next_sync_at;
            $this->travel(601)->seconds();
            throw new RuntimeException('Read failed after execution expiry.');
        });
        $this->app->bind(\App\Services\ActiveDirectoryClient::class, fn () => $client);
        try {
            (new \App\Jobs\SyncLdapDirectory($directory->getKey()))->handle(new \App\Services\LdapDirectorySyncService(), $resolver);
            $this->fail('Expected the directory read to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Read failed after execution expiry.', $exception->getMessage());
        }
        $this->assertNotNull($dueAfterClaim);
        $this->assertEquals($dueAfterClaim, $directory->fresh()->next_sync_at);
        $this->assertNull($directory->fresh()->last_sync_status);
        $this->assertNull(\App\Models\LdapSyncRun::query()->first()->finished_at);
        $resolver->finalizePendingHandoff();
        $this->assertSame('expired', \App\Models\ScheduledJobExecution::query()->first()->status);
    }

    public function test_lost_api_response_is_recovered_without_pretending_replication_arrived(): void
    {
        $this->configuredPair();
        $peer = $this->peer();
        $key = (string) Str::uuid();
        $peer->shouldReceive('prepareHandoff')->once()->andThrow(new RuntimeException('Response lost.'));
        $peer->shouldReceive('handoffStatus')->once()->with('https://pbx-a.example.test', $key)->andReturn([
            'status' => 'completed', 'handoff' => ['to_node_id' => '2002', 'expected_generation' => 4],
        ]);
        $ui = $this->resolver('2002', [], [], $peer);
        $this->assertSame('completed', $ui->requestHandoff('2002', 4, null, $key)['status']);
        $this->assertSame('1001', $ui->configuredNode());
        $this->assertFalse($ui->isActive());
    }

    public function test_unreachable_owner_stops_normal_transfer_without_local_ownership_write(): void
    {
        $this->configuredPair();
        $peer = $this->peer();
        $peer->shouldReceive('prepareHandoff')->andThrow(new RuntimeException('Owner unreachable.'));
        $peer->shouldReceive('handoffStatus')->andThrow(new RuntimeException('Owner unreachable.'));
        $ui = $this->resolver('2002', [], [], $peer);
        try {
            $ui->requestHandoff('2002', 4, null);
            $this->fail('Normal transfer continued without its owner.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Owner unreachable.', $exception->getMessage());
        }
        $this->assertSame('1001', $ui->configuredNode());
        $this->assertDatabaseCount('scheduled_job_handoffs', 0);
    }

    private function ldapFixture(): \App\Models\LdapDirectory
    {
        (require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php'))->up();
        (require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php'))->up();
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_enabled');
        });
        $domain = (string) Str::uuid();
        DB::table('v_domains')->insert(['domain_uuid' => $domain, 'domain_enabled' => 'true']);

        return \App\Models\LdapDirectory::query()->create([
            'domain_uuid' => $domain, 'name' => 'Lab LDAP', 'hosts' => 'unused.example.test',
            'bind_username' => 'reader', 'ad_domain' => 'example.test', 'base_dn' => 'dc=example,dc=test',
            'enabled' => true, 'next_sync_at' => now()->subMinute(),
        ])->fresh();
    }

    private function configuredPair(): ActiveNodeResolver
    {
        $this->node('1001', 'pbx-a', 'https://pbx-a.example.test');
        $this->node('2002', 'pbx-b', 'https://pbx-b.example.test');
        $this->setting('active_node', '1001');
        $this->setting('active_node_generation', '4', 'numeric');

        return $this->resolver('1001', ['https://pbx-b.example.test'], [], $this->peer());
    }

    private function peer(string $otherId = '2002'): ScheduledJobPeerClient
    {
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->andReturnUsing(function ($endpoint) use ($otherId) {
            $id = str_contains($endpoint, 'pbx-a') ? '1001' : $otherId;
            return ['system_identifier' => $id, 'hostname' => $id, 'host_fingerprint' => hash('sha256', $id)];
        });

        return $peer;
    }

    private function payload(): array
    {
        return ['target_node_id' => '2002', 'target_endpoint' => 'https://pbx-b.example.test', 'expected_generation' => 4, 'idempotency_key' => (string) Str::uuid()];
    }

    private function resolver(
        string $nodeId,
        array $endpoints,
        array $subscriptions = [],
        ?ScheduledJobPeerClient $peer = null
    ): ActiveNodeResolver
    {
        $peer ??= Mockery::mock(ScheduledJobPeerClient::class);
        $authenticator = Mockery::mock(ScheduledJobPeerAuthenticator::class);
        $authenticator->shouldReceive('secretConfigured')->andReturn(true)->byDefault();

        return new class($peer, $authenticator, $nodeId, $endpoints, $subscriptions) extends ActiveNodeResolver
        {
            public function __construct(
                ScheduledJobPeerClient $peer,
                ScheduledJobPeerAuthenticator $authenticator,
                private readonly string $fixedNodeId,
                private readonly array $fixedEndpoints,
                private readonly array $fixedSubscriptions
            ) {
                parent::__construct($peer, $authenticator);
            }

            public function localNodeId(): ?string
            {
                return $this->fixedNodeId;
            }

            public function hostname(): string
            {
                return $this->fixedNodeId === '1001' ? 'pbx-a.example.test' : 'pbx-b.example.test';
            }

            public function hostFingerprint(): ?string
            {
                return hash('sha256', $this->fixedNodeId);
            }

            protected function subscriptionDiscovery(): array
            {
                return ['readable' => true, 'endpoints' => $this->fixedEndpoints, 'subscriptions' => $this->fixedSubscriptions];
            }
        };
    }

    private function node(string $id, string $hostname, string $endpoint, string $status = 'approved'): void
    {
        ScheduledJobNode::query()->create([
            'system_identifier' => $id, 'hostname' => $hostname, 'endpoint' => $endpoint,
            'status' => $status, 'approved_at' => now(),
            'host_fingerprint' => hash('sha256', $id), 'registered_on_node_id' => '1001',
        ]);
    }

    private function setting(string $subcategory, string $value, string $type = 'text'): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'scheduled_jobs',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => $type,
            'default_setting_value' => $value,
            'default_setting_enabled' => true,
        ]);
    }
}
