<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AiAgentRuntimeContractTest extends TestCase
{
    public function test_outbound_runtime_uses_external_tcp_without_a_gateway_or_credentials(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/resources/freeswitch_scripts/ai_agent.lua');

        $this->assertStringContainsString('sofia/external/', $script);
        $this->assertStringContainsString('@sip.retellai.com;transport=tcp', $script);
        $this->assertStringContainsString('sip_h_X-FSPBX-Agent-UUID', $script);
        $this->assertStringContainsString('sip_h_X-FSPBX-SIP-Host', $script);
        $this->assertStringContainsString('i.public_sip_host', $script);
        $this->assertStringContainsString("s.sip_profile_setting_name = 'sip-port'", $script);
        $this->assertStringContainsString('value:match("^%$%${([%w_.%-]+)}$")', $script);
        $this->assertStringContainsString('api:execute("global_getvar", variable_name)', $script);
        $this->assertStringContainsString('resolve_global_value(agent.external_sip_port)', $script);
        $this->assertStringContainsString('public_sip_host .. ":" .. external_sip_port', $script);
        $this->assertStringContainsString('log("Final dial string: " .. dial_string)', $script);
        $this->assertStringContainsString('session:execute("bridge", dial_string)', $script);
        $this->assertStringNotContainsString('recording_disabled', $script);
        $this->assertStringNotContainsString('sofia/gateway/', $script);
        $this->assertStringNotContainsString('password', strtolower($script));
    }

    public function test_return_runtime_requires_the_strict_uuid_transfer_shape_and_tenant_extension(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/resources/freeswitch_scripts/ai_agent_return.lua');

        $this->assertStringContainsString('^xfer%.', $script);
        $this->assertStringContainsString('e.domain_uuid = a.domain_uuid', $script);
        $this->assertStringContainsString('a.provisioning_status = \'synced\'', $script);
        $this->assertStringContainsString('log("Received SIP Request-URI user: " .. request_user)', $script);
        $this->assertStringContainsString('"Parsed transfer target: AI Agent UUID="', $script);
        $this->assertStringContainsString('"Matched transfer target: AI Agent UUID="', $script);
        $this->assertStringContainsString('log("Final transfer command: " .. transfer_destination)', $script);
        $this->assertStringContainsString('session:execute("transfer", transfer_destination)', $script);
    }

    public function test_phone_number_routing_builder_transfers_to_the_ai_agent_extension(): void
    {
        require_once dirname(__DIR__, 2) . '/app/helpers.php';

        $this->assertSame([
            'destination_app' => 'transfer',
            'destination_data' => '9450 XML account.example.com',
        ], buildDestinationAction([
            'type' => 'ai_agents',
            'extension' => '9450',
        ], 'account.example.com'));
    }
}
