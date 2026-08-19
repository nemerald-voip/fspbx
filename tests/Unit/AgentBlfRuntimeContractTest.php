<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgentBlfRuntimeContractTest extends TestCase
{
    public function test_agent_blf_daemon_has_tenant_runtime_and_event_contracts(): void
    {
        $script = File::get(resource_path('freeswitch_scripts/lua/agent_blf.lua'));

        $this->assertStringContainsString('a.agent_id = :agent_id', $script);
        $this->assertStringContainsString('d.domain_name = :domain_name', $script);
        $this->assertStringContainsString('a.call_center_agent_uuid = :agent_uuid', $script);
        $this->assertStringContainsString('callcenter_config agent get status ', $script);
        $this->assertStringContainsString('freeswitch.EventConsumer("CUSTOM", "callcenter::info")', $script);
        $this->assertStringContainsString('CC-Action', $script);
        $this->assertStringContainsString('agent-status-change', $script);
        $this->assertStringContainsString('CC-Agent-Status', $script);
        $this->assertStringContainsString('status == "Available (On Demand)"', $script);
        $this->assertStringContainsString('or status == "On Break"', $script);
        $this->assertStringContainsString('local on_break = status == "On Break"', $script);
        $this->assertStringContainsString('publish_agent_presence(AGENT_PREFIX .. agent_id', $script);
        $this->assertStringContainsString('publish_agent_presence(BREAK_PREFIX .. agent_id', $script);
        $this->assertStringContainsString('local consumer_probe = freeswitch.EventConsumer("PRESENCE_PROBE")', $script);
        $this->assertStringContainsString('consumer_probe:pop(0)', $script);
        $this->assertStringContainsString('consumer_callcenter:pop(0)', $script);
        $this->assertStringContainsString('freeswitch.msleep(50)', $script);
        $this->assertStringNotContainsString('basic_event_service', strtolower($script));
        $this->assertStringNotContainsString('agent+', $script);
    }

    public function test_compact_agent_actions_use_a_purpose_built_authenticated_toggle(): void
    {
        $script = File::get(resource_path('freeswitch_scripts/lua/agent_toggle.lua'));

        $this->assertStringContainsString('session:getVariable("sip_auth_username")', $script);
        $this->assertStringContainsString('tostring(sip_auth_username) ~= requested_agent_id', $script);
        $this->assertStringContainsString('WHERE domain_uuid = :domain_uuid', $script);
        $this->assertStringContainsString('AND agent_id = :agent_id', $script);
        $this->assertStringContainsString('requested_action == "login"', $script);
        $this->assertStringContainsString('current_status == "Available (On Demand)"', $script);
        $this->assertStringContainsString('current_status == "On Break"', $script);
        $this->assertStringContainsString('"callcenter_config agent set status "', $script);
        $this->assertStringContainsString(
            'session:setVariable("sound_prefix", AGENT_SOUND_PREFIX)',
            $script
        );
        $this->assertStringContainsString(
            '/var/www/fspbx/resources/sounds/en/us/alloy/call_center',
            $script
        );
        $this->assertStringContainsString('"logged_in.wav"', $script);
        $this->assertStringContainsString('"logged_out.wav"', $script);
        $this->assertStringContainsString('"on_break.wav"', $script);
        $this->assertStringContainsString('"available.wav"', $script);
        $this->assertStringNotContainsString('ivr-thank_you.wav', $script);
        $this->assertStringNotContainsString('sched_api', $script);
        $this->assertStringNotContainsString('agent+', $script);

        foreach (['available.wav', 'logged_in.wav', 'logged_out.wav', 'on_break.wav'] as $recording) {
            $this->assertFileExists(
                resource_path('sounds/en/us/alloy/call_center/'.$recording)
            );
        }
    }

    public function test_legacy_star_code_script_does_not_implement_new_compact_actions(): void
    {
        $legacy = File::get(resource_path('freeswitch_scripts/app/agent_status/index.lua'));

        $this->assertStringNotContainsString('agent_self_service', $legacy);
        $this->assertStringNotContainsString('toggle_login', $legacy);
    }

    public function test_canonical_lua_config_starts_agent_blf_exactly_once(): void
    {
        $config = File::get(resource_path('autoload_configs/lua.conf.xml'));

        $this->assertSame(
            1,
            substr_count($config, '<param name="startup-script" value="lua/agent_blf.lua"/>')
        );
        $this->assertStringNotContainsString('luarun', $config);
    }
}
