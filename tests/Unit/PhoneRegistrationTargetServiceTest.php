<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Extensions;
use App\Services\ClickToDialService;
use App\Services\FreeswitchEslService;
use App\Services\PhoneControlDriverRegistry;
use App\Services\PhoneControlService;
use App\Services\PhoneRegistrationTargetService;
use App\Services\SnomPhoneControlDriver;
use App\Services\YealinkPhoneControlDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PhoneRegistrationTargetServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_click_to_dial_and_phone_control_share_candidate_resolution(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $targets = Mockery::mock(PhoneRegistrationTargetService::class);
        $groups = collect([$this->group('yealink', '10.0.0.5', 'call-a', 10)]);
        $targets->shouldReceive('resolveCandidates')
            ->twice()
            ->with(
                $esl,
                '101',
                'example.test',
                Mockery::on(fn (callable $identifyVendor) => $identifyVendor('Yealink SIP-T53W')['vendor'] === 'yealink')
            )
            ->andReturn([
                'domain' => new Domain(),
                'extension' => new Extensions(),
                'groups' => $groups,
            ]);

        $clickToDial = new ClickToDialService($targets);
        $phoneControl = new PhoneControlService(
            new PhoneControlDriverRegistry(new YealinkPhoneControlDriver(), new SnomPhoneControlDriver()),
            $targets
        );

        $this->assertSame($groups, $clickToDial->candidateGroups($esl, '101', 'example.test'));
        $this->assertSame($groups, $phoneControl->candidateGroups($esl, '101', 'example.test'));
    }

    public function test_it_groups_and_orders_registrations_for_both_phone_consumers(): void
    {
        $service = new PhoneRegistrationTargetService();
        $groups = $service->groupRegistrations(
            collect([
                $this->registration('old-call', 10),
                $this->registration('fresh-call', 50),
                $this->registration('unsupported-call', 100, 'Unsupported Softphone'),
            ]),
            fn (string $agent) => str_contains($agent, 'Yealink')
                ? ['vendor' => 'yealink', 'label' => 'Yealink']
                : null
        );

        $this->assertCount(1, $groups);
        $this->assertSame('yealink', $groups[0]['vendor']);
        $this->assertSame('10.0.0.5', $groups[0]['lan_ip']);
        $this->assertSame(2, $groups[0]['count']);
        $this->assertSame('fresh-call', $groups[0]['registrations'][0]['call_id']);
    }

    public function test_it_selects_the_freshest_group_and_reports_other_matches(): void
    {
        $service = new PhoneRegistrationTargetService();
        $groups = collect([
            $this->group('yealink', '10.0.0.5', 'old-call', 10),
            $this->group('yealink', '10.0.0.6', 'fresh-call', 50),
        ]);

        $selection = $service->selectGroups(
            $groups,
            ['vendor' => 'yealink'],
            ['yealink'],
            'controllable registrations'
        );

        $this->assertSame('10.0.0.6', $selection['selected']->first()['lan_ip']);
        $this->assertSame('10.0.0.5', $selection['skipped']->first()['lan_ip']);
    }

    public function test_registration_call_id_selects_one_group(): void
    {
        $service = new PhoneRegistrationTargetService();
        $groups = collect([
            $this->group('yealink', '10.0.0.5', 'call-a', 10),
            $this->group('yealink', '10.0.0.6', 'call-b', 50),
        ]);

        $selection = $service->selectGroups(
            $groups,
            ['call_id' => 'call-a'],
            ['yealink'],
            'supported phone-control registrations'
        );

        $this->assertSame('call-a', $selection['selected']->first()['registrations'][0]['call_id']);
        $this->assertTrue($selection['skipped']->isEmpty());
    }

    public function test_call_id_selection_puts_the_matched_registration_first(): void
    {
        $service = new PhoneRegistrationTargetService();
        $group = $this->group('yealink', '10.0.0.5', 'fresh-call', 50);
        $group['registrations'][] = ['call_id' => 'old-call', 'expsecs' => 10];
        $group['count'] = 2;

        $selection = $service->selectGroups(
            collect([$group]),
            ['call_id' => 'old-call'],
            ['yealink'],
            'supported phone-control registrations'
        );

        $registrations = $selection['selected']->first()['registrations'];
        $this->assertSame('old-call', $registrations[0]['call_id']);
        $this->assertSame('fresh-call', $registrations[1]['call_id']);
    }

    public function test_it_rejects_a_vendor_not_supported_by_the_consumer(): void
    {
        $service = new PhoneRegistrationTargetService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown vendor [poly]. Valid vendors: yealink.');

        $service->selectGroups(
            collect([$this->group('yealink', '10.0.0.5', 'call-a', 10)]),
            ['vendor' => 'poly'],
            ['yealink'],
            'supported phone-control registrations'
        );
    }

    private function registration(
        string $callId,
        int $expires,
        string $agent = 'Yealink SIP-T53W 96.86.0.70 X-LAN: 10.0.0.5'
    ): array {
        return [
            'call_id' => $callId,
            'agent' => $agent,
            'lan_ip' => '198.51.100.20',
            'contact' => 'sip:101@10.0.0.5:5060',
            'sip_profile_name' => 'internal',
            'expsecs' => $expires,
        ];
    }

    private function group(
        string $vendor,
        string $lanIp,
        string $callId,
        int $expires
    ): array {
        return [
            'index' => $lanIp === '10.0.0.5' ? 1 : 2,
            'vendor' => $vendor,
            'label' => ucfirst($vendor),
            'agent' => 'Yealink SIP-T53W',
            'lan_ip' => $lanIp,
            'registration_lan_ip' => $lanIp,
            'sip_profile_name' => 'internal',
            'count' => 1,
            'registrations' => [[
                'call_id' => $callId,
                'expsecs' => $expires,
            ]],
        ];
    }
}
