<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Extensions;
use App\Services\FreeswitchEslService;
use App\Services\PhoneControlService;
use App\Services\PhoneControlDriverRegistry;
use App\Services\PhoneRegistrationTargetService;
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
            new PhoneControlDriverRegistry($driver, new SnomPhoneControlDriver()),
            new PhoneRegistrationTargetService()
        );

        $this->assertSame(['yealink', 'snom'], $service->supportedVendors());
        $this->assertContains('hold', $service->supportedActions('yealink'));
        $this->assertContains('dnd-toggle', $service->supportedActions('snom'));
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
        $esl->shouldReceive('getAllChannels')
            ->once()
            ->with(false)
            ->andReturn(collect([
                [
                    'uuid' => 'uuid-a',
                    'presence_id' => '101@example.test',
                    'direction' => 'inbound',
                    'callstate' => 'ACTIVE',
                    'cid_num' => '101',
                    'dest' => '100',
                ],
                [
                    'uuid' => 'uuid-b',
                    'presence_id' => '100@example.test',
                    'direction' => 'outbound',
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
            new PhoneControlDriverRegistry(new YealinkPhoneControlDriver(), new SnomPhoneControlDriver()),
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
