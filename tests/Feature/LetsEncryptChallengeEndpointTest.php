<?php

namespace Tests\Feature;

use App\Services\LetsEncryptService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Mockery;
use Tests\TestCase;

class LetsEncryptChallengeEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'logging.default' => 'stderr',
            'session.driver' => 'array',
        ]);
        app('log')->setDefaultDriver('stderr');
        $this->app->instance(RateLimiter::class, new RateLimiter(new Repository(new ArrayStore())));
    }

    public function test_peer_can_present_an_authenticated_challenge_token(): void
    {
        $service = Mockery::mock(LetsEncryptService::class);
        $service->shouldReceive('verifyPushSecret')
            ->once()
            ->with('shared-secret-value')
            ->andReturnTrue();
        $service->shouldReceive('storeChallengeToken')
            ->once()
            ->with('peer_token', 'peer-proof');
        $this->app->instance(LetsEncryptService::class, $service);

        $response = $this->withHeader('X-FsPbx-Cert-Secret', 'shared-secret-value')
            ->postJson('/api/letsencrypt/challenge', [
                'action' => 'present',
                'token' => 'peer_token',
                'value' => 'peer-proof',
            ]);

        $response->assertOk()
            ->assertJsonPath('messages.success.0', 'Challenge token stored.');
    }

    public function test_peer_challenge_rejects_an_invalid_secret(): void
    {
        $service = Mockery::mock(LetsEncryptService::class);
        $service->shouldReceive('verifyPushSecret')
            ->once()
            ->with('wrong-secret')
            ->andReturnFalse();
        $service->shouldNotReceive('storeChallengeToken');
        $this->app->instance(LetsEncryptService::class, $service);

        $response = $this->withHeader('X-FsPbx-Cert-Secret', 'wrong-secret')
            ->postJson('/api/letsencrypt/challenge', [
                'action' => 'present',
                'token' => 'peer_token',
                'value' => 'peer-proof',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('errors.auth.0', 'Invalid or missing push secret.');
    }
}
