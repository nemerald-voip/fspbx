<?php

namespace Tests\Unit;

use App\Services\Ha\ScheduledJobPeerAuthenticator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
}
