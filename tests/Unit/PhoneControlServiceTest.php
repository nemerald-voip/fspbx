<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Extensions;
use App\Services\FreeswitchEslService;
use App\Services\PhoneControlService;
use App\Services\PbxCallControl;
use App\Services\PhoneControlDriverRegistry;
use App\Services\PhoneRegistrationTargetService;
use App\Services\PolyPhoneControlDriver;
use App\Services\SnomPhoneControlDriver;
use App\Services\YealinkPhoneControlDriver;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PhoneControlServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public static function yealinkActionBodies(): array
    {
        return [
            'hold key' => ['hold', null, 'key=F_HOLD'],
            'resume key' => ['resume', null, 'key=F_HOLD'],
            'blind transfer' => ['blind-transfer', '202', 'key=BTrans=202'],
            'attended transfer' => ['attended-transfer', '*5901', 'key=ATrans=*5901'],
            'complete transfer' => ['complete-transfer', null, 'key=F_TRANSFER'],
            'cancel transfer' => ['cancel-transfer', null, 'key=CANCEL'],
            'conference' => ['conference', null, 'key=F_CONFERENCE'],
            'mute toggle' => ['mute-toggle', null, 'key=MUTE'],
            'end call' => ['end-call', null, 'key=CALLEND'],
            'DND on' => ['dnd-on', null, 'key=DNDOn'],
            'DND off' => ['dnd-off', null, 'key=DNDOff'],
            'answer call' => ['answer-call', null, 'key=OK'],
        ];
    }

    /**
     * @dataProvider yealinkActionBodies
     */
    public function test_yealink_driver_builds_documented_action_uri_bodies(
        string $action,
        ?string $destination,
        string $expected
    ): void {
        $driver = new YealinkPhoneControlDriver();

        $this->assertSame($expected, $driver->buildActionBody($action, $destination));
    }

    public function test_yealink_driver_detects_standard_and_oem_agents(): void
    {
        $driver = new YealinkPhoneControlDriver();

        $this->assertTrue($driver->matchesAgent('Yealink SIP-T53W 96.86.0.70'));
        $this->assertTrue($driver->matchesAgent('SIP-T46U 108.86.0.20'));
        $this->assertFalse($driver->matchesAgent('Poly Edge E450 8.3.1'));
    }

    public function test_service_exposes_installed_vendor_drivers(): void
    {
        $driver = new YealinkPhoneControlDriver();
        $service = new PhoneControlService(
            new PhoneControlDriverRegistry($driver, new SnomPhoneControlDriver(), new PolyPhoneControlDriver(new PbxCallControl()), new PbxCallControl()),
            new PhoneRegistrationTargetService()
        );

        $this->assertSame(['yealink', 'snom', 'poly', 'grandstream', 'generic'], $service->supportedVendors());
        $this->assertContains('hold', $service->supportedActions('yealink'));
        $this->assertContains('dnd-toggle', $service->supportedActions('snom'));
        $this->assertContains('answer-call', $service->supportedActions('poly'));
        $this->assertContains('conference', $service->supportedActions('poly'));
        $this->assertContains('conference', $service->supportedActions('grandstream'));
        $this->assertContains('conference', $service->supportedActions('generic'));
    }

    public function test_transfer_requires_a_destination(): void
    {
        $driver = new YealinkPhoneControlDriver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A destination is required for blind-transfer.');

        $driver->buildActionBody('blind-transfer');
    }

    public function test_driver_rejects_action_uri_delimiters_in_user_values(): void
    {
        $driver = new YealinkPhoneControlDriver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination contains unsupported characters.');

        $driver->buildActionBody('blind-transfer', '202&key=Reboot');
    }

    public function test_active_calls_resolves_sip_call_ids_for_the_extension_only(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->with('101@example.test')
            ->andReturn(collect([
                [
                    'uuid' => 'uuid-a',
                    'presence_id' => '101@example.test',
                    'direction' => 'inbound',
                    'callstate' => 'ACTIVE',
                    'cid_num' => '101',
                    'dest' => '100',
                ],
            ]));
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('uuid_getvar uuid-a sip_call_id', false)
            ->andReturn('0_123456@192.168.4.34');

        $service = new PhoneControlService(
            new PhoneControlDriverRegistry(new YealinkPhoneControlDriver(), new SnomPhoneControlDriver(), new PolyPhoneControlDriver(new PbxCallControl()), new PbxCallControl()),
            new PhoneRegistrationTargetService()
        );
        $extension = new Extensions();
        $extension->extension = '101';
        $domain = new Domain();
        $domain->domain_name = 'example.test';

        $calls = $service->activeCalls($esl, $extension, $domain);

        $this->assertCount(1, $calls);
        $this->assertSame('0_123456@192.168.4.34', $calls->first()['sip_call_id']);
        $this->assertSame('100', $calls->first()['other_party']);
    }

    public static function snomActionFragments(): array
    {
        return [
            'hold' => ['hold', null, ['key=F_HOLD']],
            'resume' => ['resume', null, ['key=F_HOLD']],
            'blind transfer' => ['blind-transfer', '100', ['key=F_TRANSFER', 'numberdial=100']],
            'attended transfer' => ['attended-transfer', '100', ['key=F_HOLD', 'numberdial=100']],
            'complete transfer' => ['complete-transfer', null, ['key=F_TRANSFER', 'key=F_OK']],
            'cancel transfer' => ['cancel-transfer', null, ['key=F_CANCEL']],
            'conference' => ['conference', null, ['key=F_CONFERENCE']],
            'mute toggle' => ['mute-toggle', null, ['key=F_MUTE']],
            'end call' => ['end-call', null, ['key=F_CANCEL']],
            'dnd toggle' => ['dnd-toggle', null, ['key=F_DND']],
            'answer call' => ['answer-call', null, ['key=ENTER']],
        ];
    }

    /**
     * @dataProvider snomActionFragments
     */
    public function test_snom_driver_builds_verified_minibrowser_fragments(
        string $action,
        ?string $destination,
        array $expected
    ): void {
        $driver = new SnomPhoneControlDriver();

        $this->assertSame($expected, $driver->buildActionFragments($action, $destination));
    }

    public function test_snom_driver_rejects_deterministic_dnd(): void
    {
        $driver = new SnomPhoneControlDriver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action [dnd-on] is not supported for Snom.');

        $driver->buildActionFragments('dnd-on');
    }

    public function test_snom_driver_detects_snom_agents(): void
    {
        $driver = new SnomPhoneControlDriver();

        $this->assertTrue($driver->matchesAgent('snomD862/10.1.226.13'));
        $this->assertFalse($driver->matchesAgent('Yealink SIP-T53W 96.86.0.70'));
    }

    public function test_snom_driver_sends_one_notify_per_fragment(): void
    {
        $driver = new SnomPhoneControlDriver(0);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('sendEvent')
            ->twice()
            ->with(
                'NOTIFY',
                Mockery::on(fn (array $headers) => $headers['event-string'] === 'xml'
                    && $headers['content-type'] === 'application/snomxml'
                    && $headers['call-id'] === 'registration-call-id'),
                Mockery::on(fn (string $body) => str_contains($body, 'state="relevant"')
                    && (str_contains($body, 'snom://mb_nop#key=F_TRANSFER')
                        || str_contains($body, 'snom://mb_nop#numberdial=100'))),
                false
            )
            ->andReturn('+OK event sent');

        $extension = new Extensions();
        $extension->extension = '102';
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $result = $driver->send(
            $esl,
            $extension,
            $domain,
            [
                'agent' => 'snomD862/10.1.226.13',
                'lan_ip' => '10.0.0.6',
                'sip_profile_name' => 'internal',
                'registrations' => [[
                    'call_id' => 'registration-call-id',
                ]],
            ],
            'blind-transfer',
            '100'
        );

        $this->assertTrue($result['sent']);
        $this->assertSame('snom', $result['vendor']);
        $this->assertSame('key=F_TRANSFER | numberdial=100', $result['body']);
    }

    public static function polyRestCommands(): array
    {
        return [
            'hold with ref' => ['hold', null, 'ref-1', [['command-URI' => '/api/v1/callctrl/holdCall', 'data' => ['Ref' => 'ref-1']]]],
            'hold without ref (--force)' => ['hold', null, null, [['command-URI' => '/api/v1/callctrl/holdCall']]],
            'resume with ref' => ['resume', null, 'ref-1', [['command-URI' => '/api/v1/callctrl/resumeCall', 'data' => ['Ref' => 'ref-1']]]],
            'blind transfer' => ['blind-transfer', '102', 'ref-1', [['command-URI' => '/api/v1/callctrl/transferCall', 'data' => ['Ref' => 'ref-1', 'TransferDest' => '102']]]],
            'attended transfer holds then dials' => ['attended-transfer', '102', 'ref-1', [
                ['command-URI' => '/api/v1/callctrl/holdCall', 'data' => ['Ref' => 'ref-1']],
                ['command-URI' => '/api/v1/callctrl/dial', 'data' => ['Dest' => '102', 'Line' => '1', 'Type' => 'TEL']],
            ]],
            'cancel transfer ends the consultation' => ['cancel-transfer', null, 'consult-ref', [['command-URI' => '/api/v1/callctrl/endCall', 'data' => ['Ref' => 'consult-ref']]]],
            'mute on' => ['mute-on', null, null, [['command-URI' => '/api/v1/callctrl/mute', 'data' => ['state' => '1']]]],
            'mute off' => ['mute-off', null, null, [['command-URI' => '/api/v1/callctrl/mute', 'data' => ['state' => '0']]]],
            'end call' => ['end-call', null, 'ref-1', [['command-URI' => '/api/v1/callctrl/endCall', 'data' => ['Ref' => 'ref-1']]]],
            'answer call' => ['answer-call', null, 'ref-1', [['command-URI' => '/api/v1/callctrl/answerCall', 'data' => ['Ref' => 'ref-1']]]],
        ];
    }

    /**
     * @dataProvider polyRestCommands
     */
    public function test_poly_driver_builds_verified_rest_commands(
        string $action,
        ?string $destination,
        ?string $activeCallId,
        array $expected
    ): void {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        $this->assertSame($expected, $driver->buildCommands($action, $destination, $activeCallId));
    }

    public function test_poly_transfer_requires_a_call_reference(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('blind-transfer requires the call reference');

        $driver->buildCommands('blind-transfer', '102', null);
    }

    public function test_poly_driver_rejects_key_simulation_actions(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action [dnd-on] is not supported for Poly.');

        $driver->buildCommands('dnd-on');
    }

    public function test_poly_driver_detects_edge_agents(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        $this->assertTrue($driver->matchesAgent('PolyEdge-Edge_E350-UA/8.3.1.0614_f04ea4691c59'));
        $this->assertFalse($driver->matchesAgent('snomD862/10.1.226.13'));
        $this->assertFalse($driver->matchesAgent('Yealink SIP-T53W 96.86.0.70'));
    }

    public function test_poly_driver_promotes_legacy_polycom_agents_on_ucs_6_4_2_and_newer(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        // UCS 6.4.2 is when REST-over-NOTIFY delivery arrived — same
        // threshold/detection ClickToDialService uses for click-to-dial.
        $this->assertTrue($driver->matchesAgent('PolycomVVX-VVX_450-UA/6.4.2.1234'));
        $this->assertTrue($driver->matchesAgent('PolycomTrio-Trio_8800-UA/6.4.3.5678'));
        $this->assertTrue($driver->matchesAgent('PolycomCCX-CCX_500-UA/7.1.0.100'));

        // Older UCS 200-OKs the NOTIFY but silently discards it, so those
        // phones fall through to the Generic/PBX-side driver instead.
        $this->assertFalse($driver->matchesAgent('PolycomVVX-VVX_310-UA/5.9.3.1234'));
        $this->assertFalse($driver->matchesAgent('PolycomSoundPointIP-SPIP_650-UA/4.0.1.0'));
    }

    public function test_poly_driver_sends_rest_json_over_notify(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('sendEvent')
            ->once()
            ->with(
                'NOTIFY',
                Mockery::on(fn (array $headers) => $headers['event-string'] === 'ACTION-URI'
                    && $headers['content-type'] === 'application/JSON'
                    && $headers['call-id'] === 'registration-call-id'),
                json_encode(['command-URI' => '/api/v1/callctrl/holdCall', 'data' => ['Ref' => 'sip-call-id-1']]),
                false
            )
            ->andReturn('+OK event sent');

        $extension = new Extensions();
        $extension->extension = '100';
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $result = $driver->send(
            $esl,
            $extension,
            $domain,
            [
                'agent' => 'PolyEdge-Edge_E350-UA/8.3.1.0614_f04ea4691c59',
                'lan_ip' => '10.0.0.7',
                'sip_profile_name' => 'internal',
                'registrations' => [[
                    'call_id' => 'registration-call-id',
                ]],
            ],
            'hold',
            null,
            'sip-call-id-1'
        );

        $this->assertTrue($result['sent']);
        $this->assertSame('poly', $result['vendor']);
    }

    private function genericServiceWithMockedTargets(PhoneRegistrationTargetService|\Mockery\MockInterface $registrationTargets): PhoneControlService
    {
        return new PhoneControlService(
            new PhoneControlDriverRegistry(
                new YealinkPhoneControlDriver(),
                new SnomPhoneControlDriver(),
                new PolyPhoneControlDriver(new PbxCallControl()),
                new PbxCallControl()
            ),
            $registrationTargets
        );
    }

    private function genericCancelTransferGroups(): \Illuminate\Support\Collection
    {
        return collect([[
            'vendor' => 'generic',
            'agent' => 'Some Unrecognized Phone',
            'lan_ip' => '10.0.0.9',
            'sip_profile_name' => 'internal',
            'registrations' => [['call_id' => 'reg-call-id', 'contact' => 'sip:101@10.0.0.9']],
        ]]);
    }

    public function test_cancel_transfer_automatically_resumes_when_exactly_one_held_call_remains(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $registrationTargets = Mockery::mock(PhoneRegistrationTargetService::class);
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $extension = new Extensions();
        $extension->extension = '101';
        $groups = $this->genericCancelTransferGroups();

        $registrationTargets->shouldReceive('resolveCandidates')
            ->once()
            ->andReturn(['domain' => $domain, 'extension' => $extension, 'groups' => $groups]);
        $registrationTargets->shouldReceive('selectGroups')
            ->once()
            ->andReturn(['selected' => $groups, 'skipped' => collect()]);

        // 1st call: cancel-transfer's own channel resolution (held + active).
        // 2nd call: the auto-resume state check (only the held call remains).
        // 3rd call: the driver's own channel lookup while sending the resume.
        $heldOnly = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD', 'direction' => 'inbound', 'cid_num' => '200', 'dest' => '101'],
        ]);
        $esl->shouldReceive('channelsForPresenceId')
            ->times(3)
            ->with('101@example.test')
            ->andReturn(
                collect([
                    ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
                    ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
                ]),
                $heldOnly,
                $heldOnly
            );
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill active-uuid', false)->andReturn('+OK');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar held-uuid sip_call_id', false)->andReturn('sip-call-id-1');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_hold off held-uuid', false)->andReturn('+OK Success');
        $esl->shouldReceive('disconnect')->once();

        $result = $this->genericServiceWithMockedTargets($registrationTargets)
            ->execute($esl, '101', 'example.test', 'cancel-transfer');

        $this->assertTrue($result['results'][0]['sent']);
        $this->assertNotNull($result['auto_resume']);
        $this->assertTrue($result['auto_resume']['sent']);
    }

    public function test_cancel_transfer_polls_until_the_vendor_finishes_clearing_the_consultation(): void
    {
        // "sent" from a vendor-NOTIFY driver only means FreeSWITCH accepted the
        // request — the phone can take a moment to actually hang up. The
        // consultation leg should still show up as present for one extra check
        // before finally clearing.
        $esl = Mockery::mock(FreeswitchEslService::class);
        $registrationTargets = Mockery::mock(PhoneRegistrationTargetService::class);
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $extension = new Extensions();
        $extension->extension = '101';
        $groups = $this->genericCancelTransferGroups();

        $registrationTargets->shouldReceive('resolveCandidates')
            ->once()
            ->andReturn(['domain' => $domain, 'extension' => $extension, 'groups' => $groups]);
        $registrationTargets->shouldReceive('selectGroups')
            ->once()
            ->andReturn(['selected' => $groups, 'skipped' => collect()]);

        $twoCalls = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
            ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
        ]);
        $heldOnly = collect([
            ['uuid' => 'held-uuid', 'callstate' => 'HELD', 'direction' => 'inbound', 'cid_num' => '200', 'dest' => '101'],
        ]);
        $esl->shouldReceive('channelsForPresenceId')
            ->times(4)
            ->with('101@example.test')
            // 1: cancel-transfer's own resolution. 2-3: still clearing (poll,
            // poll). 4: cleared, then the driver's own lookup for resume.
            ->andReturn($twoCalls, $twoCalls, $heldOnly, $heldOnly);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill active-uuid', false)->andReturn('+OK');
        // held-uuid is enriched (sip_call_id lookup) on both poll attempts
        // that still see it; active-uuid only on the first (it's gone by the
        // second poll).
        $esl->shouldReceive('executeCommand')->twice()->with('uuid_getvar held-uuid sip_call_id', false)->andReturn('sip-call-id-1');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_getvar active-uuid sip_call_id', false)->andReturn('sip-call-id-2');
        $esl->shouldReceive('executeCommand')->once()->with('uuid_hold off held-uuid', false)->andReturn('+OK Success');
        $esl->shouldReceive('disconnect')->once();

        $result = $this->genericServiceWithMockedTargets($registrationTargets)
            ->execute($esl, '101', 'example.test', 'cancel-transfer');

        $this->assertNotNull($result['auto_resume']);
        $this->assertTrue($result['auto_resume']['sent']);
    }

    public function test_cancel_transfer_skips_auto_resume_when_state_is_ambiguous(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $registrationTargets = Mockery::mock(PhoneRegistrationTargetService::class);
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $extension = new Extensions();
        $extension->extension = '101';
        $groups = $this->genericCancelTransferGroups();

        $registrationTargets->shouldReceive('resolveCandidates')
            ->once()
            ->andReturn(['domain' => $domain, 'extension' => $extension, 'groups' => $groups]);
        $registrationTargets->shouldReceive('selectGroups')
            ->once()
            ->andReturn(['selected' => $groups, 'skipped' => collect()]);

        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->with('101@example.test')
            ->andReturn(collect([
                ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
                ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
            ]));
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill active-uuid', false)->andReturn('+OK');

        // Nothing is left held (e.g. it hung up too) — auto-resume must not fire.
        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->with('101@example.test')
            ->andReturn(collect());
        $esl->shouldReceive('disconnect')->once();

        $result = $this->genericServiceWithMockedTargets($registrationTargets)
            ->execute($esl, '101', 'example.test', 'cancel-transfer');

        $this->assertNull($result['auto_resume']);
    }

    public function test_no_resume_option_suppresses_auto_resume(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $registrationTargets = Mockery::mock(PhoneRegistrationTargetService::class);
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $extension = new Extensions();
        $extension->extension = '101';
        $groups = $this->genericCancelTransferGroups();

        $registrationTargets->shouldReceive('resolveCandidates')
            ->once()
            ->andReturn(['domain' => $domain, 'extension' => $extension, 'groups' => $groups]);
        $registrationTargets->shouldReceive('selectGroups')
            ->once()
            ->andReturn(['selected' => $groups, 'skipped' => collect()]);

        $esl->shouldReceive('channelsForPresenceId')
            ->once()
            ->with('101@example.test')
            ->andReturn(collect([
                ['uuid' => 'held-uuid', 'callstate' => 'HELD'],
                ['uuid' => 'active-uuid', 'callstate' => 'ACTIVE'],
            ]));
        $esl->shouldReceive('executeCommand')->once()->with('uuid_kill active-uuid', false)->andReturn('+OK');
        $esl->shouldReceive('disconnect')->once();

        $result = $this->genericServiceWithMockedTargets($registrationTargets)
            ->execute($esl, '101', 'example.test', 'cancel-transfer', null, ['no_resume' => true]);

        $this->assertNull($result['auto_resume']);
    }

    public function test_yealink_driver_owns_its_notify_transport(): void
    {
        $driver = new YealinkPhoneControlDriver();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('sendEvent')
            ->once()
            ->with(
                'NOTIFY',
                Mockery::on(fn (array $headers) => $headers['event-string'] === 'ACTION-URI'
                    && $headers['content-type'] === 'message/sipfrag'
                    && $headers['call-id'] === 'registration-call-id'),
                'key=DNDOn',
                false
            )
            ->andReturn('+OK event sent');

        $extension = new Extensions();
        $extension->extension = '101';
        $domain = new Domain();
        $domain->domain_name = 'example.test';
        $result = $driver->send(
            $esl,
            $extension,
            $domain,
            [
                'agent' => 'Yealink SIP-T53W 96.86.0.70',
                'lan_ip' => '10.0.0.5',
                'sip_profile_name' => 'internal',
                'registrations' => [[
                    'call_id' => 'registration-call-id',
                ]],
            ],
            'dnd-on'
        );

        $this->assertTrue($result['sent']);
        $this->assertSame('yealink', $result['vendor']);
        $this->assertSame('key=DNDOn', $result['body']);
    }
}
