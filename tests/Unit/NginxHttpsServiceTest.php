<?php

namespace Tests\Unit;

use App\Services\NginxHttpsService;
use RuntimeException;
use Tests\TestCase;

class NginxHttpsServiceTest extends TestCase
{
    private string $directory;
    private array $paths;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/nginx-https-'.bin2hex(random_bytes(6));
        mkdir($this->directory);

        $this->paths = [
            'helper' => $this->directory.'/helper.sh',
            'snippet' => $this->directory.'/fspbx-https.conf',
            'site' => $this->directory.'/fspbx.conf',
            'certificate' => $this->directory.'/fullchain.pem',
        ];

        file_put_contents($this->paths['helper'], "#!/bin/sh\n");
        file_put_contents($this->paths['site'], "        include snippets/fspbx-https.conf;\n");
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    private function service(int $exitCode = 0, string $output = 'OK'): NginxHttpsService
    {
        return new NginxHttpsService(function (array $command) use ($exitCode, $output): array {
            $this->calls[] = $command;

            return [$exitCode, $output];
        }, $this->paths);
    }

    private function selfSignedCertificate(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'pbx.example.test'], $key);
        openssl_x509_export(openssl_csr_sign($csr, null, $key, 30), $pem);
        file_put_contents($this->paths['certificate'], $pem);
    }

    public function test_status_is_off_when_the_snippet_says_off_or_is_missing(): void
    {
        $this->assertFalse($this->service()->status()['enabled']);

        file_put_contents($this->paths['snippet'], "# fspbx-https: off\nssl_protocols TLSv1.2;\n");

        $this->assertFalse($this->service()->status()['enabled']);
    }

    public function test_status_is_on_when_the_snippet_says_on(): void
    {
        file_put_contents($this->paths['snippet'], "# fspbx-https: on\nadd_header Strict-Transport-Security \"max-age=31536000\" always;\n");

        $this->assertTrue($this->service()->status()['enabled']);
    }

    public function test_status_reports_whether_the_site_includes_the_snippet(): void
    {
        $this->assertTrue($this->service()->status()['site_ready']);

        file_put_contents($this->paths['site'], "server {}\n");

        $this->assertFalse($this->service()->status()['site_ready']);

        unlink($this->paths['site']);

        $this->assertNull($this->service()->status()['site_ready']);
    }

    public function test_status_flags_a_self_signed_certificate(): void
    {
        $this->selfSignedCertificate();

        $status = $this->service()->status();

        $this->assertTrue($status['certificate']['installed']);
        $this->assertTrue($status['certificate']['is_self_signed']);
        $this->assertTrue($status['requires_confirmation']);
        $this->assertNotNull($status['certificate']['valid_to']);
    }

    public function test_status_without_a_certificate_asks_for_nothing(): void
    {
        $status = $this->service()->status();

        $this->assertFalse($status['certificate']['installed']);
        $this->assertFalse($status['requires_confirmation']);
    }

    public function test_apply_runs_only_the_helper_through_sudo(): void
    {
        $this->service()->apply(true);
        $this->service()->apply(false);

        $this->assertSame(['sudo', '-n', $this->paths['helper'], 'on'], $this->calls[0]);
        $this->assertSame(['sudo', '-n', $this->paths['helper'], 'off'], $this->calls[1]);
    }

    public function test_self_signed_flag_is_only_passed_when_turning_on(): void
    {
        $this->service()->apply(true, true);
        $this->service()->apply(false, true);

        $this->assertSame('--allow-self-signed', end($this->calls[0]));
        $this->assertNotContains('--allow-self-signed', $this->calls[1]);
    }

    public function test_apply_fails_without_the_helper(): void
    {
        unlink($this->paths['helper']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('helper is not installed');

        $this->service()->apply(true);
    }

    public function test_helper_exit_codes_become_messages(): void
    {
        $cases = [
            2 => 'does not include the HTTPS setting',
            3 => 'self-signed',
            4 => 'rejected the new configuration',
            5 => 'could not be reloaded',
            1 => 'could not be changed',
        ];

        foreach ($cases as $code => $fragment) {
            try {
                $this->service($code, 'boom')->apply(true);
                $this->fail("exit code $code should throw");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($fragment, $exception->getMessage(), "exit code $code");
            }
        }
    }

    public function test_a_sudo_password_prompt_is_reported_as_missing_permission(): void
    {
        try {
            $this->service(1, 'sudo: a password is required')->apply(true);
            $this->fail('should throw');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not allowed to run the HTTPS helper', $exception->getMessage());
        }
    }
}
