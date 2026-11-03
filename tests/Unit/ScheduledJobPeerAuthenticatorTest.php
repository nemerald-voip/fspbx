<?php

namespace Tests\Unit;

use App\Services\Ha\ScheduledJobPeerAuthenticator;
use App\Services\Ha\ScheduledJobPeerClient;
use App\Services\Ha\ActiveNodeResolver;
use App\Http\Controllers\ScheduledJobPeerController;
use App\Models\ScheduledJobNode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ScheduledJobPeerAuthenticatorTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config()->set('database.connections.peer_auth_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'peer_auth_test');
        config()->set('cache.default', 'array');
        DB::purge('peer_auth_test');
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->uuid('default_setting_uuid')->primary();
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_name');
            $table->text('default_setting_value')->nullable();
            $table->boolean('default_setting_enabled')->default(true);
        });
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'scheduled_jobs',
            'default_setting_subcategory' => 'coordination_secret',
            'default_setting_name' => 'text',
            'default_setting_value' => str_repeat('s', 64),
            'default_setting_enabled' => true,
        ]);
        Cache::flush();
        Carbon::setTestNow('2026-09-04 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('peer_auth_test');
        config()->set('database.default', $this->originalConnection);
        parent::tearDown();
    }

    public function test_valid_request_is_accepted_once_and_replay_is_rejected(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $payload = ['challenge' => str_repeat('a', 32), 'endpoint_identity' => 'https://pbx-b.example.test'];
        $idempotency = (string) Str::uuid();
        $headers = $auth->requestHeaders('POST', 'api/ha/node/identify', $payload, $idempotency);
        $request = Request::create('/api/ha/node/identify', 'POST', $payload, [], [], $this->serverHeaders($headers));

        $this->assertTrue($auth->verifyRequest($request));
        $this->assertTrue(Cache::has('scheduled-job-peer-nonce:'.$headers[ScheduledJobPeerAuthenticator::NONCE_HEADER]));
        $this->assertFalse($auth->verifyRequest($request));
    }

    public function test_body_tampering_and_expired_timestamps_are_rejected(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $payload = ['target_node_id' => '2002'];
        $headers = $auth->requestHeaders('POST', 'api/ha/scheduled-jobs/handoffs', $payload, (string) Str::uuid());

        $tampered = Request::create('/api/ha/scheduled-jobs/handoffs', 'POST', ['target_node_id' => '3003'], [], [], $this->serverHeaders($headers));
        $this->assertFalse($auth->verifyRequest($tampered));

        Carbon::setTestNow('2026-09-04 12:02:00');
        $expired = Request::create('/api/ha/scheduled-jobs/handoffs', 'POST', $payload, [], [], $this->serverHeaders($headers));
        $this->assertFalse($auth->verifyRequest($expired));
    }

    public function test_request_signed_with_the_old_secret_is_rejected_after_rotation(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $payload = ['challenge' => str_repeat('a', 32), 'endpoint_identity' => 'https://pbx-b.example.test'];
        $headers = $auth->requestHeaders('POST', 'api/ha/node/identify', $payload, (string) Str::uuid());
        DB::table('v_default_settings')->where('default_setting_subcategory', 'coordination_secret')
            ->update(['default_setting_value' => str_repeat('n', 64)]);
        $request = Request::create('/api/ha/node/identify', 'POST', $payload, [], [], $this->serverHeaders($headers));

        $this->assertFalse($auth->verifyRequest($request));
    }

    public function test_signed_response_is_bound_to_nonce_idempotency_path_and_body(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $payload = ['system_identifier' => '2002'];
        $nonce = str_repeat('a', 32);
        $idempotency = (string) Str::uuid();
        $headers = $auth->responseHeaders('api/ha/node/identify', $payload, $nonce, $idempotency);
        $arrayHeaders = collect($headers)->mapWithKeys(fn ($value, $name) => [strtolower($name) => [$value]])->all();

        $this->assertTrue($auth->verifyResponse('api/ha/node/identify', $payload, $nonce, $idempotency, $arrayHeaders));
        $this->assertFalse($auth->verifyResponse('api/ha/node/identify', ['system_identifier' => '3003'], $nonce, $idempotency, $arrayHeaders));
        $this->assertFalse($auth->verifyResponse('api/ha/node/identify', $payload, $nonce, $idempotency, $arrayHeaders, 409));
    }

    private function serverHeaders(array $headers): array
    {
        return collect($headers)->mapWithKeys(fn ($value, $name) => [
            'HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value,
        ])->all();
    }

    public function test_forwarded_approval_uses_signed_requests_and_responses_and_preserves_the_actor(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $resolver = Mockery::mock(ActiveNodeResolver::class);
        $resolver->shouldReceive('localNodeId')->once()->andReturn('1001');
        $actor = (string) Str::uuid();
        $node = new ScheduledJobNode([
            'system_identifier' => '2002', 'endpoint' => 'https://pbx-b.example.test',
            'status' => 'approved', 'host_fingerprint' => hash('sha256', '2002'),
        ]);
        $resolver->shouldReceive('approveNode')->once()->with('https://pbx-b.example.test', '2002', $actor, hash('sha256', '2002'))->andReturn($node);
        $controller = new ScheduledJobPeerController($resolver, $auth);
        $received = null;
        Http::fake(function ($outgoing) use ($controller, &$received) {
            $received = Request::create($outgoing->url(), 'POST', $outgoing->data(), [], [],
                $this->serverHeaders(collect($outgoing->headers())->map(fn ($values) => $values[0])->all()));
            $response = $controller->approveNode($received);

            return Http::response($response->getData(true), $response->getStatusCode(), $response->headers->all());
        });

        $result = (new ScheduledJobPeerClient($auth))->approveNode('https://pbx-a.example.test', [
            'writer_node_id' => '1001', 'target_node_id' => '2002',
            'target_endpoint' => 'https://pbx-b.example.test',
            'target_host_fingerprint' => hash('sha256', '2002'), 'requested_by' => $actor,
        ]);

        $this->assertSame($node->toArray(), $result['node']);
        $this->assertSame(403, $controller->approveNode($received)->getStatusCode(), 'A captured request cannot be replayed.');
    }

    public function test_approval_endpoint_requires_https_and_a_valid_peer_signature(): void
    {
        $resolver = Mockery::mock(ActiveNodeResolver::class);
        $resolver->shouldNotReceive('approveNode');
        $controller = new ScheduledJobPeerController($resolver, new ScheduledJobPeerAuthenticator());
        $path = '/api/ha/scheduled-jobs/nodes/approve';
        $this->assertSame(426, $controller->approveNode(Request::create('http://pbx-a.example.test'.$path, 'POST'))->getStatusCode());
        $this->assertSame(403, $controller->approveNode(Request::create('https://pbx-a.example.test'.$path, 'POST'))->getStatusCode());
    }

    public function test_forwarded_removal_is_signed_and_preserves_the_target(): void
    {
        (require base_path('database/migrations/2026_09_04_000001_create_scheduled_job_coordination_tables.php'))->up();
        $node = ScheduledJobNode::create([
            'system_identifier' => '3003', 'endpoint' => 'https://retired.example.test',
            'hostname' => 'retired', 'registered_on_node_id' => '1001',
            'status' => 'retired', 'host_fingerprint' => hash('sha256', '3003'),
        ]);
        $auth = new ScheduledJobPeerAuthenticator();
        $resolver = Mockery::mock(ActiveNodeResolver::class);
        $resolver->shouldReceive('localNodeId')->once()->andReturn('1001');
        $resolver->shouldReceive('removeNode')->once()->with(Mockery::on(
            fn ($target) => $target->getKey() === $node->getKey() && $target->system_identifier === '3003'
        ));
        $controller = new ScheduledJobPeerController($resolver, $auth);
        $received = null;
        Http::fake(function ($outgoing) use ($controller, &$received) {
            $received = Request::create($outgoing->url(), 'POST', $outgoing->data(), [], [],
                $this->serverHeaders(collect($outgoing->headers())->map(fn ($values) => $values[0])->all()));
            $response = $controller->removeNode($received);

            return Http::response($response->getData(true), $response->getStatusCode(), $response->headers->all());
        });

        $result = (new ScheduledJobPeerClient($auth))->removeNode('https://pbx-a.example.test', [
            'writer_node_id' => '1001', 'target_node_id' => '3003', 'node_uuid' => $node->getKey(),
        ]);
        $this->assertSame(['node_uuid' => $node->getKey(), 'target_node_id' => '3003'], $result);
        $this->assertSame(403, $controller->removeNode($received)->getStatusCode());
        $this->assertSame(403, $controller->removeNode(Request::create('https://pbx-a.example.test/api/ha/scheduled-jobs/nodes/remove', 'POST'))->getStatusCode());
        $this->assertSame(426, $controller->removeNode(Request::create('http://pbx-a.example.test/api/ha/scheduled-jobs/nodes/remove', 'POST'))->getStatusCode());
    }

    public function test_forwarded_approval_rejects_the_wrong_receiver_with_a_signed_error(): void
    {
        $auth = new ScheduledJobPeerAuthenticator();
        $resolver = Mockery::mock(ActiveNodeResolver::class);
        $resolver->shouldReceive('localNodeId')->once()->andReturn('3003');
        $resolver->shouldNotReceive('approveNode');
        $controller = new ScheduledJobPeerController($resolver, $auth);
        Http::fake(function ($outgoing) use ($controller) {
            $request = Request::create($outgoing->url(), 'POST', $outgoing->data(), [], [],
                $this->serverHeaders(collect($outgoing->headers())->map(fn ($values) => $values[0])->all()));
            $response = $controller->approveNode($request);

            return Http::response($response->getData(true), $response->getStatusCode(), $response->headers->all());
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('The approval request reached a different server.');
        (new ScheduledJobPeerClient($auth))->approveNode('https://pbx-a.example.test', [
            'writer_node_id' => '1001', 'target_node_id' => '2002',
            'target_endpoint' => 'https://pbx-b.example.test',
            'target_host_fingerprint' => hash('sha256', '2002'),
        ]);
    }
}
