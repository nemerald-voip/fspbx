<?php

namespace Tests\Unit;

use App\Http\Controllers\LetsEncryptController;
use App\Http\Requests\SaveLetsEncryptSettingsRequest;
use App\Services\LetsEncryptService;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LetsEncryptControllerTest extends TestCase
{
    public function test_issue_persists_the_complete_form_before_certificate_generation(): void
    {
        Log::spy();

        $settings = [
            'domain' => 'app.example.com, fs1.example.com, fs2.example.com',
            'account_email' => 'admin@example.com',
            'webroot' => '/var/www/fspbx/public',
            'staging' => true,
            'auto_renew' => true,
            'push_secret' => 'shared-secret-value',
        ];

        $request = Mockery::mock(SaveLetsEncryptSettingsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($settings);

        $service = Mockery::mock(LetsEncryptService::class);
        $service->shouldReceive('parseDomains')->once()->with($settings['domain'])->andReturn([
            'app.example.com',
            'fs1.example.com',
            'fs2.example.com',
        ])->ordered();
        $service->shouldReceive('saveSetting')->once()->with(
            'domain',
            'app.example.com fs1.example.com fs2.example.com'
        )->ordered();
        $service->shouldReceive('saveSetting')->once()->with('account_email', 'admin@example.com')->ordered();
        $service->shouldReceive('saveSetting')->once()->with('webroot', '/var/www/fspbx/public')->ordered();
        $service->shouldReceive('saveSetting')->once()->with('staging', 'true')->ordered();
        $service->shouldReceive('saveSetting')->once()->with('auto_renew', 'true')->ordered();
        $service->shouldReceive('saveSetting')->once()->with('push_secret', 'shared-secret-value')->ordered();
        $service->shouldReceive('saveScheduledJobToggle')->once()->with(true)->ordered();
        $service->shouldReceive('issue')
            ->once()
            ->with($settings['domain'], 'admin@example.com', true)
            ->andThrow(new RuntimeException('ACME validation failed.'))
            ->ordered();
        $service->shouldReceive('recordError')->once()->with(Mockery::type(RuntimeException::class));

        $response = (new LetsEncryptController($service))->issue($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            'ACME validation failed.',
            $response->getData(true)['errors']['certificate'][0]
        );
    }

    public function test_rotate_generates_and_immediately_saves_the_peer_secret(): void
    {
        $savedSecret = null;
        $service = Mockery::mock(LetsEncryptService::class);
        $service->shouldReceive('saveSetting')
            ->once()
            ->with('push_secret', Mockery::on(function ($secret) use (&$savedSecret) {
                $savedSecret = $secret;

                return is_string($secret) && strlen($secret) === 40;
            }));

        $response = (new TestableLetsEncryptController($service))->generateSecret();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($savedSecret, $payload['secret']);
        $this->assertSame('Peer push secret rotated and saved.', $payload['messages']['success'][0]);
    }
}

class TestableLetsEncryptController extends LetsEncryptController
{
    protected function canManage(): bool
    {
        return true;
    }
}
