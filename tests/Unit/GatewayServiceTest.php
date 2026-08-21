<?php

namespace Tests\Unit;

use App\Models\Gateways;
use App\Services\FreeswitchEslService;
use App\Services\GatewayService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GatewayServiceTest extends TestCase
{
    /**
     * @dataProvider runtimeFieldProvider
     */
    public function test_each_generated_gateway_field_reloads_an_active_gateway(string $field): void
    {
        $esl = $this->reloadEsl('+OK');
        $service = new TestableGatewayService([$esl]);
        $gateway = $this->gateway();

        $this->assertTrue($service->reloadRegistration(
            $gateway,
            [$field => 'changed'],
            $service->runtimeState($gateway)
        ));
    }

    public static function runtimeFieldProvider(): array
    {
        $fields = [
            'username',
            'distinct_to',
            'auth_username',
            'password',
            'realm',
            'from_user',
            'from_domain',
            'proxy',
            'register_proxy',
            'outbound_proxy',
            'expire_seconds',
            'register',
            'register_transport',
            'contact_params',
            'retry_seconds',
            'extension',
            'ping',
            'ping_min',
            'ping_max',
            'contact_in_ping',
            'context',
            'caller_id_in_from',
            'supress_cng',
            'extension_in_contact',
            'sip_cid_type',
        ];

        return array_combine($fields, array_map(fn (string $field) => [$field], $fields));
    }

    public function test_description_only_change_does_not_connect_to_freeswitch(): void
    {
        $service = new TestableGatewayService();
        $gateway = $this->gateway();

        $this->assertTrue($service->reloadRegistration(
            $gateway,
            ['description' => 'Updated description'],
            $service->runtimeState($gateway)
        ));
    }

    public function test_profile_change_kills_the_gateway_from_its_previous_profile(): void
    {
        $esl = $this->reloadEsl('+OK', 'external');
        $service = new TestableGatewayService([$esl]);
        $gateway = $this->gateway('internal');

        $this->assertTrue($service->reloadRegistration(
            $gateway,
            ['profile' => 'internal'],
            [
                'profile' => 'external',
                'hostname' => null,
                'enabled' => 'true',
            ]
        ));
    }

    public function test_moving_an_active_gateway_off_the_local_node_kills_it_before_rescan(): void
    {
        $esl = $this->reloadEsl('+OK');
        $service = new TestableGatewayService([$esl]);
        $gateway = $this->gateway(hostname: 'node-b');

        $this->assertTrue($service->reloadRegistration(
            $gateway,
            ['hostname' => 'node-b'],
            [
                'profile' => 'external',
                'hostname' => 'node-a',
                'enabled' => 'true',
            ]
        ));
    }

    public function test_disabling_an_active_gateway_kills_it_before_rescan(): void
    {
        $esl = $this->reloadEsl('+OK');
        $service = new TestableGatewayService([$esl]);
        $gateway = $this->gateway(enabled: 'false');

        $this->assertTrue($service->reloadRegistration(
            $gateway,
            ['enabled' => 'false'],
            [
                'profile' => 'external',
                'hostname' => null,
                'enabled' => 'true',
            ]
        ));
    }

    public function test_kill_gateway_failure_keeps_the_runtime_update_unsatisfied(): void
    {
        $esl = $this->reloadEsl('-ERR Invalid Gateway');
        $service = new TestableGatewayService([$esl]);
        $gateway = $this->gateway();

        $this->assertFalse($service->reloadRegistration(
            $gateway,
            ['password' => 'new-secret'],
            $service->runtimeState($gateway)
        ));
    }

    public function test_sync_clears_the_node_cache_and_rescans_each_profile(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('switchname', false)
            ->ordered()
            ->andReturn('node-a');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with("sofia profile 'external' rescan", false)
            ->ordered()
            ->andReturn('+OK');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with("sofia profile 'internal' rescan", false)
            ->ordered()
            ->andReturn('+OK');
        $esl->shouldReceive('disconnect')->once()->ordered();

        $service = new TestableGatewayService([$esl]);

        $this->assertTrue($service->sync(collect(['external', 'internal', 'external'])));
        $this->assertSame(['configuration:sofia.conf:node-a'], $service->clearedKeys);
        $this->assertFalse((bool) session('reload_xml'));
    }

    public function test_sync_keeps_reload_pending_when_rescan_fails(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')->with('switchname', false)->andReturn('node-a');
        $esl->shouldReceive('executeCommand')
            ->with("sofia profile 'external' rescan", false)
            ->andReturn('-ERR Invalid Profile');
        $esl->shouldReceive('disconnect')->once();

        $service = new TestableGatewayService([$esl]);

        $this->assertFalse($service->sync(collect(['external'])));
        $this->assertTrue((bool) session('reload_xml'));
    }

    public function test_sync_keeps_reload_pending_when_freeswitch_is_unavailable(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnFalse();
        $esl->shouldNotReceive('executeCommand');
        $esl->shouldNotReceive('disconnect');

        $service = new TestableGatewayService([$esl]);

        $this->assertFalse($service->sync(collect(['external'])));
        $this->assertTrue((bool) session('reload_xml'));
    }

    private function reloadEsl(string $response, string $profile = 'external'): FreeswitchEslService
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('switchname', false)
            ->ordered()
            ->andReturn('node-a');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with("sofia profile '{$profile}' killgw 76095fa3-2cf4-4253-98f9-34cb51bf7322", false)
            ->ordered()
            ->andReturn($response);
        $esl->shouldReceive('disconnect')->once()->ordered();

        return $esl;
    }

    private function gateway(
        string $profile = 'external',
        ?string $hostname = null,
        string $enabled = 'true',
    ): Gateways {
        return (new Gateways())->forceFill([
            'gateway_uuid' => '76095fa3-2cf4-4253-98f9-34cb51bf7322',
            'profile' => $profile,
            'hostname' => $hostname,
            'enabled' => $enabled,
        ]);
    }
}

class TestableGatewayService extends GatewayService
{
    public array $clearedKeys = [];

    public function __construct(
        private array $services = [],
        private bool $cacheClearResult = true,
    ) {}

    protected function makeEslService(): FreeswitchEslService
    {
        if ($this->services === []) {
            throw new RuntimeException('No FreeSWITCH ESL service was expected for this test.');
        }

        return array_shift($this->services);
    }

    protected function clearCacheKey(string $key): bool
    {
        $this->clearedKeys[] = $key;

        return $this->cacheClearResult;
    }
}
