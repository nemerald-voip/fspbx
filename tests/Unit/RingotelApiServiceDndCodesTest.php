<?php

namespace Tests\Unit;

use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class RingotelApiServiceDndCodesTest extends TestCase
{
    private RingotelApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ringotel.url' => 'https://ringotel.test']);

        // Avoid the DefaultSettings lookup; Http::ringotel() resolves the service from the container.
        $this->service = Mockery::mock(RingotelApiService::class)->makePartial();
        $this->service->shouldReceive('getRingotelApiToken')->andReturn('test-token');
        $this->app->instance(RingotelApiService::class, $this->service);

        Http::fake([
            'https://ringotel.test/*' => Http::response(['result' => ['id' => 'branch-1']]),
        ]);
    }

    public function test_create_connection_sends_activate_code_as_dnd_on(): void
    {
        $this->service->createConnection($this->connectionParams());

        Http::assertSent(fn ($request) => $request['method'] === 'createBranch'
            && $request['params']['provision']['dnd'] === ['on' => '*78', 'off' => '*79']);
    }

    public function test_update_connection_sends_activate_code_as_dnd_on(): void
    {
        $this->service->updateConnection($this->connectionParams() + ['conn_id' => 'branch-1']);

        Http::assertSent(fn ($request) => $request['method'] === 'updateBranch'
            && $request['params']['provision']['dnd'] === ['on' => '*78', 'off' => '*79']);
    }

    public function test_saving_an_unchanged_connection_keeps_its_dnd_codes(): void
    {
        // UpdateRingotelConnectionForm.vue loads the form from getBranches: dnd_on_code <- provision.dnd.on.
        $stored = ['on' => '*78', 'off' => '*79'];

        $this->service->updateConnection([
            'dnd_on_code' => $stored['on'],
            'dnd_off_code' => $stored['off'],
        ] + $this->connectionParams() + ['conn_id' => 'branch-1']);

        Http::assertSent(fn ($request) => $request['params']['provision']['dnd'] === $stored);
    }

    public function test_call_forwarding_codes_are_unchanged(): void
    {
        $this->service->createConnection($this->connectionParams());

        Http::assertSent(fn ($request) => $request['params']['provision']['forwarding']['cfon'] === '*72'
            && $request['params']['provision']['forwarding']['cfoff'] === '*73');
    }

    private function connectionParams(): array
    {
        return [
            'org_id' => 'org-1',
            'connection_name' => 'Primary SIP Profile',
            'protocol' => 'sip',
            'domain' => 'example.test',
            'port' => '5060',
            'proxy' => null,
            'dont_verify_server_certificate' => false,
            'disable_srtp' => false,
            'multitenant' => false,
            'codecs' => [['name' => 'G.711 Ulaw', 'enabled' => true, 'frame' => 20]],
            'registration_ttl' => 3600,
            'max_registrations' => 3,
            'app_opus_codec' => true,
            'one_push' => false,
            'show_call_settings' => true,
            'allow_call_recording' => false,
            'allow_state_change' => true,
            'allow_video_calls' => true,
            'allow_internal_chat' => true,
            'disable_iphone_recents' => true,
            'call_delay' => 10,
            'desktop_app_delay' => false,
            'pbx_features' => true,
            'voicemail_extension' => '*97',
            'dnd_on_code' => '*78',
            'dnd_off_code' => '*79',
            'cf_on_code' => '*72',
            'cf_off_code' => '*73',
        ];
    }
}
