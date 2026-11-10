<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class NginxCertificateVerifier
{
    protected string $certificatesPath = '/etc/dehydrated/certs';

    protected int $attempts = 3;

    protected int $port = 443;

    /** Verify the certificate clients receive, including when issuance was skipped. */
    public function verify(array $config): void
    {
        $primary = $config['FLOATING_HOST'] ?? '';
        $this->assertHostname($primary);
        $path = $this->certificatesPath.'/'.$primary.'/fullchain.pem';
        $pem = @file_get_contents($path);
        if ($pem === false) {
            throw new RuntimeException('Cannot read the expected certificate from '.$path.'.');
        }

        $expected = $this->certificateInfo($pem);
        $this->assertCurrent($expected, 'The certificate stored by Dehydrated');

        $hosts = [$primary, $config['LOCAL_HOST'] ?? $primary];
        if (($config['MODE'] ?? '') === 'redundant') {
            $hosts[] = $config['PEER_HOST'] ?? '';
        }
        foreach (explode(',', $expected['extensions']['subjectAltName'] ?? '') as $san) {
            if (str_starts_with(trim($san), 'DNS:')) {
                $hosts[] = substr(trim($san), 4);
            }
        }

        $targets = [];
        foreach (array_unique($hosts) as $host) {
            $this->assertHostname($host);
            $targets[$host.'|'.$host] = [$host, $host];
        }
        if (($config['MODE'] ?? '') === 'redundant') {
            // Check the floating name on the standby too, before DNS fails over.
            foreach ([$config['LOCAL_HOST'], $config['PEER_HOST']] as $host) {
                $targets[$host.'|'.$primary] = [$host, $primary];
            }
        }

        $errors = [];
        foreach ($targets as [$host, $serverName]) {
            $label = $host === $serverName ? $host : $host.' (SNI '.$serverName.')';
            for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
                try {
                    $served = $this->certificateInfo($this->fetchCertificate($host, $serverName));
                    $this->assertCurrent($served, 'The certificate served by '.$label);
                    if (! hash_equals($expected['fingerprint'], $served['fingerprint'])) {
                        throw new RuntimeException(
                            'Served certificate does not match the certificate stored by Dehydrated.'
                            .' Expected SHA-256: '.$expected['fingerprint']
                            .'; served SHA-256: '.$served['fingerprint']
                            .'; served expiry: '.gmdate('Y-m-d H:i:s \U\T\C', $served['validTo_time_t']).'.'
                        );
                    }

                    break;
                } catch (Throwable $exception) {
                    if ($attempt === $this->attempts) {
                        $errors[] = $label.': '.$exception->getMessage();
                    } else {
                        // A graceful Nginx reload may need a moment to take effect.
                        $this->waitForReload();
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("Nginx HTTPS certificate verification failed:\n".implode("\n", $errors));
        }
    }

    protected function fetchCertificate(string $hostname, string $serverName): string
    {
        $process = $this->probeProcess($hostname, $serverName);
        $process->run();
        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'The TLS connection failed.';
            throw new RuntimeException(mb_strimwidth($error, 0, 2000, '...'));
        }

        if (! preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $process->getOutput(), $match)) {
            throw new RuntimeException('The HTTPS endpoint did not return a certificate.');
        }

        return $match[0];
    }

    protected function probeProcess(string $hostname, string $serverName): Process
    {
        // A separate process bounds DNS, connection and handshake time together.
        // OpenSSL otherwise continues after trust/hostname errors unless told to fail.
        return new Process([
            'openssl', 's_client', '-connect', $hostname.':'.$this->port,
            '-servername', $serverName, '-verify_hostname', $serverName,
            '-verify_return_error', '-showcerts', '-no_ign_eof',
        ], null, null, '', 10);
    }

    protected function waitForReload(): void
    {
        usleep(500000);
    }

    protected function certificateInfo(string $pem): array
    {
        $info = @openssl_x509_parse($pem);
        $fingerprint = @openssl_x509_fingerprint($pem, 'sha256');
        if ($info === false || $fingerprint === false
            || ! isset($info['validFrom_time_t'], $info['validTo_time_t'])) {
            throw new RuntimeException('The certificate could not be parsed.');
        }

        return $info + ['fingerprint' => $fingerprint];
    }

    protected function assertCurrent(array $info, string $label): void
    {
        if ($info['validTo_time_t'] <= time()) {
            throw new RuntimeException($label.' expired at '.gmdate('Y-m-d H:i:s \U\T\C', $info['validTo_time_t']).'.');
        }
        if ($info['validFrom_time_t'] > time()) {
            throw new RuntimeException($label.' is not yet valid.');
        }
    }

    protected function assertHostname(string $hostname): void
    {
        if ($hostname === '' || filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new RuntimeException('A certificate hostname is missing or invalid.');
        }
    }
}
