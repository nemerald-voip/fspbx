<?php

namespace Tests\Unit;

use App\Services\CallFlowService;
use App\Services\RingGroupService;
use App\Services\BasicDialerService;
use App\Services\BasicQueueService;
use App\Services\OutboundRouteService;
use App\Models\BasicDialerCampaign;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\VirtualReceptionistController;
use PHPUnit\Framework\TestCase;

class BridgeRuntimeRoutingTest extends TestCase
{
    private const TARGET = '9df158e6-b145-4c21-b3a9-049b4ee57e9a';

    public function test_ring_group_bridge_timeout_executes_the_resolver(): void
    {
        $data = (new RingGroupService())->buildUpdateData([
            'ring_group_strategy' => 'simultaneous',
            'members' => [],
            'timeout_action' => 'bridges',
            'timeout_target' => self::TARGET,
            'ring_group_forward_enabled' => false,
        ], 'example.test');

        $this->assertSame('lua', $data['ring_group_timeout_app']);
        $this->assertSame('bridge.lua ' . self::TARGET, $data['ring_group_timeout_data']);
    }

    public function test_call_flow_bridge_executes_the_resolver(): void
    {
        $data = (new CallFlowService())->buildSaveData([
            'call_flow_action' => 'bridges',
            'call_flow_target' => self::TARGET,
        ], null, 'example.test');

        $this->assertSame('lua', $data['call_flow_app']);
        $this->assertSame('bridge.lua ' . self::TARGET, $data['call_flow_data']);
    }

    public function test_basic_queue_bridge_timeout_executes_the_resolver(): void
    {
        $method = new \ReflectionMethod(BasicQueueService::class, 'buildQueueTimeoutAction');
        $action = $method->invoke(new BasicQueueService(), [
            'timeout_action' => 'bridges',
            'timeout_target' => self::TARGET,
        ], 'example.test');

        $this->assertSame('lua:bridge.lua ' . self::TARGET, $action);
    }

    public function test_business_hours_bridge_fallback_executes_the_resolver(): void
    {
        $controller = (new \ReflectionClass(BusinessHoursController::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'buildExitDestinationAction');
        $action = $method->invoke($controller, [
            'failback_action' => 'bridges',
            'failback_target' => self::TARGET,
        ]);

        $this->assertSame(['action' => 'lua', 'data' => 'bridge.lua ' . self::TARGET], $action);
    }

    public function test_virtual_receptionist_bridge_key_executes_the_resolver(): void
    {
        $controller = (new \ReflectionClass(VirtualReceptionistController::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'buildKeyDestinationAction');
        $action = $method->invoke($controller, [
            'action' => 'bridges',
            'target' => self::TARGET,
        ]);

        $this->assertSame('lua bridge.lua ' . self::TARGET, $action);
    }

    public function test_basic_dialer_executes_the_runtime_bridge_resolver(): void
    {
        $campaign = new BasicDialerCampaign();
        $campaign->forceFill([
            'destination_type' => 'bridges',
            'destination_target' => self::TARGET,
        ]);

        $method = new \ReflectionMethod(BasicDialerService::class, 'answeredApplication');
        $application = $method->invoke(new BasicDialerService(), $campaign, 'example.test');

        $this->assertSame(
            '&lua(bridge.lua 9df158e6-b145-4c21-b3a9-049b4ee57e9a)',
            $application
        );
    }

    public function test_outbound_route_bridge_option_executes_the_resolver(): void
    {
        $service = (new \ReflectionClass(OutboundRouteService::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'destination');
        $destination = $method->invoke($service, 'bridge_uuid:' . self::TARGET);

        $this->assertSame('bridge_uuid', $destination['type']);
        $this->assertSame('bridge.lua ' . self::TARGET, $destination['data']);
    }
}
