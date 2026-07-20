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
        $this->expectExceptionMessage('Action [dnd-on] is not supported for Poly Edge.');

        $driver->buildCommands('dnd-on');
    }

    public function test_poly_driver_detects_edge_agents(): void
    {
        $driver = new PolyPhoneControlDriver(new PbxCallControl());

        $this->assertTrue($driver->matchesAgent('PolyEdge-Edge_E350-UA/8.3.1.0614_f04ea4691c59'));
        $this->assertFalse($driver->matchesAgent('snomD862/10.1.226.13'));
        $this->assertFalse($driver->matchesAgent('Yealink SIP-T53W 96.86.0.70'));
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
