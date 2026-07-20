<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Extensions;
use App\Services\FreeswitchEslService;
use App\Services\GenericPhoneControlDriver;
use App\Services\PbxCallControl;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class GenericPhoneControlDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function group(): array
    {
        return [
            'agent' => 'Grandstream GXP2135 1.0.11.103',
            'lan_ip' => '192.168.5.72',
            'sip_profile_name' => 'internal',
            'registrations' => [[
                'call_id' => 'registration-call-id',
                'contact' => 'sip:107@192.168.5.72:19402',
            ]],
        ];
    }

    private function extensionAndDomain(): array
    {
        $extension = new Extensions();
        $extension->extension = '107';
        $extension->user_context = 'example.test';
        $domain = new Domain();
        $domain->domain_name = 'example.test';

        return [$extension, $domain];
    }

    public function test_grandstream_matcher_is_specific(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);

        $this->assertTrue($driver->matchesAgent('Grandstream GXP2135 1.0.11.103'));
        $this->assertFalse($driver->matchesAgent('Yealink SIP-T53W 96.86.0.70'));
    }

    public function test_generic_matcher_is_a_catch_all(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'generic', 'Generic (PBX-assisted)', []);

        $this->assertTrue($driver->matchesAgent('Anything At All 1.0'));
        $this->assertTrue($driver->matchesAgent(''));
    }

    public function test_supported_actions_exclude_vendor_dependent_features(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'generic', 'Generic (PBX-assisted)', []);
        $actions = $driver->supportedActions();

        $this->assertContains('hold', $actions);
        $this->assertContains('conference', $actions);
        $this->assertNotContains('dnd-on', $actions);
        $this->assertNotContains('dnd-toggle', $actions);
        $this->assertNotContains('answer-call', $actions);
        $this->assertNotContains('mute-toggle', $actions);
    }

    public function test_unsupported_action_is_rejected(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action [dnd-on] is not supported for Grandstream.');

        $driver->send(Mockery::mock(FreeswitchEslService::class), $extension, $domain, $this->group(), 'dnd-on');
    }

    public function test_blind_transfer_requires_a_destination(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A destination is required for blind-transfer.');

        $driver->send(Mockery::mock(FreeswitchEslService::class), $extension, $domain, $this->group(), 'blind-transfer');
    }

    public function test_dry_run_never_touches_the_channel(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('channelsForPresenceId');
        $esl->shouldNotReceive('executeCommand');

        $result = $driver->send($esl, $extension, $domain, $this->group(), 'hold', null, null, true);

        $this->assertTrue($result['sent']);
        $this->assertSame('dry-run', $result['result']);
    }

    public function test_hold_finds_the_extensions_channel_and_runs_uuid_hold(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->with('107@example.test')
            ->andReturn(collect([['uuid' => 'local-uuid', 'callstate' => 'ACTIVE']]));
        $esl->shouldReceive('executeCommand')->once()->with('uuid_hold local-uuid', false)->andReturn('+OK Success');

        $result = $driver->send($esl, $extension, $domain, $this->group(), 'hold');

        $this->assertTrue($result['sent']);
        $this->assertSame('grandstream', $result['vendor']);
    }

    public function test_no_call_found_is_reported_without_touching_the_channel(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('channelsForPresenceId')->once()->andReturn(collect());

        $result = $driver->send($esl, $extension, $domain, $this->group(), 'hold');

        $this->assertFalse($result['sent']);
        $this->assertSame('No active call was found for this extension.', $result['reason']);
    }

    public function test_blind_transfer_moves_the_far_leg_and_uses_the_extensions_context(): void
    {
        $driver = new GenericPhoneControlDriver(new PbxCallControl(), 'grandstream', 'Grandstream', ['grandstream']);
        [$extension, $domain] = $this->extensionAndDomain();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->andReturn(collect([['uuid' => 'local-uuid', 'callstate' => 'ACTIVE']]));
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar local-uuid bridge_uuid', false)->andReturn('far-uuid');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_transfer far-uuid 200 XML example.test', false)->andReturn('+OK');

        $result = $driver->send($esl, $extension, $domain, $this->group(), 'blind-transfer', '200');

        $this->assertTrue($result['sent']);
    }
}
