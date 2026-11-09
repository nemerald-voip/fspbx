<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\LetsEncryptService;
use App\Services\ScheduledAnnouncements\AuthoritativeDnsActiveNodeGuard;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\TestCase;

class CertificateRenewalOwnershipTest extends TestCase
{
    private Application $app;
    private Container $previousContainer;
    private $previousFacadeApplication;
    private array $replies = [];
    private array $queries = [];
    private string $interfaces = '172.16.10.4';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository([]));
        $this->app->instance('cache', new CacheRepository(new ArrayStore()));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        Http::preventStrayRequests();
        Process::fake(function ($process) {
            if ($process->command === ['which', 'dig']) {
                return Process::result(output: '/usr/bin/dig');
            }
            if ($process->command === ['hostname', '-I']) {
                return Process::result(output: $this->interfaces);
            }
            [$name, $type, $server] = array_slice($process->command, -3);
            $key = substr($server, 1).' '.$name.' '.$type;
            $this->queries[] = $key;

            return isset($this->replies[$key])
                ? Process::result(output: $this->replies[$key])
                : Process::result(exitCode: 9, errorOutput: 'Timed out');
        });
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_direct_dns_identifies_active_and_standby_nodes(): void
    {
        $this->addresses('cloud.example.com', '192.0.2.10');
        $guard = $this->guard();

        $this->assertSame('active', $guard->canExecute(null, 'cloud.example.com', ['192.0.2.10'])['status']);
        $this->assertSame('standby', $guard->canExecute(null, 'cloud.example.com', ['192.0.2.20'])['status']);
    }

    public function test_external_cname_chain_uses_each_targets_authorities_and_observes_failover(): void
    {
        $this->trafficManagerChain();
        $guard = $this->guard();

        $active = $guard->canExecute(null, 'cloud.example.com', ['192.0.2.10']);
        $this->assertTrue($active['active']);
        $this->assertSame(['ns1.example.com' => ['192.0.2.10'], 'ns2.example.com' => ['192.0.2.10']], $active['answers']);
        $this->assertContains('ns1.provider.net profile.provider.net A', $this->queries);
        $this->assertSame(1, array_count_values($this->queries)['ns1.provider.net profile.provider.net A']);

        // A later decision must not reuse the prior DNS ownership answer.
        $this->addresses('pbx1.example.com', '192.0.2.20');
        $this->assertSame('standby', $guard->canExecute(null, 'cloud.example.com', ['192.0.2.10'])['status']);
        $this->assertTrue($guard->canExecute(null, 'cloud.example.com', ['192.0.2.20'])['active']);
    }

    public function test_cname_target_authorities_must_agree(): void
    {
        $this->trafficManagerChain();
        $this->reply('ns2.example.com', 'pbx1.example.com', 'A', ['A 192.0.2.20']);

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertFalse($result['active']);
        $this->assertSame('active_unknown', $result['status']);
        $this->assertStringContainsString('pbx1.example.com', $result['reason']);
        $this->assertSame('CNAME target authorities disagreed.', $result['lookup_failures']['pbx1.example.com']['CNAME']);
    }

    public function test_nxdomain_from_one_authority_is_not_ignored(): void
    {
        $this->trafficManagerChain();
        foreach (['A', 'AAAA'] as $type) {
            $this->reply('ns2.example.com', 'cloud.example.com', $type, [], 'NXDOMAIN');
        }

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertFalse($result['active']);
        $this->assertSame('Authoritative nameservers disagreed.', $result['reason']);
    }

    public function test_cname_loop_fails_closed_with_bounded_queries(): void
    {
        $this->alias('cloud.example.com', 'profile.provider.net', ['ns1.example.com', 'ns2.example.com']);
        $this->alias('profile.provider.net', 'cloud.example.com', ['ns1.provider.net', 'ns2.provider.net']);

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertSame('active_unknown', $result['status']);
        $this->assertStringContainsString('loop', $result['lookup_failures']['cloud.example.com']['CNAME']);
        $this->assertLessThanOrEqual(8, count($this->queries));
    }

    public function test_cname_chain_length_is_bounded(): void
    {
        $this->alias('cloud.example.com', 'hop1.provider.net', ['ns1.example.com', 'ns2.example.com']);
        for ($i = 1; $i <= 10; $i++) {
            $this->alias('hop'.$i.'.provider.net', 'hop'.($i + 1).'.provider.net', ['ns1.provider.net', 'ns2.provider.net']);
        }

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertSame('active_unknown', $result['status']);
        $this->assertLessThanOrEqual(32, count($this->queries));
    }

    public function test_missing_cname_target_authorities_fail_closed(): void
    {
        $this->alias('cloud.example.com', 'unknown.unconfigured.test', ['ns1.example.com', 'ns2.example.com']);

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertSame('active_unknown', $result['status']);
        $this->assertSame('Unable to discover CNAME target authorities.', $result['lookup_failures']['unknown.unconfigured.test']['CNAME']);
    }

    public function test_conflicting_ipv4_and_ipv6_cname_targets_fail_closed(): void
    {
        foreach (['ns1.example.com', 'ns2.example.com'] as $server) {
            $this->reply($server, 'cloud.example.com', 'A', ['CNAME pbx1.example.com.']);
            $this->reply($server, 'cloud.example.com', 'AAAA', ['CNAME pbx2.example.com.']);
        }

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertSame('active_unknown', $result['status']);
    }

    public function test_included_cname_target_addresses_do_not_replace_authoritative_validation(): void
    {
        $this->trafficManagerChain();
        foreach (['ns1.example.com', 'ns2.example.com'] as $server) {
            $this->replies[$server.' cloud.example.com A'] .= "profile.provider.net. 60 IN A 192.0.2.99\n";
        }

        $this->assertTrue($this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10'])['active']);
    }

    public function test_dns_pointing_at_multiple_nodes_does_not_authorize_both(): void
    {
        foreach (['ns1.example.com', 'ns2.example.com'] as $server) {
            $this->reply($server, 'cloud.example.com', 'A', ['A 192.0.2.10', 'A 192.0.2.20']);
            $this->reply($server, 'cloud.example.com', 'AAAA');
        }

        foreach (['192.0.2.10', '192.0.2.20'] as $nodeIp) {
            $result = $this->guard()->canExecute(null, 'cloud.example.com', [$nodeIp]);
            $this->assertFalse($result['active']);
            $this->assertSame('active_unknown', $result['status']);
        }
    }

    public function test_non_authoritative_and_servfail_answers_cannot_authorize_a_node(): void
    {
        $this->reply('ns1.example.com', 'cloud.example.com', 'A', ['A 192.0.2.10'], 'NOERROR', false);
        $this->reply('ns2.example.com', 'cloud.example.com', 'A', ['A 192.0.2.10'], 'SERVFAIL');

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertFalse($result['active']);
        $this->assertSame('active_unknown', $result['status']);
        $this->assertCount(2, $result['lookup_failures']);
    }

    public function test_unavailable_authority_does_not_override_an_available_authoritative_answer(): void
    {
        $this->reply('ns1.example.com', 'cloud.example.com', 'A', ['A 192.0.2.10']);
        $this->reply('ns1.example.com', 'cloud.example.com', 'AAAA');

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['192.0.2.10']);

        $this->assertTrue($result['active']);
        $this->assertArrayHasKey('ns2.example.com', $result['lookup_failures']);
    }

    public function test_nat_node_discovers_public_address_and_excludes_its_own_peer_hostname(): void
    {
        Http::fake(['*' => Http::response('192.0.2.10')]);
        $this->app->instance(AuthoritativeDnsActiveNodeGuard::class, $this->guard());
        $service = $this->service();

        $this->assertSame(['172.16.10.4', '192.0.2.10'], $service->localIps());
        $this->assertSame(['https://pbx2.example.com'], $service->peerHosts());
        Http::assertSentCount(3);
    }

    public function test_direct_public_node_does_not_need_external_address_discovery(): void
    {
        $this->interfaces = '192.0.2.10';

        $this->assertSame(['https://pbx2.example.com'], $this->service()->peerHosts());
        Http::assertNothingSent();
    }

    public function test_ipv6_only_dns_can_identify_the_active_node(): void
    {
        foreach (['ns1.example.com', 'ns2.example.com'] as $server) {
            $this->reply($server, 'cloud.example.com', 'A');
            $this->reply($server, 'cloud.example.com', 'AAAA', ['AAAA 2606:4700:4700::1111']);
        }

        $result = $this->guard()->canExecute(null, 'cloud.example.com', ['2606:4700:4700::1111']);

        $this->assertTrue($result['active']);
    }

    public function test_failed_or_invalid_public_address_discovery_keeps_ownership_unknown(): void
    {
        Http::fake([
            'https://api.ipify.org' => Http::response('192.0.2.10', 503),
            'https://checkip.amazonaws.com' => Http::response('127.0.0.1'),
            'https://ifconfig.me/ip' => Http::response('<html>error</html>'),
        ]);
        $this->app->instance(AuthoritativeDnsActiveNodeGuard::class, $this->guard());

        $ips = $this->service()->localIps();
        $result = $this->guard()->canExecute(null, 'cloud.example.com', $ips);

        $this->assertSame(['172.16.10.4'], $ips);
        $this->assertFalse($result['active']);
        $this->assertSame('No local or external node IP addresses could be determined.', $result['reason']);
        $this->assertSame([], $this->queries);
    }

    private function guard(): AuthoritativeDnsActiveNodeGuard
    {
        return new class extends AuthoritativeDnsActiveNodeGuard {
            protected function setting(string $subcategory): ?string
            {
                return $subcategory === 'scheduled_announcements_authoritative_zone' ? 'example.com' : null;
            }

            protected function dnsRecords(string $hostname, int $type): array
            {
                $zones = [
                    'example.com' => ['ns1.example.com', 'ns2.example.com'],
                    'provider.net' => ['ns1.provider.net', 'ns2.provider.net'],
                ];

                return array_map(fn ($target) => ['target' => $target], $zones[$hostname] ?? []);
            }
        };
    }

    private function service(): LetsEncryptService
    {
        return new class($this->createMock(FreeswitchEslService::class)) extends LetsEncryptService {
            public function config(): array
            {
                return ['domain' => 'cloud.example.com pbx1.example.com pbx2.example.com'];
            }

            protected function resolveHostIps(string $hostname): array
            {
                return match ($hostname) {
                    'pbx1.example.com' => ['192.0.2.10'],
                    'pbx2.example.com' => ['192.0.2.20'],
                    default => [],
                };
            }
        };
    }

    private function trafficManagerChain(): void
    {
        $this->alias('cloud.example.com', 'profile.provider.net', ['ns1.example.com', 'ns2.example.com']);
        $this->alias('profile.provider.net', 'pbx1.example.com', ['ns1.provider.net', 'ns2.provider.net']);
        $this->addresses('pbx1.example.com', '192.0.2.10');
    }

    private function alias(string $name, string $target, array $servers): void
    {
        foreach ($servers as $server) {
            foreach (['A', 'AAAA'] as $type) {
                $this->reply($server, $name, $type, ['CNAME '.$target.'.']);
            }
        }
    }

    private function addresses(string $name, string $ip): void
    {
        foreach (['ns1.example.com', 'ns2.example.com'] as $server) {
            $this->reply($server, $name, 'A', ['A '.$ip]);
            $this->reply($server, $name, 'AAAA');
        }
    }

    private function reply(string $server, string $name, string $type, array $records = [], string $status = 'NOERROR', bool $authoritative = true): void
    {
        $this->replies[$server.' '.$name.' '.$type] = ';; ->>HEADER<<- opcode: QUERY, status: '.$status.", id: 123\n"
            .';; flags: qr '.($authoritative ? 'aa ' : '')."rd; QUERY: 1;\n"
            .implode('', array_map(fn ($record) => $name.'. 60 IN '.$record."\n", $records));
    }
}
