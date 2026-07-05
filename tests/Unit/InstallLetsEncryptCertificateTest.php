<?php

namespace Tests\Unit;

use App\Console\Commands\InstallLetsEncryptCertificate;
use PHPUnit\Framework\TestCase;

class InstallLetsEncryptCertificateTest extends TestCase
{
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
            '/etc/dehydrated/certs/new.example.com/fullchain.pem',
            '/etc/dehydrated/certs/new.example.com/privkey.pem'
        );

        $this->assertStringContainsString(
            'ssl_certificate /etc/dehydrated/certs/new.example.com/fullchain.pem;',
            $updated
        );
        $this->assertStringContainsString(
            'ssl_certificate_key /etc/dehydrated/certs/new.example.com/privkey.pem;',
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
