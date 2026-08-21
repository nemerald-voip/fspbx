<?php

namespace Tests\Unit;

use App\Services\SipRegistrationAddressResolver;
use PHPUnit\Framework\TestCase;

class SipRegistrationAddressResolverTest extends TestCase
{
    private SipRegistrationAddressResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new SipRegistrationAddressResolver();
    }

    /**
     * @dataProvider publicContactRegistrations
     */
    public function test_plain_registration_moves_public_contact_to_wan_without_inventing_lan(
        string $contact,
        string $callId,
        string $agent,
        string $networkIp,
        string $networkPort
    ): void {
        $resolved = $this->resolver->resolve($contact, $callId, $agent, $networkIp, $networkPort);

        $this->assertSame('', $resolved['lan_ip']);
        $this->assertSame($networkIp, $resolved['wan_ip']);
        $this->assertSame($networkPort, $resolved['port']);
    }

    public static function publicContactRegistrations(): array
    {
        return [
            'Digium D65' => [
                '"101" <sip:101@8.8.8.8:31777;ob>',
                '74AEtpudKhTaJFFWPgpMNNtgOHvXVoJf',
                'Digium D65 2_9_19',
                '8.8.8.8',
                '31777',
            ],
            'Sangoma P325' => [
                '"" <sip:120@9.9.9.9:61359;ob>',
                '4sJ73Z9ISYyXEBIcCKmuFrcab.6NJvTh',
                'Sangoma P325 4_9_2',
                '9.9.9.9',
                '61359',
            ],
        ];
    }

    public function test_nat_registration_keeps_private_contact_and_fs_path_addresses(): void
    {
        $resolved = $this->resolver->resolve(
            '"1038-1038" <sip:1038@10.121.4.34:12210;transport=UDP;received=1.1.1.1:12210;fs_nat=yes;fs_path=sip%3A1038%401.1.1.1%3A12210%3Btransport%3DUDP>',
            '17df848dc450813@10.121.4.34',
            'Sangoma S500 V2.0.4.67',
            '1.1.1.1',
            '12210'
        );

        $this->assertSame('10.121.4.34', $resolved['lan_ip']);
        $this->assertSame('1.1.1.1', $resolved['wan_ip']);
        $this->assertSame('12210', $resolved['port']);
        $this->assertSame('UDP', $resolved['transport']);
        $this->assertSame(
            'sip:1038@10.121.4.34:12210;transport=UDP;received=1.1.1.1:12210;fs_nat=yes;fs_path=sip%3A1038%401.1.1.1%3A12210%3Btransport%3DUDP',
            $resolved['contact']
        );
    }

    public function test_missing_contact_port_falls_back_to_fs_path_port(): void
    {
        $resolved = $this->resolver->resolve(
            '"" <sip:100@192.168.5.86;fs_nat=yes;fs_path=sip%3A100%40208.67.222.222%3A5060>',
            '2cd98f8e446e397daeadec9ebd691c59',
            'PolyEdge-Edge_E350-UA/8.3.1.0614',
            '208.67.222.222',
            '5060'
        );

        $this->assertSame('192.168.5.86', $resolved['lan_ip']);
        $this->assertSame('208.67.222.222', $resolved['wan_ip']);
        $this->assertSame('5060', $resolved['port']);
    }

    public function test_network_port_is_used_when_contact_and_path_have_no_port(): void
    {
        $resolved = $this->resolver->resolve(
            '<sip:100@192.168.5.86;fs_nat=yes>',
            'opaque-call-id',
            'Example phone',
            '208.67.222.222',
            '45123'
        );

        $this->assertSame('45123', $resolved['port']);
    }

    public function test_fanvil_call_id_recovers_private_lan_address(): void
    {
        $resolved = $this->resolver->resolve(
            '"101" <sip:101@8.8.4.4:1851;transport=tcp>',
            '37077566442090-24334045862667@192.168.0.108',
            'Fanvil X3U 2.4.5',
            '8.8.4.4',
            '1851'
        );

        $this->assertSame('192.168.0.108', $resolved['lan_ip']);
        $this->assertSame('8.8.4.4', $resolved['wan_ip']);
        $this->assertSame('1851', $resolved['port']);
        $this->assertSame('TCP', $resolved['transport']);
    }

    public function test_grandstream_encoded_private_call_id_is_a_lan_fallback(): void
    {
        $resolved = $this->resolver->resolve(
            '<sip:205@1.1.1.1:49383>',
            '2010075462-49383-3@BJC.BGI.BBB.CDJ',
            'Grandstream GRP2615 1.0.13.122',
            '1.1.1.1',
            '49383'
        );

        $this->assertSame('192.168.111.239', $resolved['lan_ip']);
        $this->assertSame('1.1.1.1', $resolved['wan_ip']);
    }

    public function test_grandstream_encoded_public_call_id_is_not_mislabeled_as_lan(): void
    {
        $resolved = $this->resolver->resolve(
            '"" <sip:101@8.8.8.8:33424>',
            '1133402387-54864-1@I.I.I.I',
            'Grandstream GRP2615 1.0.11.23',
            '8.8.8.8',
            '33424'
        );

        $this->assertSame('', $resolved['lan_ip']);
        $this->assertSame('8.8.8.8', $resolved['wan_ip']);
        $this->assertSame('33424', $resolved['port']);
    }

    public function test_snom_real_parameter_is_used_as_a_lan_fallback(): void
    {
        $resolved = $this->resolver->resolve(
            '<sip:100@8.8.8.8:5060>;real=192.168.20.15',
            'opaque-call-id',
            'snomD862/10.1.215.13',
            '8.8.8.8',
            '5060'
        );

        $this->assertSame('192.168.20.15', $resolved['lan_ip']);
        $this->assertSame('8.8.8.8', $resolved['wan_ip']);
    }

    public function test_contact_hostname_is_preserved_for_phone_control_targeting(): void
    {
        $resolved = $this->resolver->resolve(
            '<sip:101@customer.example.test:55054;transport=tcp>',
            'opaque-call-id',
            'Ringotel Shell Server',
            '8.8.8.8',
            '55054'
        );

        $this->assertSame('customer.example.test', $resolved['lan_ip']);
        $this->assertSame('8.8.8.8', $resolved['wan_ip']);
        $this->assertSame('55054', $resolved['port']);
    }
}
