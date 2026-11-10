<?php

namespace Tests\Unit;

use App\Services\NginxCertificateVerifier;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class NginxCertificateVerifierTest extends TestCase
{
    private string $directory;
    private array $servers = [];
    private array $config = [
        'MODE' => 'redundant',
        'FLOATING_HOST' => 'portal.example.test',
        'LOCAL_HOST' => 'pbx1.example.test',
        'PEER_HOST' => 'pbx2.example.test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/fspbx-cert-verify-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_verifies_floating_name_both_nodes_and_additional_sans(): void
    {
        $pem = $this->certificate(['portal.example.test', 'pbx1.example.test', 'pbx2.example.test', 'admin.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = array_fill_keys(['portal.example.test', 'pbx1.example.test', 'pbx2.example.test', 'admin.example.test'], [$pem]);

        $verifier->verify($this->config);

        $this->assertSame([
            'portal.example.test', 'pbx1.example.test', 'pbx2.example.test', 'admin.example.test',
            'pbx1.example.test|portal.example.test', 'pbx2.example.test|portal.example.test',
        ], $verifier->calls);
    }

    public function test_a_peer_serving_a_different_unexpired_certificate_is_a_failure(): void
    {
        $pem = $this->certificate(['portal.example.test', 'pbx1.example.test', 'pbx2.example.test']);
        $old = $this->certificate(['portal.example.test', 'pbx1.example.test', 'pbx2.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = ['portal.example.test' => [$pem], 'pbx1.example.test' => [$pem], 'pbx2.example.test' => [$old]];

        try {
            $verifier->verify($this->config);
            $this->fail('A stale certificate must fail even while it is still valid.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('pbx2.example.test: Served certificate does not match', $exception->getMessage());
            $this->assertStringContainsString(openssl_x509_fingerprint($old, 'sha256'), $exception->getMessage());
        }
        $this->assertSame(3, array_count_values($verifier->calls)['pbx2.example.test']);
    }

    public function test_retries_a_reload_that_briefly_serves_the_previous_certificate(): void
    {
        $pem = $this->certificate(['portal.example.test']);
        $old = $this->certificate(['portal.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = ['portal.example.test' => [$old, $pem]];

        $verifier->verify(['MODE' => 'single', 'FLOATING_HOST' => 'portal.example.test']);

        $this->assertSame(['portal.example.test', 'portal.example.test'], $verifier->calls);
    }

    public function test_peer_must_serve_the_floating_name_correctly_before_failover(): void
    {
        $pem = $this->certificate(['portal.example.test', 'pbx1.example.test', 'pbx2.example.test']);
        $old = $this->certificate(['portal.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = [
            'portal.example.test' => [$pem], 'pbx1.example.test' => [$pem], 'pbx2.example.test' => [$pem],
            'pbx2.example.test|portal.example.test' => [$old],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pbx2.example.test (SNI portal.example.test): Served certificate does not match');
        $verifier->verify($this->config);
    }

    public function test_reports_each_unreachable_endpoint(): void
    {
        $pem = $this->certificate(['portal.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = [
            'portal.example.test' => [$pem],
            'pbx1.example.test' => [new RuntimeException('Connection refused.')],
            'pbx2.example.test' => [new RuntimeException('TLS handshake timed out.')],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("pbx1.example.test: Connection refused.\npbx2.example.test: TLS handshake timed out.");
        $verifier->verify($this->config);
    }

    public function test_an_expired_stored_certificate_cannot_pass_even_if_it_matches(): void
    {
        $pem = $this->certificate(['portal.example.test'], 0);
        $verifier = $this->verifier($pem);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The certificate stored by Dehydrated expired');
        $verifier->verify($this->config);
    }

    public function test_an_expired_served_certificate_fails(): void
    {
        $pem = $this->certificate(['portal.example.test']);
        $verifier = $this->verifier($pem);
        $verifier->responses = ['portal.example.test' => [$this->certificate(['portal.example.test'], 0)]];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The certificate served by portal.example.test expired');
        $verifier->verify(['MODE' => 'single', 'FLOATING_HOST' => 'portal.example.test']);
    }

    public function test_missing_expected_certificate_fails_instead_of_trusting_the_installed_copy(): void
    {
        $verifier = $this->verifier(null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read the expected certificate');
        $verifier->verify($this->config);
    }

    public function test_invalid_expected_certificate_fails(): void
    {
        $verifier = $this->verifier('not a certificate');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be parsed');
        $verifier->verify($this->config);
    }

    public function test_real_tls_probe_accepts_a_trusted_matching_hostname(): void
    {
        [$verifier, $pem] = $this->tlsVerifier('localhost', true);
        $served = $verifier->probe('localhost');
        $this->assertSame(openssl_x509_fingerprint($pem, 'sha256'), openssl_x509_fingerprint($served, 'sha256'));
    }

    public function test_real_tls_probe_validates_the_requested_name_when_connecting_to_a_different_address(): void
    {
        [$verifier, $pem] = $this->tlsVerifier('localhost', true);
        $served = $verifier->probe('127.0.0.1', 'localhost');
        $this->assertSame(openssl_x509_fingerprint($pem, 'sha256'), openssl_x509_fingerprint($served, 'sha256'));
    }

    public function test_real_tls_probe_rejects_an_untrusted_certificate(): void
    {
        [$verifier] = $this->tlsVerifier('localhost', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate verify failed');
        $verifier->probe('localhost');
    }

    public function test_real_tls_probe_rejects_a_wrong_hostname_even_when_trusted(): void
    {
        [$verifier] = $this->tlsVerifier('wrong.example.test', true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('hostname mismatch');
        $verifier->probe('localhost');
    }

    public function test_real_tls_probe_rejects_an_expired_certificate_even_when_trusted(): void
    {
        [$verifier] = $this->tlsVerifier('localhost', true, 0);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate has expired');
        $verifier->probe('localhost');
    }

    private function verifier(?string $pem): NginxCertificateVerifier
    {
        if ($pem !== null) {
            mkdir($this->directory.'/portal.example.test', 0700);
            file_put_contents($this->directory.'/portal.example.test/fullchain.pem', $pem);
        }

        return new class($this->directory) extends NginxCertificateVerifier
        {
            public array $responses = [];
            public array $calls = [];

            public function __construct(string $path)
            {
                $this->certificatesPath = $path;
            }

            protected function fetchCertificate(string $hostname, string $serverName): string
            {
                $target = $hostname === $serverName ? $hostname : $hostname.'|'.$serverName;
                $this->calls[] = $target;
                $key = array_key_exists($target, $this->responses) ? $target : $hostname;
                $responses = &$this->responses[$key];
                $response = count($responses) > 1 ? array_shift($responses) : $responses[0];
                if ($response instanceof Throwable) {
                    throw $response;
                }

                return $response;
            }

            protected function waitForReload(): void {}
        };
    }

    private function certificate(array $hosts, int $days = 2, ?string $keyPath = null): string
    {
        $config = $this->directory.'/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n[v3]\nsubjectAltName="
            .implode(',', array_map(fn ($host) => 'DNS:'.$host, $hosts))."\n");
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => $hosts[0]], $key, ['config' => $config]);
        $cert = openssl_csr_sign($csr, null, $key, $days, ['config' => $config, 'x509_extensions' => 'v3']);
        $this->assertNotFalse($cert);
        openssl_x509_export($cert, $pem);
        if ($keyPath !== null) {
            openssl_pkey_export_to_file($key, $keyPath);
        }

        return $pem;
    }

    /** Use an isolated loopback TLS server; never contact production hosts. */
    private function tlsVerifier(string $hostname, bool $trusted, int $days = 2): array
    {
        $pem = $this->certificate([$hostname], $days, $this->directory.'/server.key');
        $certPath = $this->directory.'/server.pem';
        file_put_contents($certPath, $pem);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $server = new Process([
            'openssl', 's_server', '-accept', '127.0.0.1:'.$port,
            '-cert', $certPath, '-key', $this->directory.'/server.key', '-www',
        ]);
        $server->start();
        $this->servers[] = $server;
        $ready = $server->waitUntil(fn ($type, $output) => str_contains($output, 'ACCEPT'));
        $this->assertTrue($ready, $server->getErrorOutput());

        $verifier = new class($port, $trusted ? $certPath : '/dev/null', $this->directory) extends NginxCertificateVerifier
        {
            public function __construct(int $port, private string $caFile, private string $caPath)
            {
                $this->port = $port;
            }

            public function probe(string $hostname, ?string $serverName = null): string
            {
                return $this->fetchCertificate($hostname, $serverName ?? $hostname);
            }

            protected function probeProcess(string $hostname, string $serverName): Process
            {
                $process = parent::probeProcess($hostname, $serverName);
                $process->setEnv(['SSL_CERT_FILE' => $this->caFile, 'SSL_CERT_DIR' => $this->caPath]);

                return $process;
            }
        };

        return [$verifier, $pem];
    }
}
