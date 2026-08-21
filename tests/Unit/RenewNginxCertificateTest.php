<?php

namespace Tests\Unit;

use App\Console\Commands\RenewNginxCertificate;
use App\Services\ScheduledAnnouncements\AuthoritativeDnsActiveNodeGuard;
use PHPUnit\Framework\TestCase;

class RenewNginxCertificateTest extends TestCase
{
    public function test_single_server_renews_without_using_the_dns_guard(): void
    {
        $guard = $this->guard(['active' => false, 'status' => 'active_unknown']);
        $command = $this->command($guard);

        $decision = $command->decide(['MODE' => 'single']);

        $this->assertTrue($decision['run']);
        $this->assertSame('single', $decision['status']);
        $this->assertSame(0, $guard->calls);
    }

    public function test_disabled_former_peer_does_not_renew(): void
    {
        $guard = $this->guard(['active' => true, 'status' => 'active']);
        $decision = $this->command($guard)->decide(['MODE' => 'disabled']);

        $this->assertFalse($decision['run']);
        $this->assertSame('disabled', $decision['status']);
        $this->assertSame(0, $guard->calls);
    }

    public function test_active_redundant_node_renews(): void
    {
        $guard = $this->guard([
            'active' => true,
            'status' => 'active',
            'answers' => ['ns1.example.com' => ['192.0.2.10']],
        ]);
        $decision = $this->command($guard)->decide($this->redundantConfig());

        $this->assertTrue($decision['run']);
        $this->assertSame('active', $decision['status']);
        $this->assertSame('portal.example.com', $guard->fqdn);
        $this->assertSame(['192.0.2.10'], $guard->nodeIps);
    }

    public function test_standby_redundant_node_skips_renewal(): void
    {
        $guard = $this->guard([
            'active' => false,
            'status' => 'standby',
            'answers' => ['ns1.example.com' => ['192.0.2.20']],
        ]);
        $decision = $this->command($guard)->decide($this->redundantConfig());

        $this->assertFalse($decision['run']);
        $this->assertSame('standby', $decision['status']);
    }

    public function test_uncertain_dns_fails_closed(): void
    {
        $guard = $this->guard([
            'active' => false,
            'status' => 'active_unknown',
            'reason' => 'Authoritative nameservers disagreed',
        ]);
        $decision = $this->command($guard)->decide($this->redundantConfig());

        $this->assertFalse($decision['run']);
        $this->assertSame('active_unknown', $decision['status']);
        $this->assertStringContainsString('failed closed', $decision['reason']);
    }

    public function test_floating_dns_pointing_to_both_nodes_fails_closed(): void
    {
        $guard = $this->guard([
            'active' => true,
            'status' => 'active',
            'answers' => ['ns1.example.com' => ['192.0.2.10', '192.0.2.20']],
        ]);
        $decision = $this->command($guard)->decide($this->redundantConfig());

        $this->assertFalse($decision['run']);
        $this->assertSame('active_unknown', $decision['status']);
        $this->assertStringContainsString('exactly one configured node', $decision['reason']);
    }

    public function test_floating_dns_pointing_to_an_unknown_server_fails_closed(): void
    {
        $guard = $this->guard([
            'active' => false,
            'status' => 'standby',
            'answers' => ['ns1.example.com' => ['192.0.2.99']],
        ]);
        $decision = $this->command($guard)->decide($this->redundantConfig());

        $this->assertFalse($decision['run']);
        $this->assertSame('active_unknown', $decision['status']);
    }

    private function command(AuthoritativeDnsActiveNodeGuard $guard): object
    {
        return new class($guard) extends RenewNginxCertificate
        {
            public function decide(array $config): array
            {
                return $this->renewalDecision($config);
            }

            protected function resolveHostIps(string $hostname): array
            {
                return $hostname === 'pbx1.example.com'
                    ? ['192.0.2.10']
                    : ['192.0.2.20'];
            }
        };
    }

    private function guard(array $result): AuthoritativeDnsActiveNodeGuard
    {
        return new class($result) extends AuthoritativeDnsActiveNodeGuard
        {
            public int $calls = 0;

            public ?string $fqdn = null;

            public array $nodeIps = [];

            public function __construct(private array $result) {}

            public function canExecute($esl = null, ?string $fqdn = null, ?array $nodeIps = null): array
            {
                $this->calls++;
                $this->fqdn = $fqdn;
                $this->nodeIps = $nodeIps ?? [];

                return $this->result;
            }
        };
    }

    private function redundantConfig(): array
    {
        return [
            'MODE' => 'redundant',
            'FLOATING_HOST' => 'portal.example.com',
            'LOCAL_HOST' => 'pbx1.example.com',
            'PEER_HOST' => 'pbx2.example.com',
        ];
    }
}
