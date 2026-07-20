<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\PbxCallControl;
use Mockery;
use Tests\TestCase;

class PbxCallControlTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_hold_and_resume_run_native_uuid_hold(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_hold abc', false)->andReturn('+OK Success');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_hold off abc', false)->andReturn('+OK Success');

        $hold = $pbx->hold($esl, 'abc');
        $resume = $pbx->resume($esl, 'abc');

        $this->assertTrue($hold['sent']);
        $this->assertTrue($resume['sent']);
    }

    public function test_mute_sends_the_verified_uuid_audio_syntax(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_audio abc start write mute 1', false)->andReturn('+OK');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_audio abc start write mute 0', false)->andReturn('+OK');

        $this->assertTrue($pbx->mute($esl, 'abc', true)['sent']);
        $this->assertTrue($pbx->mute($esl, 'abc', false)['sent']);
    }

    public function test_end_call_reports_failure_reason(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill abc', false)->andReturn('-ERR No such channel!');

        $result = $pbx->endCall($esl, 'abc');

        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('uuid_kill abc failed', $result['reason']);
    }

    public function test_blind_transfer_moves_the_far_leg(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar local-uuid bridge_uuid', false)->andReturn('far-uuid');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_transfer far-uuid 200 XML example.test', false)->andReturn('+OK');

        $result = $pbx->blindTransfer($esl, 'local-uuid', '200', 'example.test');

        $this->assertTrue($result['sent']);
    }

    public function test_blind_transfer_fails_cleanly_when_far_leg_unresolvable(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar local-uuid bridge_uuid', false)->andReturn('_undef_');

        $result = $pbx->blindTransfer($esl, 'local-uuid', '200', 'example.test');

        $this->assertFalse($result['sent']);
    }

    public function test_bridge_held_and_active_joins_the_far_legs(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar held-uuid bridge_uuid', false)->andReturn('far-held');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar active-uuid bridge_uuid', false)->andReturn('far-active');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_bridge far-held far-active', false)->andReturn('+OK');

        $channels = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
            ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
        ]);

        $result = $pbx->bridgeHeldAndActive($esl, $channels);

        $this->assertTrue($result['sent']);
    }

    public function test_bridge_held_and_active_requires_both_states(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);

        $result = $pbx->bridgeHeldAndActive($esl, collect([['uuid' => 'a', 'callstate' => 'ACTIVE']]));

        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('complete-transfer needs', $result['reason']);
    }

    public function test_conference_held_and_active_moves_both_far_legs_into_a_room(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar held-uuid bridge_uuid', false)->andReturn('far-held');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with(Mockery::on(fn (string $cmd) => str_starts_with(
                $cmd,
                "uuid_transfer active-uuid -both 'set:conference_silent_entry=true,conference:phonectl-101-"
            ) && str_contains($cmd, "@default+flags{mintwo}' inline")), false)
            ->andReturn('+OK');
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with(Mockery::on(fn (string $cmd) => str_starts_with(
                $cmd,
                "uuid_transfer far-held 'set:conference_silent_entry=true,conference:phonectl-101-"
            ) && str_contains($cmd, "@default+flags{mintwo}' inline")), false)
            ->andReturn('+OK');

        $channels = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
            ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
        ]);

        $result = $pbx->conferenceHeldAndActive($esl, $channels, 'phonectl-101');

        $this->assertTrue($result['sent']);
        $this->assertStringStartsWith('phonectl-101-', $result['conference_room']);
    }

    public function test_end_active_consultation_ends_only_the_non_held_call(): void
    {
        $pbx = new PbxCallControl();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill active-uuid', false)->andReturn('+OK');

        $channels = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
            ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
        ]);

        $result = $pbx->endActiveConsultation($esl, $channels);

        $this->assertTrue($result['sent']);
    }
}
