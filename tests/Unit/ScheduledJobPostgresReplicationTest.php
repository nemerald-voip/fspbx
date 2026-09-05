<?php

namespace Tests\Unit;

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
use PDO;
use RuntimeException;
use Tests\TestCase;

/** Opt-in only; never uses the application's database or Redis connections. */
class ScheduledJobPostgresReplicationTest extends TestCase
{
    public function test_real_replication_delay_and_local_write_fencing(): void
    {
        $lab = getenv('FSPBX_COORDINATION_LAB_DIR');
        if (! $lab || ! str_starts_with(realpath($lab) ?: '', '/tmp/fspbx-coordination-lab.')) {
            $this->markTestSkipped('Requires two disposable PostgreSQL 17+ clusters and two isolated Redis instances.');
        }
        $original = config('database.default');
        $ids = [];
        foreach (['a' => 16541, 'b' => 16542] as $node => $port) {
            config()->set('database.connections.lab_'.$node, [
                'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => $port,
                'database' => 'postgres', 'username' => 'postgres', 'password' => '', 'charset' => 'utf8',
                'prefix' => '', 'schema' => 'public', 'sslmode' => 'disable',
            ]);
            config()->set('database.redis.lab_'.$node, ['host' => '127.0.0.1', 'port' => $node === 'a' ? 16381 : 16382, 'database' => 0]);
            config()->set('cache.stores.lab_'.$node, ['driver' => 'redis', 'connection' => 'lab_'.$node, 'lock_connection' => 'lab_'.$node]);
            DB::setDefaultConnection('lab_'.$node);
            $this->assertSame($lab.'/'.$node, DB::selectOne('show data_directory')->data_directory, 'Refusing a non-lab server.');
            $this->assertFalse(Schema::hasTable('v_default_settings'), 'Lab must start empty.');
            Schema::create('v_default_settings', function (Blueprint $table) {
                $table->uuid('default_setting_uuid')->primary();
                $table->string('default_setting_category');
                $table->string('default_setting_subcategory');
                $table->string('default_setting_name');
                $table->text('default_setting_value')->nullable();
                $table->boolean('default_setting_enabled');
                $table->text('default_setting_description')->nullable();
            });
            (require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php'))->up();
            Schema::create('coordination_test_effects', function (Blueprint $table) {
                $table->uuid('effect_uuid')->primary();
                $table->string('value');
            });
            DB::statement('create publication coordination_lab for all tables');
            $ids[$node] = DB::selectOne('select system_identifier::text as id from pg_control_system()')->id;
        }
        $this->assertNotSame($ids['a'], $ids['b']);
        // RedisManager captures configuration at construction; discard the
        // boot-time manager before connecting to these test-only stores.
        $this->app->forgetInstance('redis');
        \Illuminate\Support\Facades\Redis::clearResolvedInstance('redis');
        DB::connection('lab_a')->statement("create subscription arbitrary_disabled_history connection 'host=127.0.0.1 port=16542 user=postgres dbname=postgres' publication coordination_lab with (copy_data=false, origin=none)");
        DB::connection('lab_b')->statement("create subscription manually_added_replacement connection 'host=127.0.0.1 port=16541 user=postgres dbname=postgres' publication coordination_lab with (copy_data=false, origin=none)");

        $switch = function (string $node) {
            DB::setDefaultConnection('lab_'.$node);
            config()->set('cache.default', 'lab_'.$node);
        };
        $peer = Mockery::mock(ScheduledJobPeerClient::class);
        $peer->shouldReceive('identify')->andReturnUsing(function ($endpoint) use ($ids) {
            $node = str_contains($endpoint, 'node-a') ? 'a' : 'b';
            $resolver = app(ActiveNodeResolver::class);
            $current = DB::getDefaultConnection();
            try {
                DB::setDefaultConnection('lab_'.$node);
                return ['system_identifier' => $ids[$node], 'host_fingerprint' => $resolver->hostFingerprint(), 'coordination' => $resolver->coordinationSnapshot()];
            } finally {
                DB::setDefaultConnection($current);
            }
        });
        $auth = app(ScheduledJobPeerAuthenticator::class);
        $resolver = new ActiveNodeResolver($peer, $auth);
        $switch('a');
        $fingerprint = $resolver->hostFingerprint();
        foreach ($ids as $node => $id) {
            ScheduledJobNode::query()->create(['system_identifier' => $id, 'host_fingerprint' => $fingerprint,
                'registered_on_node_id' => $ids['a'], 'hostname' => 'node-'.$node, 'endpoint' => 'https://node-'.$node,
                'status' => 'approved', 'approved_at' => now()]);
        }
        foreach (['active_node' => '', 'active_node_generation' => '0', 'coordination_secret' => str_repeat('lab-only-secret-', 4)] as $key => $value) {
            DB::table('v_default_settings')->insert(['default_setting_uuid' => (string) Str::uuid(), 'default_setting_category' => 'scheduled_jobs',
                'default_setting_subcategory' => $key, 'default_setting_name' => 'text', 'default_setting_value' => $value, 'default_setting_enabled' => true]);
        }
        $this->await(fn () => DB::connection('lab_b')->table('v_default_settings')->count() === 3);
        $this->assertSame($ids['a'], $resolver->localNodeId());
        Cache::put('lab-isolation', 'a', 60);
        $switch('b');
        $this->assertSame($ids['b'], $resolver->localNodeId());
        $this->assertNull(Cache::get('lab-isolation'));
        $this->assertFalse($resolver->isActive());
        try {
            $resolver->requestHandoff($ids['a'], 0, null);
            $this->fail('The replica initialized ownership independently.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $switch('a');
        $this->assertSame('completed', $resolver->requestHandoff($ids['a'], 0, null)['status']);
        $this->await(fn () => DB::connection('lab_b')->table('v_default_settings')->where('default_setting_subcategory', 'active_node')->value('default_setting_value') === $ids['a']);
        $execution = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        $this->assertNotNull($execution);
        $this->assertNull($resolver->claimExecution('ldap_directory_sync', 'directory', 600));

        // A second PostgreSQL session cannot cross the same commit gate.
        $other = new PDO('pgsql:host=127.0.0.1;port=16541;dbname=postgres', 'postgres');
        $other->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $resolver->withExecution($execution, function () use ($other) {
            $other->beginTransaction();
            $other->exec("set local lock_timeout='150ms'");
            try {
                $other->query('select pg_advisory_xact_lock(1179861584, 1)');
                $this->fail('Concurrent ownership writer crossed the execution commit gate.');
            } catch (\PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
            } finally {
                $other->rollBack();
            }
            DB::table('coordination_test_effects')->insert(['effect_uuid' => (string) Str::uuid(), 'value' => 'authorized']);
        });
        $payload = ['target_node_id' => $ids['b'], 'target_endpoint' => 'https://node-b', 'expected_generation' => 1, 'idempotency_key' => (string) Str::uuid()];
        $this->assertSame('draining', $resolver->prepareHandoff($payload)['status']);
        $this->await(fn () => DB::connection('lab_b')->table('scheduled_job_handoffs')->where('status', 'draining')->exists());
        DB::connection('lab_b')->statement('alter subscription manually_added_replacement disable');
        $this->await(fn () => DB::connection('lab_b')->selectOne("select count(*)::int as n from pg_stat_subscription where subname='manually_added_replacement' and pid is not null")->n === 0);
        $resolver->finishExecution($execution);
        $this->assertFalse($resolver->isActive());
        $this->assertSame('completed', $resolver->prepareHandoff($payload)['status'], 'Lost responses must be recoverable on old owner.');
        $switch('b');
        $this->assertFalse($resolver->isActive(), 'Standby must wait for ownership replication.');
        $this->assertNull($resolver->claimExecution('ldap_directory_sync', 'directory', 600));
        DB::statement('alter subscription manually_added_replacement enable');
        $this->await(fn () => $resolver->configuredNode() === $ids['b']);
        $this->assertTrue($resolver->isActive());
        $next = $resolver->claimExecution('ldap_directory_sync', 'directory', 600);
        $this->assertNotNull($next);
        $switch('a');
        try {
            $resolver->withExecution($execution, fn () => DB::table('coordination_test_effects')->insert(['effect_uuid' => (string) Str::uuid(), 'value' => 'stale']));
            $this->fail('Resumed old worker committed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertSame(['authorized'], DB::table('coordination_test_effects')->pluck('value')->all());
        $this->await(fn () => DB::connection('lab_a')->table('scheduled_job_executions')->where('node_id', $ids['b'])->exists());

        // Suspend a real PHP worker after it has loaded its claim, transfer
        // ownership after expiry, then resume that same process.
        $workerCode = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('database.connections.paused_lab', json_decode($argv[1], true));
Illuminate\Support\Facades\DB::setDefaultConnection('paused_lab');
config()->set('cache.default', 'array');
$execution = App\Models\ScheduledJobExecution::query()->findOrFail($argv[2]);
fwrite(STDOUT, "READY\n");
fgetc(STDIN);
try {
    app(App\Services\Ha\ActiveNodeResolver::class)->withExecution($execution, function () {
        Illuminate\Support\Facades\DB::table('coordination_test_effects')->insert([
            'effect_uuid' => (string) Illuminate\Support\Str::uuid(), 'value' => 'paused-worker-write',
        ]);
    });
    exit(2);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, "REVOKED\n");
    exit($exception->getCode() === 409 ? 0 : 3);
}
PHP;
        $input = new \Symfony\Component\Process\InputStream();
        $worker = new \Symfony\Component\Process\Process([PHP_BINARY, '-r', $workerCode, json_encode(config('database.connections.lab_b')), $next->getKey()], base_path());
        $worker->setInput($input)->setTimeout(30)->start();
        try {
            $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
            $worker->signal(SIGSTOP);
            $switch('b');
            DB::table('scheduled_job_executions')->where('scheduled_job_execution_uuid', $next->getKey())->update(['expires_at' => now()->subSecond()]);
            $back = ['target_node_id' => $ids['a'], 'target_endpoint' => 'https://node-a', 'expected_generation' => 2, 'idempotency_key' => (string) Str::uuid()];
            $this->assertSame('completed', $resolver->prepareHandoff($back)['status']);
            $switch('a');
            $this->await(fn () => $resolver->generation() === 3);
            $this->assertTrue($resolver->isActive());
            $worker->signal(SIGCONT);
            $input->write("resume\n");
            $input->close();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
            $this->assertStringContainsString('REVOKED', $worker->getOutput());
            $this->assertSame('expired', DB::connection('lab_b')->table('scheduled_job_executions')->where('scheduled_job_execution_uuid', $next->getKey())->value('status'));
            $this->assertFalse(DB::connection('lab_b')->table('coordination_test_effects')->where('value', 'paused-worker-write')->exists());
        } finally {
            if ($worker->isRunning()) {
                $worker->signal(SIGCONT);
                $worker->stop();
            }
        }
        DB::setDefaultConnection($original);
    }

    private function await(callable $condition): void
    {
        $deadline = microtime(true) + 15;
        do {
            if ($condition()) {
                return;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        $this->fail('Disposable logical replication did not reach the expected state in 15 seconds.');
    }
}
