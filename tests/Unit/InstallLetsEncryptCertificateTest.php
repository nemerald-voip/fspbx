<?php

namespace Tests\Unit;

use App\Console\Commands\InstallLetsEncryptCertificate;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class InstallLetsEncryptCertificateTest extends TestCase
{
    public function test_single_server_flow_never_invokes_peer_operations(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public array $events = [];

            protected function isRoot(): bool { return true; }
            protected function promptDeploymentMode(): string { return 'single'; }
            protected function readDeploymentConfig(string $path): array { return []; }
            protected function promptSingleServerTopology(): array
            {
                return [
                    'mode' => 'single',
                    'domains' => ['portal.example.com'],
                    'floating_host' => 'portal.example.com',
                    'local_host' => 'portal.example.com',
                ];
            }
            protected function installLocalPrerequisites(string $mode): void { $this->events[] = 'local-prerequisites'; }
            protected function configureLocalNode(string $mode, array $topology): void { $this->events[] = 'local-config'; }
            protected function verifyHttpChallenges(array $topology): void { $this->events[] = 'preflight'; }
            protected function runProcess(array $command, int $timeout = 60, bool $stream = true): string
            {
                $this->events[] = implode(' ', $command);

                return '';
            }
            protected function certificateFilesInstalled(): bool { return true; }
            protected function installLocalNginxConfiguration(): void { $this->events[] = 'local-nginx'; }
            protected function installLocalRenewalCron(): void { $this->events[] = 'local-cron'; }
            protected function verifyReciprocalSsh(array $topology): void { throw new \LogicException('SSH must not run.'); }
            protected function installRemotePrerequisites(array $topology): void { throw new \LogicException('Remote install must not run.'); }
            protected function configureRemoteNode(array $topology): void { throw new \LogicException('Remote config must not run.'); }
            protected function installRemoteNginxConfiguration(array $topology): void { throw new \LogicException('Remote Nginx must not run.'); }
            protected function installRemoteRenewalCron(array $topology): void { throw new \LogicException('Remote cron must not run.'); }
        };

        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame([
            'local-prerequisites',
            'local-config',
            'preflight',
            'dehydrated --register --accept-terms',
            'dehydrated -c',
            'local-nginx',
            'local-cron',
        ], $command->events);
    }

    public function test_switching_from_redundant_to_single_disables_only_the_former_peer(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public array $events = [];

            protected function isRoot(): bool { return true; }
            protected function promptDeploymentMode(): string { return 'single'; }
            protected function readDeploymentConfig(string $path): array
            {
                return [
                    'MODE' => 'redundant',
                    'PEER_SSH_TARGET' => 'root@pbx2.example.com',
                    'PEER_SSH_PORT' => '22',
                ];
            }
            protected function promptSingleServerTopology(): array
            {
                return [
                    'mode' => 'single',
                    'domains' => ['portal.example.com'],
                    'floating_host' => 'portal.example.com',
                    'local_host' => 'portal.example.com',
                ];
            }
            protected function disableFormerPeer(array $existing): void { $this->events[] = 'former-peer-disabled'; }
            protected function installLocalPrerequisites(string $mode): void {}
            protected function configureLocalNode(string $mode, array $topology): void {}
            protected function verifyHttpChallenges(array $topology): void {}
            protected function runProcess(array $command, int $timeout = 60, bool $stream = true): string { return ''; }
            protected function certificateFilesInstalled(): bool { return true; }
            protected function installLocalNginxConfiguration(): void { $this->events[] = 'local-certificate-preserved'; }
            protected function installLocalRenewalCron(): void {}
            protected function configureRemoteNode(array $topology): void { throw new \LogicException('A new peer must not be configured.'); }
        };

        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));
        $this->assertSame(['former-peer-disabled', 'local-certificate-preserved'], $command->events);
    }

    public function test_redundant_flow_configures_and_renews_both_nodes(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public array $events = [];

            protected function isRoot(): bool { return true; }
            protected function promptDeploymentMode(): string { return 'redundant'; }
            protected function readDeploymentConfig(string $path): array { return []; }
            protected function promptRedundantTopology(): array
            {
                return [
                    'mode' => 'redundant',
                    'domains' => ['portal.example.com', 'pbx1.example.com', 'pbx2.example.com'],
                    'floating_host' => 'portal.example.com',
                    'local_host' => 'pbx1.example.com',
                    'peer_host' => 'pbx2.example.com',
                    'peer_ssh_target' => 'root@pbx2.example.com',
                    'peer_ssh_port' => 22,
                ];
            }
            protected function displayTopology(array $topology): void {}
            protected function installLocalPrerequisites(string $mode): void { $this->events[] = 'local-prerequisites'; }
            protected function verifyReciprocalSsh(array $topology): void { $this->events[] = 'reciprocal-ssh'; }
            protected function installRemotePrerequisites(array $topology): void { $this->events[] = 'peer-prerequisites'; }
            protected function configureLocalNode(string $mode, array $topology): void { $this->events[] = 'local-config'; }
            protected function configureRemoteNode(array $topology): void { $this->events[] = 'peer-config'; }
            protected function verifyHttpChallenges(array $topology): void { $this->events[] = 'preflight'; }
            protected function runProcess(array $command, int $timeout = 60, bool $stream = true): string
            {
                $this->events[] = implode(' ', $command);

                return '';
            }
            protected function runRemote(array $topology, array $command, int $timeout = 60, bool $stream = true): string
            {
                $this->events[] = 'peer:'.implode(' ', $command);

                return '';
            }
            protected function certificateFilesInstalled(): bool { return true; }
            protected function installLocalNginxConfiguration(): void { $this->events[] = 'local-nginx'; }
            protected function installRemoteNginxConfiguration(array $topology): void { $this->events[] = 'peer-nginx'; }
            protected function installLocalRenewalCron(): void { $this->events[] = 'local-cron'; }
            protected function installRemoteRenewalCron(array $topology): void { $this->events[] = 'peer-cron'; }
        };

        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame([
            'local-prerequisites',
            'reciprocal-ssh',
            'peer-prerequisites',
            'local-config',
            'peer-config',
            'preflight',
            'dehydrated --register --accept-terms',
            'peer:dehydrated --register --accept-terms',
            'dehydrated -c',
            'local-nginx',
            'peer-nginx',
            'local-cron',
            'peer-cron',
        ], $command->events);
    }

    public function test_redundant_flow_explains_how_to_fix_missing_reverse_ssh(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            private int $remoteCalls = 0;

            protected function isRoot(): bool { return true; }
            protected function promptDeploymentMode(): string { return 'redundant'; }
            protected function readDeploymentConfig(string $path): array { return []; }
            protected function promptRedundantTopology(): array
            {
                return [
                    'mode' => 'redundant',
                    'domains' => ['portal.example.com', 'pbx1.example.com', 'pbx2.example.com'],
                    'floating_host' => 'portal.example.com',
                    'local_host' => 'pbx1.example.com',
                    'peer_host' => 'pbx2.example.com',
                    'peer_ssh_target' => 'root@pbx2.example.com',
                    'peer_ssh_port' => 22,
                ];
            }
            protected function displayTopology(array $topology): void {}
            protected function installLocalPrerequisites(string $mode): void {}
            protected function runRemote(array $topology, array $command, int $timeout = 60, bool $stream = true): string
            {
                $this->remoteCalls++;
                if ($this->remoteCalls === 2) {
                    throw new \RuntimeException('Permission denied (publickey).');
                }

                return '';
            }
        };

        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString(
            'ssh-copy-id -p 22 root@pbx1.example.com',
            $tester->getDisplay()
        );
        $this->assertStringContainsString(
            'ssh -o BatchMode=yes -p 22 root@pbx1.example.com true',
            $tester->getDisplay()
        );
    }

    public function test_deployment_type_requires_an_explicit_selection(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function mode(string $selection): ?string
            {
                return $this->deploymentModeForSelection($selection);
            }
        };

        $this->assertSame('single', $command->mode('1'));
        $this->assertSame('redundant', $command->mode('2'));
        $this->assertNull($command->mode(''));
        $this->assertNull($command->mode('single'));
    }

    public function test_it_parses_multiple_certificate_hostnames(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function domains(string $value): array
            {
                return $this->parseDomains($value);
            }
        };

        $this->assertSame(
            ['portal.example.com', 'pbx1.example.com'],
            $command->domains(" Portal.Example.com, pbx1.example.com\nportal.example.com ")
        );
    }

    public function test_it_validates_each_certificate_hostname(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function valid(string $domain): bool
            {
                return $this->isValidDomain($domain);
            }
        };

        $this->assertTrue($command->valid('portal.example.com'));
        $this->assertTrue($command->valid('pbx1.example.com'));
        $this->assertFalse($command->valid('portal.example.com/path'));
        $this->assertFalse($command->valid('localhost'));
    }

    public function test_it_renders_reciprocal_safe_redundant_configuration(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function config(array $topology): string
            {
                return $this->deploymentConfigContents('redundant', $topology);
            }

            public function validTarget(string $target): bool
            {
                return $this->isValidSshTarget($target);
            }
        };

        $config = $command->config([
            'floating_host' => 'portal.example.com',
            'local_host' => 'pbx1.example.com',
            'peer_host' => 'pbx2.example.com',
            'peer_ssh_target' => 'root@pbx2.example.com',
            'peer_ssh_port' => 2222,
        ]);

        $this->assertStringContainsString("MODE='redundant'", $config);
        $this->assertStringContainsString("FLOATING_HOST='portal.example.com'", $config);
        $this->assertStringContainsString("LOCAL_HOST='pbx1.example.com'", $config);
        $this->assertStringContainsString("PEER_SSH_TARGET='root@pbx2.example.com'", $config);
        $this->assertStringContainsString("PEER_SSH_PORT='2222'", $config);
        $this->assertTrue($command->validTarget('root@pbx2.example.com'));
        $this->assertTrue($command->validTarget('root@192.0.2.10'));
        $this->assertFalse($command->validTarget('admin@pbx2.example.com'));
        $this->assertFalse($command->validTarget('root@pbx2.example.com;reboot'));
    }

    public function test_it_replaces_only_the_certificate_renewal_cron(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function crontab(string $existing, bool $add): string
            {
                return $this->updatedCrontab($existing, $add);
            }
        };

        $existing = "15 2 * * * /usr/local/bin/backup\n"
            ."0 3 * * * dehydrated -c && nginx -t && systemctl reload nginx\n";
        $updated = $command->crontab($existing, true);

        $this->assertStringContainsString('15 2 * * * /usr/local/bin/backup', $updated);
        $this->assertStringContainsString('app:renew-nginx-certificate', $updated);
        $this->assertStringNotContainsString('dehydrated -c && nginx', $updated);
        $this->assertSame(1, substr_count($command->crontab($updated, true), 'app:renew-nginx-certificate'));
        $this->assertStringNotContainsString('app:renew-nginx-certificate', $command->crontab($updated, false));
    }

    public function test_it_replaces_existing_dehydrated_certificate_paths(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function replacePaths(string $config, string $certificate, string $key): string
            {
                return $this->replaceCertificateDirectives($config, $certificate, $key);
            }
        };

        $config = <<<'NGINX'
server {
    listen 443 ssl;
    ssl_certificate /etc/dehydrated/certs/old.example.com/fullchain.pem;
    ssl_certificate_key /etc/dehydrated/certs/old.example.com/privkey.pem;
}
NGINX;

        $updated = $command->replacePaths(
            $config,
            '/etc/nginx/ssl/fullchain.pem',
            '/etc/nginx/ssl/private/privkey.pem'
        );

        $this->assertStringContainsString(
            'ssl_certificate /etc/nginx/ssl/fullchain.pem;',
            $updated
        );
        $this->assertStringContainsString(
            'ssl_certificate_key /etc/nginx/ssl/private/privkey.pem;',
            $updated
        );
        $this->assertStringNotContainsString('old.example.com', $updated);
    }

    public function test_it_fails_when_the_site_has_no_certificate_directives(): void
    {
        $command = new class extends InstallLetsEncryptCertificate
        {
            public function replacePaths(string $config, string $certificate, string $key): string
            {
                return $this->replaceCertificateDirectives($config, $certificate, $key);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not contain certificate directives');

        $command->replacePaths(
            "server {\n    listen 80;\n}\n",
            '/tmp/fullchain.pem',
            '/tmp/privkey.pem'
        );
    }
}
