<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\SofiaProfileRuntimeService;
use Illuminate\Support\Facades\Log;
use Mockery;
use SimpleXMLElement;
use Tests\TestCase;

class SofiaProfileRuntimeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
    }

    /**
     * @dataProvider localRuntimeTransitionProvider
     */
    public function test_it_warms_the_cache_and_applies_the_expected_local_runtime_actions(
        array $transition,
        array $expectedCommands,
    ): void {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('switchname', false)
            ->ordered()
            ->andReturn('node-a');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('xml_locate configuration configuration name sofia.conf', false)
            ->ordered()
            ->andReturn(new SimpleXMLElement('<document/>'));

        foreach ($expectedCommands as $command) {
            $esl->shouldReceive('executeCommand')
                ->once()
                ->with($command, false)
                ->ordered()
                ->andReturn('+OK');
        }

        $esl->shouldReceive('disconnect')->once()->ordered();

        $service = new TestableSofiaProfileRuntimeService($esl);

        $this->assertTrue($service->synchronize(collect([$transition]), collect([null])));
        $this->assertSame(
            ['configuration:sofia.conf:node-a'],
            $service->clearedKeys
        );
        $this->assertFalse((bool) session('reload_xml'));
    }

    public static function localRuntimeTransitionProvider(): array
    {
        return [
            'update running profile' => [
                [
                    'before' => self::state('internal', 'true'),
                    'after' => self::state('internal', 'true'),
                ],
                ["sofia profile 'internal' rescan"],
            ],
            'create enabled profile' => [
                [
                    'before' => null,
                    'after' => self::state('new-profile', 'true'),
                ],
                ["sofia profile 'new-profile' start"],
            ],
            'enable profile' => [
                [
                    'before' => self::state('internal', 'false'),
                    'after' => self::state('internal', 'true'),
                ],
                ["sofia profile 'internal' start"],
            ],
            'disable profile' => [
                [
                    'before' => self::state('internal', 'true'),
                    'after' => self::state('internal', 'false'),
                ],
                ["sofia profile 'internal' stop"],
            ],
            'delete profile' => [
                [
                    'before' => self::state('internal', 'true'),
                    'after' => null,
                ],
                ["sofia profile 'internal' stop"],
            ],
            'rename running profile' => [
                [
                    'before' => self::state('internal', 'true'),
                    'after' => self::state('office', 'true'),
                ],
                [
                    "sofia profile 'internal' stop",
                    "sofia profile 'office' start",
                ],
            ],
        ];
    }

    public function test_it_defers_runtime_actions_when_freeswitch_is_unavailable(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnFalse();
        $esl->shouldNotReceive('executeCommand');
        $esl->shouldNotReceive('disconnect');

        $service = new TestableSofiaProfileRuntimeService($esl);

        $result = $service->synchronize(
            collect([[
                'before' => self::state('internal', 'true', 'node-a'),
                'after' => self::state('internal', 'true', 'node-a'),
            ]]),
            collect(['node-a'])
        );

        $this->assertFalse($result);
        $this->assertSame(
            ['configuration:sofia.conf:node-a'],
            $service->clearedKeys
        );
        $this->assertTrue((bool) session('reload_xml'));
    }

    public function test_it_keeps_reload_pending_when_cache_warming_or_rescan_fails(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('switchname', false)
            ->andReturn('node-a');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('xml_locate configuration configuration name sofia.conf', false)
            ->andReturnNull();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with("sofia profile 'internal' rescan", false)
            ->andReturn('-ERR cannot find config for profile internal');
        $esl->shouldReceive('disconnect')->once();

        $service = new TestableSofiaProfileRuntimeService($esl);

        $result = $service->synchronize(
            collect([[
                'before' => self::state('internal', 'true'),
                'after' => self::state('internal', 'true'),
            ]]),
            collect([null])
        );

        $this->assertFalse($result);
        $this->assertTrue((bool) session('reload_xml'));
    }

    private static function state(
        string $name,
        string $enabled,
        ?string $hostname = null,
    ): array {
        return [
            'name' => $name,
            'hostname' => $hostname,
            'enabled' => $enabled,
        ];
    }
}

class TestableSofiaProfileRuntimeService extends SofiaProfileRuntimeService
{
    public array $clearedKeys = [];

    protected function clearCacheKey(string $key): bool
    {
        $this->clearedKeys[] = $key;

        return true;
    }
}
