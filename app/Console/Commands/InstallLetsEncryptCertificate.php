<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class InstallLetsEncryptCertificate extends Command
{
    protected $signature = 'app:install-lets-encrypt-certificate';

    protected $description = 'Set up a Let\'s Encrypt web server certificate using Dehydrated';

    private const DEHYDRATED_DIR = '/etc/dehydrated';
    private const WELLKNOWN_DIR = '/var/www/fspbx/public/.well-known/acme-challenge';
    private const NGINX_CONFIG = '/etc/nginx/sites-available/fspbx.conf';
    private const LEGACY_NGINX_CONFIG = '/etc/nginx/sites-available/freeswitchpbx.conf';
    private const RENEWAL_CRON = '0 3 * * * dehydrated -c && nginx -t && systemctl reload nginx';

    public function handle(): int
    {
        $domain = strtolower(trim((string) $this->ask('Enter the domain for SSL (e.g., pbx.example.com)')));

        if (! $this->isValidDomain($domain)) {
            $this->error('Enter a valid fully qualified domain name.');

            return self::FAILURE;
        }

        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $this->error('This command must run as root. Use sudo php artisan app:install-lets-encrypt-certificate.');

            return self::FAILURE;
        }

        try {
            $nginxConfigPath = $this->findNginxConfig();

            $this->info('Installing Dehydrated...');
            $this->runProcess(['apt-get', 'install', '-y', 'dehydrated', 'curl'], 600);

            $this->info('Configuring Dehydrated...');
            $this->ensureDirectory(self::DEHYDRATED_DIR, 0755);
            $this->ensureDirectory(self::WELLKNOWN_DIR, 0755);
            @chown(self::WELLKNOWN_DIR, 'www-data');
            @chgrp(self::WELLKNOWN_DIR, 'www-data');

            $this->writeFile(self::DEHYDRATED_DIR.'/domains.txt', $domain."\n");
            $this->writeFile(
                self::DEHYDRATED_DIR.'/config',
                'BASEDIR='.self::DEHYDRATED_DIR."\n"
                .'WELLKNOWN='.self::WELLKNOWN_DIR."\n"
            );
            $this->writeFile(self::DEHYDRATED_DIR.'/hook.sh', "#!/bin/bash\nexit 0\n");
            @chmod(self::DEHYDRATED_DIR.'/hook.sh', 0755);

            $this->info('Registering the account and generating the certificate...');
            $this->runProcess(['dehydrated', '--register', '--accept-terms'], 120);
            $this->runProcess(['dehydrated', '-c'], 300);

            $certificateDirectory = self::DEHYDRATED_DIR.'/certs/'.$domain;
            $certificatePath = $certificateDirectory.'/fullchain.pem';
            $privateKeyPath = $certificateDirectory.'/privkey.pem';

            if (! is_readable($certificatePath) || ! is_readable($privateKeyPath)) {
                throw new \RuntimeException('Certificate generation did not create a readable fullchain.pem and privkey.pem.');
            }

            $this->installNginxCertificate(
                $nginxConfigPath,
                $certificatePath,
                $privateKeyPath
            );

            $this->info('Setting up auto-renewal...');
            $this->installRenewalCron();

            $this->info("The web server certificate is installed for {$domain}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function replaceCertificateDirectives(
        string $config,
        string $certificatePath,
        string $privateKeyPath
    ): string {
        $certificateCount = 0;
        $privateKeyCount = 0;

        $updated = preg_replace(
            '/^([ \t]*)ssl_certificate\s+[^;]+;/m',
            '$1ssl_certificate '.$certificatePath.';',
            $config,
            -1,
            $certificateCount
        );

        if ($updated === null) {
            throw new \RuntimeException('Unable to update the Nginx certificate directive.');
        }

        $updated = preg_replace(
            '/^([ \t]*)ssl_certificate_key\s+[^;]+;/m',
            '$1ssl_certificate_key '.$privateKeyPath.';',
            $updated,
            -1,
            $privateKeyCount
        );

        if ($updated === null) {
            throw new \RuntimeException('Unable to update the Nginx private-key directive.');
        }

        if ($certificateCount === 0 || $privateKeyCount === 0) {
            throw new \RuntimeException('The FS PBX Nginx site does not contain certificate directives.');
        }

        return $updated;
    }

    private function installNginxCertificate(
        string $nginxConfigPath,
        string $certificatePath,
        string $privateKeyPath
    ): void {
        $this->info("Updating Nginx configuration at {$nginxConfigPath}...");

        $existingConfig = file_get_contents($nginxConfigPath);

        if ($existingConfig === false) {
            throw new \RuntimeException("Unable to read {$nginxConfigPath}.");
        }

        $updatedConfig = $this->replaceCertificateDirectives(
            $existingConfig,
            $certificatePath,
            $privateKeyPath
        );

        $this->writeFile($nginxConfigPath, $updatedConfig);

        try {
            $this->runProcess(['nginx', '-t']);
            $this->runProcess(['systemctl', 'reload', 'nginx']);
        } catch (Throwable $exception) {
            $this->writeFile($nginxConfigPath, $existingConfig);

            throw new \RuntimeException(
                'Nginx rejected the certificate configuration. The previous configuration was restored. '
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function findNginxConfig(): string
    {
        foreach ([self::NGINX_CONFIG, self::LEGACY_NGINX_CONFIG] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach (glob('/etc/nginx/sites-available/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $config = @file_get_contents($path);

            if ($config !== false && str_contains($config, 'root /var/www/fspbx/public')) {
                return $path;
            }
        }

        throw new \RuntimeException('Unable to find the FS PBX Nginx site configuration.');
    }

    private function installRenewalCron(): void
    {
        $process = new Process(['crontab', '-l']);
        $process->run();
        $crontab = $process->isSuccessful() ? $process->getOutput() : '';

        $lines = preg_split('/\R/', trim($crontab)) ?: [];
        $lines = array_values(array_filter(
            $lines,
            fn (string $line) => ! str_contains($line, 'dehydrated -c')
                || ! str_contains($line, 'systemctl reload nginx')
        ));
        $lines[] = self::RENEWAL_CRON;

        $process = new Process(['crontab', '-']);
        $process->setInput(implode("\n", $lines)."\n");
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Unable to install the certificate renewal cron: '.$this->processError($process));
        }
    }

    private function isValidDomain(string $domain): bool
    {
        return strlen($domain) <= 253
            && str_contains($domain, '.')
            && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function ensureDirectory(string $path, int $permissions): void
    {
        if (! is_dir($path) && ! mkdir($path, $permissions, true) && ! is_dir($path)) {
            throw new \RuntimeException("Unable to create {$path}.");
        }

        @chmod($path, $permissions);
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Unable to write {$path}.");
        }
    }

    private function runProcess(array $command, int $timeout = 60): void
    {
        $process = new Process($command, null, null, null, $timeout);
        $process->run(function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Command failed: '.implode(' ', $command).'. '.$this->processError($process)
            );
        }
    }

    private function processError(Process $process): string
    {
        return trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'No error output was returned.';
    }
}
