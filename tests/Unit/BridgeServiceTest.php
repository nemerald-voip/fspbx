<?php

namespace Tests\Unit;

use App\Services\BridgeService;
use PHPUnit\Framework\TestCase;

class BridgeServiceTest extends TestCase
{
    public function test_it_keeps_sip_headers_out_of_a_gateway_destination(): void
    {
        $destination = (new BridgeService())->bridgeDestination([
            'bridge_action' => 'gateway',
            'bridge_gateway_1' => '9df158e6-b145-4c21-b3a9-049b4ee57e9a:Retell',
            'destination_number' => 'retell-destination',
            'bridge_headers' => [
                ['name' => 'X-FSPBX-Domain-UUID', 'value' => '${domain_uuid}'],
                ['name' => 'sip_h_X-FSPBX-Call-UUID', 'value' => '${uuid}'],
                ['name' => 'X-FSPBX-DID', 'value' => '${destination_number}'],
                ['name' => 'X-FSPBX-Caller-ID', 'value' => '${caller_id_number}'],
            ],
        ]);

        $this->assertSame(
            'sofia/gateway/9df158e6-b145-4c21-b3a9-049b4ee57e9a/retell-destination',
            $destination
        );
    }

    public function test_it_parses_sip_headers_for_structured_editing(): void
    {
        $form = (new BridgeService())->parseDestination(
            '{continue_on_fail=true,sip_h_X-FSPBX-Call-UUID=${uuid}}sofia/external/retell-destination'
        );

        $this->assertSame('profile', $form['bridge_action']);
        $this->assertSame('external', $form['bridge_profile']);
        $this->assertSame(['continue_on_fail' => 'true', 'sip_h_X-FSPBX-Call-UUID' => '${uuid}'], $form['bridge_variables']);
        $this->assertSame([
            ['name' => 'X-FSPBX-Call-UUID', 'value' => '${uuid}'],
        ], $form['bridge_headers']);
    }

    public function test_removing_structured_headers_removes_old_header_variables(): void
    {
        $destination = (new BridgeService())->bridgeDestination([
            'bridge_action' => 'profile',
            'bridge_profile' => 'external',
            'destination_number' => 'retell-destination',
            'bridge_variables' => [
                'continue_on_fail' => 'true',
                'sip_h_X-Old-Header' => 'old-value',
            ],
            'bridge_headers' => [],
        ]);

        $this->assertSame(
            '{continue_on_fail=true}sofia/external/retell-destination',
            $destination
        );
    }

    public function test_empty_headers_do_not_add_an_empty_variable_block(): void
    {
        $destination = (new BridgeService())->bridgeDestination([
            'bridge_action' => 'gateway',
            'bridge_gateway_1' => '9df158e6-b145-4c21-b3a9-049b4ee57e9a:Retell',
            'destination_number' => 'retell-destination',
            'bridge_headers' => [],
        ]);

        $this->assertSame(
            'sofia/gateway/9df158e6-b145-4c21-b3a9-049b4ee57e9a/retell-destination',
            $destination
        );
    }

    public function test_bridge_destination_actions_execute_the_resolver(): void
    {
        $action = buildDestinationAction([
            'type' => 'bridges',
            'extension' => 'sofia/gateway/retell/1000',
            'bridge_uuid' => '9df158e6-b145-4c21-b3a9-049b4ee57e9a',
        ], 'example.test');

        $this->assertSame([
            'destination_app' => 'lua',
            'destination_data' => 'bridge.lua 9df158e6-b145-4c21-b3a9-049b4ee57e9a',
            'bridge_uuid' => '9df158e6-b145-4c21-b3a9-049b4ee57e9a',
        ], $action);
    }

    public function test_existing_variables_can_be_reused_without_compiling_headers(): void
    {
        $existing = (new BridgeService())->parseDestination(
            '{continue_on_fail=true,sip_h_X-Old=old}sofia/external/retell-destination'
        );

        $destination = (new BridgeService())->bridgeDestination([
            'bridge_action' => 'profile',
            'bridge_profile' => 'external',
            'destination_number' => 'retell-destination',
            'bridge_variables' => $existing['bridge_variables'],
            'bridge_headers' => [
                ['name' => 'X-New', 'value' => '${uuid}'],
            ],
        ]);

        $this->assertSame(
            '{continue_on_fail=true}sofia/external/retell-destination',
            $destination
        );
    }
}
