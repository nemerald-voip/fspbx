<?php

namespace Tests\Unit;

use App\Console\Commands\RenewNginxCertificate;
use App\Services\NginxCertificateVerifier;
use App\Services\ScheduledAnnouncements\AuthoritativeDnsActiveNodeGuard;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class RenewNginxCertificateNotificationTest extends TestCase
{
    private Application $app;
    private $previousContainer;
    private $previousFacadeApplication;
    private ArrayTransport $transport;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository(['mail' => ['from' => ['address' => 'ops@example.test']]]));
        $this->app->instance('log', new NullLogger());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->transport = new ArrayTransport();
        $mailer = new Mailer('test', $this->createMock(Factory::class), $this->transport);
        $mailer->alwaysFrom('ops@example.test');
        Mail::swap($mailer);
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_deployment_failure_emails_the_configured_from_address(): void
    {
        $command = $this->command();
        $command->renewalFailure = new RuntimeException('nginx: command not found');
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->never())->method('verify');

        $this->assertSame(1, $command->handle($verifier));
        $this->assertCount(1, $this->transport->messages());
        $email = $this->transport->messages()->first()->getOriginalMessage();
        $this->assertSame('ops@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('ops@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Nginx certificate renewal/deployment FAILED: portal.example.test', $email->getSubject());
        $this->assertStringContainsString('nginx: command not found', $email->getTextBody());
        $this->assertStringContainsString('Peer: pbx2.example.test', $email->getTextBody());
        $this->assertStringContainsString('Server: '.gethostname(), $email->getTextBody());
    }

    public function test_a_successful_deployment_with_a_stale_live_certificate_still_fails_and_emails(): void
    {
        $command = $this->command();
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->once())->method('verify')->with($command->configuration)
            ->willThrowException(new RuntimeException('pbx2.example.test: served certificate does not match.'));

        $this->assertSame(1, $command->handle($verifier));
        $this->assertTrue($command->renewalRan);
        $this->assertCount(1, $this->transport->messages());
        $this->assertStringContainsString('served certificate does not match', $this->transport->messages()->first()->getOriginalMessage()->getTextBody());
        $this->assertStringNotContainsString('check completed', $this->output->fetch());
    }

    public function test_successful_checks_always_verify_even_when_no_new_certificate_was_issued(): void
    {
        $command = $this->command();
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->once())->method('verify')->with($command->configuration);

        $this->assertSame(0, $command->handle($verifier));
        $this->assertTrue($command->renewalRan);
        $this->assertCount(0, $this->transport->messages());
        $this->assertStringContainsString('check completed', $this->output->fetch());
    }

    /** @dataProvider normalSkips */
    public function test_normal_skips_do_not_verify_or_email(string $status): void
    {
        $command = $this->command();
        $command->decision = ['run' => false, 'status' => $status, 'reason' => 'Skipped.'];
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->never())->method('verify');

        $this->assertSame(0, $command->handle($verifier));
        $this->assertFalse($command->renewalRan);
        $this->assertCount(0, $this->transport->messages());
    }

    public static function normalSkips(): array
    {
        return [['standby'], ['disabled']];
    }

    public function test_unknown_dns_ownership_sends_an_alert(): void
    {
        $command = $this->command();
        $command->decision = ['run' => false, 'status' => 'active_unknown', 'reason' => 'Authoritative nameservers disagreed.'];
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->never())->method('verify');

        $this->assertSame(1, $command->handle($verifier));
        $this->assertFalse($command->renewalRan);
        $this->assertCount(1, $this->transport->messages());
        $this->assertStringContainsString('Authoritative nameservers disagreed', $this->transport->messages()->first()->getOriginalMessage()->getTextBody());
    }

    public function test_missing_deployment_configuration_can_still_email(): void
    {
        $command = $this->command();
        $command->configFailure = new RuntimeException('Deployment configuration missing.');
        $verifier = $this->createMock(NginxCertificateVerifier::class);
        $verifier->expects($this->never())->method('verify');

        $this->assertSame(1, $command->handle($verifier));
        $this->assertCount(1, $this->transport->messages());
        $this->assertStringContainsString('Deployment configuration missing', $this->transport->messages()->first()->getOriginalMessage()->getTextBody());
    }

    public function test_a_missing_recipient_preserves_the_original_failure(): void
    {
        config(['mail.from.address' => '']);
        $command = $this->command();
        $command->renewalFailure = new RuntimeException('Deployment failed.');

        $this->assertSame(1, $command->handle($this->createMock(NginxCertificateVerifier::class)));
        $this->assertCount(0, $this->transport->messages());
        $this->assertStringContainsString('MAIL_FROM_ADDRESS is not configured', $this->output->fetch());
    }

    public function test_mail_transport_errors_do_not_hide_the_certificate_failure(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->once())->method('raw')->willThrowException(new RuntimeException('SMTP unavailable.'));
        Mail::swap($mailer);
        $command = $this->command();
        $command->renewalFailure = new RuntimeException('Certificate deployment failed.');

        $this->assertSame(1, $command->handle($this->createMock(NginxCertificateVerifier::class)));
        $output = $this->output->fetch();
        $this->assertStringContainsString('Certificate deployment failed', $output);
        $this->assertStringContainsString('SMTP unavailable', $output);
    }

    private function command(): RenewNginxCertificate
    {
        $command = new class($this->createMock(AuthoritativeDnsActiveNodeGuard::class)) extends RenewNginxCertificate
        {
            public array $configuration = [
                'MODE' => 'redundant',
                'FLOATING_HOST' => 'portal.example.test',
                'LOCAL_HOST' => 'pbx1.example.test',
                'PEER_HOST' => 'pbx2.example.test',
            ];
            public array $decision = ['run' => true, 'status' => 'active', 'reason' => 'Running renewal.'];
            public ?Throwable $renewalFailure = null;
            public ?Throwable $configFailure = null;
            public bool $renewalRan = false;

            protected function isRoot(): bool
            {
                return true;
            }

            protected function readDeploymentConfig(): array
            {
                if ($this->configFailure) {
                    throw $this->configFailure;
                }

                return $this->configuration;
            }

            protected function renewalDecision(array $config): array
            {
                return $this->decision;
            }

            protected function runDehydrated(): void
            {
                $this->renewalRan = true;
                if ($this->renewalFailure) {
                    throw $this->renewalFailure;
                }
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $this->output));

        return $command;
    }
}
