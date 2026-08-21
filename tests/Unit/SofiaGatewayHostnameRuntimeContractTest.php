<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SofiaGatewayHostnameRuntimeContractTest extends TestCase
{
    public function test_sofia_configuration_uses_the_current_switchname_for_its_cache(): void
    {
        $script = $this->sofiaConfigurationScript();

        $this->assertStringContainsString(
            'local hostname = trim(api:execute("switchname", ""))',
            $script
        );
        $this->assertStringContainsString(
            'local sofia_cache_key = "configuration:sofia.conf:" .. hostname',
            $script
        );
    }

    public function test_gateway_query_includes_matching_and_unscoped_hostnames(): void
    {
        $script = $this->sofiaConfigurationScript();

        $this->assertStringContainsString('sql = "select * from v_gateways ";', $script);
        $this->assertStringContainsString(
            'sql = sql .. "and (hostname = :hostname or hostname is null or hostname = \'\') ";',
            $script
        );
        $this->assertStringContainsString(
            'local params = {profile = sip_profile_name, hostname = hostname};',
            $script
        );
    }

    private function sofiaConfigurationScript(): string
    {
        return File::get(resource_path(
            'freeswitch_scripts/app/xml_handler/resources/scripts/configuration/sofia.conf.lua'
        ));
    }
}
