<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DehydratedFspbxHookTest extends TestCase
{
    private string $directory;

    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = dirname(__DIR__, 2);
        $this->directory = sys_get_temp_dir().'/fspbx-hook-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                foreach (glob($path.'/*') ?: [] as $child) {
                    @unlink($child);
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_single_mode_deploys_locally_without_ssh(): void
    {
        $config = $this->directory.'/deployment.conf';
        $helper = $this->directory.'/deploy-helper';
        $log = $this->directory.'/helper.log';
        file_put_contents($config, "MODE='single'\nWELLKNOWN_DIR='{$this->directory}'\n");
        file_put_contents($helper, "#!/bin/sh\nprintf '%s\\n' \"\$*\" > \"\$FSPBX_HOOK_LOG\"\n");
        chmod($helper, 0755);

        foreach (['key.pem', 'cert.pem', 'fullchain.pem', 'chain.pem'] as $file) {
            file_put_contents($this->directory.'/'.$file, $file);
        }

        $process = $this->hookProcess([
            'deploy_cert',
            'portal.example.com',
            $this->directory.'/key.pem',
            $this->directory.'/cert.pem',
            $this->directory.'/fullchain.pem',
            $this->directory.'/chain.pem',
            '12345',
        ], [
            'FSPBX_CERT_CONFIG' => $config,
            'FSPBX_DEPLOY_HELPER' => $helper,
            'FSPBX_HOOK_LOG' => $log,
            'PATH' => '/nonexistent',
        ]);

        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame(
            $this->directory.'/fullchain.pem '.$this->directory.'/key.pem',
            trim((string) file_get_contents($log))
        );
    }

    public function test_redundant_mode_publishes_the_challenge_to_the_peer(): void
    {
        $bin = $this->directory.'/bin';
        mkdir($bin, 0700);
        $ssh = $bin.'/ssh';
        $log = $this->directory.'/ssh.log';
        $config = $this->directory.'/deployment.conf';
        file_put_contents($config, implode("\n", [
            "MODE='redundant'",
            "PEER_SSH_TARGET='root@pbx2.example.com'",
            "PEER_SSH_PORT='22'",
            "WELLKNOWN_DIR='/var/www/fspbx/public/.well-known/acme-challenge'",
        ])."\n");
        file_put_contents($ssh, <<<'SH'
#!/bin/sh
printf 'ARGS:%s\n' "$*" >> "$FSPBX_HOOK_LOG"
input="$(cat)"
if [ -n "$input" ]; then printf 'INPUT:%s\n' "$input" >> "$FSPBX_HOOK_LOG"; fi
SH);
        chmod($ssh, 0755);

        $process = $this->hookProcess([
            'deploy_challenge',
            'pbx2.example.com',
            'valid_token-123',
            'challenge-value',
        ], [
            'FSPBX_CERT_CONFIG' => $config,
            'FSPBX_HOOK_LOG' => $log,
            'PATH' => $bin.':'.getenv('PATH'),
        ]);

        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $contents = (string) file_get_contents($log);
        $this->assertStringContainsString('root@pbx2.example.com install -d -m 0755', $contents);
        $this->assertStringContainsString('valid_token-123', $contents);
        $this->assertStringContainsString('INPUT:challenge-value', $contents);
    }

    public function test_redundant_mode_fails_when_the_peer_is_unavailable(): void
    {
        $bin = $this->directory.'/bin';
        mkdir($bin, 0700);
        file_put_contents($bin.'/ssh', "#!/bin/sh\nexit 42\n");
        chmod($bin.'/ssh', 0755);

        $config = $this->directory.'/deployment.conf';
        file_put_contents($config, implode("\n", [
            "MODE='redundant'",
            "PEER_SSH_TARGET='root@pbx2.example.com'",
            "PEER_SSH_PORT='22'",
            "WELLKNOWN_DIR='/var/www/fspbx/public/.well-known/acme-challenge'",
        ])."\n");

        $process = $this->hookProcess([
            'deploy_challenge',
            'pbx2.example.com',
            'valid-token',
            'challenge-value',
        ], [
            'FSPBX_CERT_CONFIG' => $config,
            'PATH' => $bin.':'.getenv('PATH'),
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame(42, $process->getExitCode());
    }

    public function test_unchanged_certificate_repairs_the_peer_without_reissuing(): void
    {
        $bin = $this->directory.'/bin';
        $certdir = $this->directory.'/certs/portal.example.com';
        mkdir($bin, 0700);
        mkdir($certdir, 0700, true);
        foreach (['privkey.pem', 'cert.pem', 'fullchain.pem', 'chain.pem'] as $file) {
            file_put_contents($certdir.'/'.$file, $file);
        }

        $config = $this->directory.'/deployment.conf';
        $helper = $this->directory.'/deploy-helper';
        $log = $this->directory.'/sync.log';
        file_put_contents($config, implode("\n", [
            "MODE='redundant'",
            "PEER_SSH_TARGET='root@pbx2.example.com'",
            "PEER_SSH_PORT='22'",
            "WELLKNOWN_DIR='{$this->directory}'",
        ])."\n");
        file_put_contents($helper, "#!/bin/sh\nprintf 'HELPER:%s\\n' \"\$*\" >> \"\$FSPBX_HOOK_LOG\"\n");
        chmod($helper, 0755);
        file_put_contents($bin.'/ssh', "#!/bin/sh\nprintf 'SSH:%s\\n' \"\$*\" >> \"\$FSPBX_HOOK_LOG\"\n");
        chmod($bin.'/ssh', 0755);
        file_put_contents($bin.'/rsync', "#!/bin/sh\nprintf 'RSYNC:%s\\n' \"\$*\" >> \"\$FSPBX_HOOK_LOG\"\n");
        chmod($bin.'/rsync', 0755);

        $process = $this->hookProcess([
            'unchanged_cert',
            'portal.example.com',
            $certdir.'/privkey.pem',
            $certdir.'/cert.pem',
            $certdir.'/fullchain.pem',
            $certdir.'/chain.pem',
        ], [
            'FSPBX_CERT_CONFIG' => $config,
            'FSPBX_DEPLOY_HELPER' => $helper,
            'FSPBX_HOOK_LOG' => $log,
            'PATH' => $bin.':'.getenv('PATH'),
        ]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $contents = (string) file_get_contents($log);
        $this->assertStringContainsString('HELPER:'.$certdir.'/fullchain.pem '.$certdir.'/privkey.pem', $contents);
        $this->assertStringContainsString('RSYNC:', $contents);
        $this->assertStringContainsString('SSH:', $contents);
        $this->assertStringContainsString($helper.' '.$certdir.'/fullchain.pem '.$certdir.'/privkey.pem', $contents);
    }

    public function test_nginx_deployer_rejects_a_mismatched_private_key(): void
    {
        $certificate = $this->directory.'/certificate.pem';
        $certificateKey = $this->directory.'/certificate-key.pem';
        $otherKey = $this->directory.'/other-key.pem';

        $certificateProcess = new Process([
            'openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
            '-subj', '/CN=portal.example.com', '-days', '1',
            '-keyout', $certificateKey, '-out', $certificate,
        ]);
        $certificateProcess->run();
        $this->assertTrue($certificateProcess->isSuccessful(), $certificateProcess->getErrorOutput());

        $keyProcess = new Process(['openssl', 'genrsa', '-out', $otherKey, '2048']);
        $keyProcess->run();
        $this->assertTrue($keyProcess->isSuccessful(), $keyProcess->getErrorOutput());

        $process = new Process([
            'bash', $this->projectRoot.'/install/deploy_nginx_certificate.sh', $certificate, $otherKey,
        ], $this->projectRoot, [
            'FSPBX_NGINX_CERTIFICATE_PATH' => $this->directory.'/nginx/fullchain.pem',
            'FSPBX_NGINX_PRIVATE_KEY_PATH' => $this->directory.'/nginx/private/privkey.pem',
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('do not match', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->directory.'/nginx/fullchain.pem');
    }

    public function test_nginx_deployer_restores_previous_files_when_validation_fails(): void
    {
        $oldCertificate = $this->directory.'/old-certificate.pem';
        $oldKey = $this->directory.'/old-key.pem';
        $newCertificate = $this->directory.'/new-certificate.pem';
        $newKey = $this->directory.'/new-key.pem';
        $this->createCertificatePair($oldCertificate, $oldKey, 'old.example.com');
        $this->createCertificatePair($newCertificate, $newKey, 'new.example.com');

        $certificatePath = $this->directory.'/nginx/fullchain.pem';
        $keyPath = $this->directory.'/nginx/private/privkey.pem';
        mkdir(dirname($certificatePath), 0700, true);
        mkdir(dirname($keyPath), 0700, true);
        copy($oldCertificate, $certificatePath);
        copy($oldKey, $keyPath);
        $expectedCertificate = file_get_contents($certificatePath);
        $expectedKey = file_get_contents($keyPath);

        $bin = $this->directory.'/bin';
        mkdir($bin, 0700);
        $nginxCalls = $this->directory.'/nginx-calls';
        file_put_contents($bin.'/nginx', <<<'SH'
#!/bin/sh
if [ ! -f "$FSPBX_NGINX_CALLS" ]; then
    : > "$FSPBX_NGINX_CALLS"
    exit 1
fi
exit 0
SH);
        chmod($bin.'/nginx', 0755);
        file_put_contents($bin.'/systemctl', "#!/bin/sh\nexit 0\n");
        chmod($bin.'/systemctl', 0755);

        $process = new Process([
            'bash', $this->projectRoot.'/install/deploy_nginx_certificate.sh', $newCertificate, $newKey,
        ], $this->projectRoot, [
            'FSPBX_NGINX_CERTIFICATE_PATH' => $certificatePath,
            'FSPBX_NGINX_PRIVATE_KEY_PATH' => $keyPath,
            'FSPBX_NGINX_CALLS' => $nginxCalls,
            'PATH' => $bin.':'.getenv('PATH'),
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame($expectedCertificate, file_get_contents($certificatePath));
        $this->assertSame($expectedKey, file_get_contents($keyPath));
        $this->assertStringContainsString('previous certificate was restored', $process->getErrorOutput());
    }

    private function createCertificatePair(string $certificate, string $key, string $commonName): void
    {
        $process = new Process([
            'openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
            '-subj', '/CN='.$commonName, '-days', '1',
            '-keyout', $key, '-out', $certificate,
        ]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    private function hookProcess(array $arguments, array $environment): Process
    {
        return new Process(
            array_merge(['/bin/bash', $this->projectRoot.'/install/dehydrated_fspbx_hook.sh'], $arguments),
            $this->projectRoot,
            $environment,
            null,
            10
        );
    }
}
