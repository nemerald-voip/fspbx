<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class InstallLetsEncryptCertificate extends Command
{
    protected $signature = 'app:install-lets-encrypt-certificate';

    protected $description = 'Set up a Let\'s Encrypt Nginx certificate using Dehydrated';

    protected const DEHYDRATED_DIR = '/etc/dehydrated';
    protected const DEPLOYMENT_CONFIG = '/etc/dehydrated/fspbx-deployment.conf';
    protected const HOOK_PATH = '/etc/dehydrated/fspbx-hook.sh';
    protected const DEPLOY_HELPER_PATH = '/usr/local/sbin/fspbx-deploy-nginx-certificate';
    protected const WELLKNOWN_DIR = '/var/www/fspbx/public/.well-known/acme-challenge';
    protected const NGINX_CERTIFICATE = '/etc/nginx/ssl/fullchain.pem';
    protected const NGINX_PRIVATE_KEY = '/etc/nginx/ssl/private/privkey.pem';
    protected const NGINX_CONFIG = '/etc/nginx/sites-available/fspbx.conf';
    protected const LEGACY_NGINX_CONFIG = '/etc/nginx/sites-available/freeswitchpbx.conf';
    protected const RENEWAL_CRON = '0 3 * * * cd /var/www/fspbx && php artisan app:renew-nginx-certificate';
    protected const RENEWAL_MARKER = '# FS PBX Nginx Let\'s Encrypt renewal';

    public function handle(): int
    {
        if (! $this->isRoot()) {
            $this->error('This command must run as root. Use sudo php artisan app:install-lets-encrypt-certificate.');

            return self::FAILURE;
        }

        $mode = $this->promptDeploymentMode();

        try {
            $existing = $this->readDeploymentConfig(self::DEPLOYMENT_CONFIG);
            $topology = $mode === 'single'
                ? $this->promptSingleServerTopology()
                : $this->promptRedundantTopology();

            if ($mode === 'redundant') {
                $this->displayTopology($topology);
                if (! $this->confirm('Continue with this redundant certificate setup?', false)) {
                    $this->warn('Certificate setup cancelled.');

                    return self::SUCCESS;
                }
            }

            $formerPeerChanged = $mode === 'redundant'
                && ($existing['MODE'] ?? null) === 'redundant'
                && (($existing['PEER_SSH_TARGET'] ?? '') !== $topology['peer_ssh_target']
                    || (int) ($existing['PEER_SSH_PORT'] ?? 22) !== $topology['peer_ssh_port']);
            if (($existing['MODE'] ?? null) === 'redundant' && ($mode === 'single' || $formerPeerChanged)) {
                $this->disableFormerPeer($existing);
            }

            $this->installLocalPrerequisites($mode);
            if ($mode === 'redundant') {
                $this->verifyReciprocalSsh($topology);
                $this->installRemotePrerequisites($topology);
            }

            $this->configureLocalNode($mode, $topology);
            if ($mode === 'redundant') {
                $this->configureRemoteNode($topology);
            }

            $this->verifyHttpChallenges($topology);

            $this->info('Registering the local ACME account...');
            $this->runProcess(['dehydrated', '--register', '--accept-terms'], 120);
            if ($mode === 'redundant') {
                $this->info('Registering the peer ACME account...');
                $this->runRemote($topology, ['dehydrated', '--register', '--accept-terms'], 120);
            }

            $this->info('Requesting the certificate...');
            $this->runProcess(['dehydrated', '-c'], 600);

            if (! $this->certificateFilesInstalled()) {
                throw new RuntimeException('Certificate deployment did not create the Nginx certificate and private key.');
            }

            $this->installLocalNginxConfiguration();
            if ($mode === 'redundant') {
                $this->installRemoteNginxConfiguration($topology);
            }

            $this->installLocalRenewalCron();
            if ($mode === 'redundant') {
                $this->installRemoteRenewalCron($topology);
            }

            $this->info('The Nginx certificate is installed for '.implode(', ', $topology['domains']).'.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function promptDeploymentMode(): string
    {
        $this->newLine();
        $this->line('Select how this Nginx certificate will be deployed:');
        $this->newLine();
        $this->line('  [1] Single server');
        $this->line('      Issue, install, and renew the certificate only on this server.');
        $this->newLine();
        $this->line('  [2] Redundant server pair');
        $this->line('      Use one certificate on two primary/standby FS PBX servers. ACME');
        $this->line('      challenges and certificates are synchronized through passwordless');
        $this->line('      root SSH so the floating hostname remains trusted after failover.');
        $this->newLine();

        do {
            $selection = trim((string) $this->ask('Select 1 or 2'));
            $mode = $this->deploymentModeForSelection($selection);
            if ($mode !== null) {
                return $mode;
            }

            $this->error('Enter 1 for a single server or 2 for a redundant server pair.');
        } while (true);
    }

    protected function deploymentModeForSelection(string $selection): ?string
    {
        return match (trim($selection)) {
            '1' => 'single',
            '2' => 'redundant',
            default => null,
        };
    }

    protected function promptSingleServerTopology(): array
    {
        $domains = $this->parseDomains((string) $this->ask(
            'Enter hostname(s) for SSL. Separate multiple hostnames with commas or spaces; every hostname must already point to this server'
        ));
        $this->assertValidDomains($domains);

        return [
            'mode' => 'single',
            'domains' => $domains,
            'floating_host' => $domains[0],
            'local_host' => $domains[0],
        ];
    }

    protected function promptRedundantTopology(): array
    {
        $this->newLine();
        $this->line('The floating hostname is the address users normally open. Its DNS record');
        $this->line('points to whichever FS PBX server is currently active.');
        $floatingHost = $this->normalizeDomain((string) $this->ask(
            'Floating hostname (example: portal.mydomain.com)'
        ));

        $this->newLine();
        $this->line("This server's direct hostname always points to this server.");
        $localHost = $this->normalizeDomain((string) $this->ask(
            'This server hostname (example: pbx1.mydomain.com)'
        ));

        $this->newLine();
        $this->line('The peer hostname always points to the other FS PBX server.');
        $peerHost = $this->normalizeDomain((string) $this->ask(
            'Peer server hostname (example: pbx2.mydomain.com)'
        ));

        $domains = [$floatingHost, $localHost, $peerHost];
        $this->assertValidDomains($domains);
        if (count(array_unique($domains)) !== 3) {
            throw new RuntimeException('The floating, local, and peer hostnames must be different.');
        }

        $this->newLine();
        $this->line('FS PBX uses passwordless root SSH to copy ACME challenges, certificate');
        $this->line('state, and the Nginx certificate between the servers.');
        $sshTarget = strtolower(trim((string) $this->ask(
            'Peer SSH target',
            'root@'.$peerHost
        )));
        $sshPort = (int) $this->ask('SSH port', '22');

        if (! $this->isValidSshTarget($sshTarget)) {
            throw new RuntimeException('The peer SSH target must use root@ followed by a valid hostname or IPv4 address.');
        }
        if ($sshPort < 1 || $sshPort > 65535) {
            throw new RuntimeException('The SSH port must be between 1 and 65535.');
        }

        return [
            'mode' => 'redundant',
            'domains' => $domains,
            'floating_host' => $floatingHost,
            'local_host' => $localHost,
            'peer_host' => $peerHost,
            'peer_ssh_target' => $sshTarget,
            'peer_ssh_port' => $sshPort,
        ];
    }

    protected function displayTopology(array $topology): void
    {
        $this->newLine();
        $this->line('Certificate hostnames:');
        foreach ($topology['domains'] as $domain) {
            $this->line('  '.$domain);
        }
        $this->newLine();
        $this->line('Local server:  '.$topology['local_host']);
        $this->line('Peer server:   '.$topology['peer_ssh_target'].':'.$topology['peer_ssh_port']);
        $this->line('Renewal owner: whichever server '.$topology['floating_host'].' currently points to');
        $this->newLine();
    }

    protected function installLocalPrerequisites(string $mode): void
    {
        $packages = ['dehydrated', 'curl'];
        if ($mode === 'redundant') {
            array_push($packages, 'rsync', 'openssh-client', 'dnsutils');
        }

        $this->info('Installing local certificate prerequisites...');
        $this->runProcess(array_merge(['apt-get', 'install', '-y'], $packages), 600);
    }

    protected function installRemotePrerequisites(array $topology): void
    {
        $this->info('Installing certificate prerequisites on the peer...');
        $this->runRemote(
            $topology,
            ['apt-get', 'install', '-y', 'dehydrated', 'curl', 'rsync', 'openssh-client', 'dnsutils'],
            600
        );
    }

    protected function verifyReciprocalSsh(array $topology): void
    {
        $this->info('Checking passwordless SSH to the peer...');
        try {
            $this->runRemote($topology, ['true'], 15, false);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Passwordless root SSH to the peer is not configured. From this server, make sure '
                ."ssh -p {$topology['peer_ssh_port']} {$topology['peer_ssh_target']} true succeeds, then rerun the installer. "
                .$exception->getMessage(),
                previous: $exception
            );
        }

        $this->info('Checking passwordless SSH from the peer back to this server...');
        $reverseTarget = 'root@'.$topology['local_host'];
        try {
            $this->verifyReverseSsh($topology, $reverseTarget);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Passwordless root SSH works to {$topology['peer_host']}, but not from the peer back to {$topology['local_host']}. "
                ."Log in to {$topology['peer_host']}, run: ssh-copy-id -p {$topology['peer_ssh_port']} {$reverseTarget}, "
                ."then verify: ssh -o BatchMode=yes -p {$topology['peer_ssh_port']} {$reverseTarget} true. "
                .'Rerun the certificate installer after that command succeeds. '
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    protected function verifyReverseSsh(array $topology, string $reverseTarget): void
    {
        $this->runRemote($topology, [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $topology['peer_ssh_port'],
            $reverseTarget,
            'true',
        ], 20, false);
    }

    protected function configureLocalNode(string $mode, array $topology): void
    {
        $this->info('Configuring Dehydrated locally...');
        $this->ensureDirectory(self::DEHYDRATED_DIR, 0755);
        $this->ensureDirectory(self::WELLKNOWN_DIR, 0755);
        $this->ensureDirectory(dirname(self::DEPLOY_HELPER_PATH), 0755);
        @chown(self::WELLKNOWN_DIR, 'www-data');
        @chgrp(self::WELLKNOWN_DIR, 'www-data');

        $this->writeFile(self::DEHYDRATED_DIR.'/domains.txt', implode(' ', $topology['domains'])."\n", 0644);
        $this->writeFile(self::DEHYDRATED_DIR.'/config', $this->dehydratedConfigContents(), 0644);
        $this->writeFile(self::DEPLOYMENT_CONFIG, $this->deploymentConfigContents($mode, $topology), 0600);
        $this->copyFile(base_path('install/dehydrated_fspbx_hook.sh'), self::HOOK_PATH, 0755);
        $this->copyFile(base_path('install/deploy_nginx_certificate.sh'), self::DEPLOY_HELPER_PATH, 0755);
    }

    protected function configureRemoteNode(array $topology): void
    {
        $this->info('Configuring Dehydrated on the peer...');
        foreach ([self::DEHYDRATED_DIR, self::WELLKNOWN_DIR, dirname(self::DEPLOY_HELPER_PATH)] as $directory) {
            $this->runRemote($topology, ['install', '-d', '-m', '0755', $directory], 30, false);
        }
        $this->runRemote($topology, ['chown', 'www-data:www-data', self::WELLKNOWN_DIR], 30, false);

        $remoteTopology = [
            'mode' => 'redundant',
            'domains' => $topology['domains'],
            'floating_host' => $topology['floating_host'],
            'local_host' => $topology['peer_host'],
            'peer_host' => $topology['local_host'],
            'peer_ssh_target' => 'root@'.$topology['local_host'],
            'peer_ssh_port' => $topology['peer_ssh_port'],
        ];

        $this->uploadContents($topology, implode(' ', $topology['domains'])."\n", self::DEHYDRATED_DIR.'/domains.txt', 0644);
        $this->uploadContents($topology, $this->dehydratedConfigContents(), self::DEHYDRATED_DIR.'/config', 0644);
        $this->uploadContents($topology, $this->deploymentConfigContents('redundant', $remoteTopology), self::DEPLOYMENT_CONFIG, 0600);
        $this->uploadFile($topology, base_path('install/dehydrated_fspbx_hook.sh'), self::HOOK_PATH, 0755);
        $this->uploadFile($topology, base_path('install/deploy_nginx_certificate.sh'), self::DEPLOY_HELPER_PATH, 0755);
    }

    protected function dehydratedConfigContents(): string
    {
        return 'BASEDIR='.self::DEHYDRATED_DIR."\n"
            .'WELLKNOWN='.self::WELLKNOWN_DIR."\n"
            .'HOOK='.self::HOOK_PATH."\n"
            .'CHALLENGETYPE=http-01'."\n";
    }

    protected function deploymentConfigContents(string $mode, array $topology): string
    {
        $values = [
            'MODE' => $mode,
            'FLOATING_HOST' => $topology['floating_host'] ?? '',
            'LOCAL_HOST' => $topology['local_host'] ?? '',
            'PEER_HOST' => $topology['peer_host'] ?? '',
            'PEER_SSH_TARGET' => $topology['peer_ssh_target'] ?? '',
            'PEER_SSH_PORT' => (string) ($topology['peer_ssh_port'] ?? 22),
            'WELLKNOWN_DIR' => self::WELLKNOWN_DIR,
        ];

        return implode("\n", array_map(
            fn (string $key, string $value) => $key."='".$value."'",
            array_keys($values),
            array_values($values)
        ))."\n";
    }

    protected function verifyHttpChallenges(array $topology): void
    {
        $this->info('Checking public HTTP-01 challenge access for every hostname...');
        $token = 'fspbx-preflight-'.bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(24));
        $localPath = self::WELLKNOWN_DIR.'/'.$token;

        $this->writeFile($localPath, $value, 0644);
        if ($topology['mode'] === 'redundant') {
            $this->uploadContents($topology, $value, $localPath, 0644);
        }

        try {
            foreach ($topology['domains'] as $domain) {
                $url = 'http://'.$domain.'/.well-known/acme-challenge/'.$token;
                $output = trim($this->runProcess([
                    'curl', '--fail', '--silent', '--show-error', '--location', '--insecure',
                    '--connect-timeout', '5', '--max-time', '15', $url,
                ], 20, false));

                if (! hash_equals($value, $output)) {
                    throw new RuntimeException("HTTP-01 preflight returned unexpected content for {$domain}.");
                }
            }
        } finally {
            @unlink($localPath);
            if ($topology['mode'] === 'redundant') {
                try {
                    $this->runRemote($topology, ['rm', '-f', $localPath], 20, false);
                } catch (Throwable) {
                    // The preflight error remains the useful failure to report.
                }
            }
        }
    }

    protected function installLocalNginxConfiguration(): void
    {
        $path = $this->findLocalNginxConfig();
        $existing = file_get_contents($path);
        if ($existing === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        $updated = $this->replaceCertificateDirectives(
            $existing,
            self::NGINX_CERTIFICATE,
            self::NGINX_PRIVATE_KEY
        );
        $this->writeFile($path, $updated, 0644);

        try {
            $this->runProcess(['nginx', '-t']);
            $this->runProcess(['systemctl', 'reload', 'nginx']);
        } catch (Throwable $exception) {
            $this->writeFile($path, $existing, 0644);
            throw new RuntimeException(
                'Nginx rejected the local certificate configuration. The previous configuration was restored. '
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    protected function certificateFilesInstalled(): bool
    {
        return is_readable(self::NGINX_CERTIFICATE) && is_readable(self::NGINX_PRIVATE_KEY);
    }

    protected function installRemoteNginxConfiguration(array $topology): void
    {
        $path = $this->findRemoteNginxConfig($topology);
        $existing = $this->runRemote($topology, ['cat', $path], 30, false);
        $updated = $this->replaceCertificateDirectives(
            $existing,
            self::NGINX_CERTIFICATE,
            self::NGINX_PRIVATE_KEY
        );
        $this->uploadContents($topology, $updated, $path, 0644);

        try {
            $this->runRemote($topology, ['nginx', '-t']);
            $this->runRemote($topology, ['systemctl', 'reload', 'nginx']);
        } catch (Throwable $exception) {
            $this->uploadContents($topology, $existing, $path, 0644);
            throw new RuntimeException(
                'Nginx rejected the peer certificate configuration. The previous configuration was restored. '
                .$exception->getMessage(),
                previous: $exception
            );
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
            throw new RuntimeException('Unable to update the Nginx certificate directive.');
        }

        $updated = preg_replace(
            '/^([ \t]*)ssl_certificate_key\s+[^;]+;/m',
            '$1ssl_certificate_key '.$privateKeyPath.';',
            $updated,
            -1,
            $privateKeyCount
        );
        if ($updated === null) {
            throw new RuntimeException('Unable to update the Nginx private-key directive.');
        }

        if ($certificateCount === 0 || $privateKeyCount === 0) {
            throw new RuntimeException('The FS PBX Nginx site does not contain certificate directives.');
        }

        return $updated;
    }

    protected function installLocalRenewalCron(): void
    {
        $process = new Process(['crontab', '-l']);
        $process->run();
        $existing = $process->isSuccessful() ? $process->getOutput() : '';
        $updated = $this->updatedCrontab($existing, true);

        $process = new Process(['crontab', '-']);
        $process->setInput($updated);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to install the local certificate renewal cron: '.$this->processError($process));
        }
    }

    protected function installRemoteRenewalCron(array $topology): void
    {
        $existing = '';
        try {
            $existing = $this->runRemote($topology, ['crontab', '-l'], 30, false);
        } catch (Throwable) {
            // A missing crontab is normal on a newly installed peer.
        }

        $this->uploadRemoteCrontab($topology, $this->updatedCrontab($existing, true));
    }

    protected function disableFormerPeer(array $existing): void
    {
        $topology = [
            'peer_ssh_target' => $existing['PEER_SSH_TARGET'] ?? '',
            'peer_ssh_port' => (int) ($existing['PEER_SSH_PORT'] ?? 22),
        ];
        if (! $this->isValidSshTarget($topology['peer_ssh_target'])) {
            throw new RuntimeException('The previous redundant peer configuration is invalid; it must be corrected before switching to single-server mode.');
        }

        $this->info('Disabling certificate renewal on the former peer...');
        $remoteConfig = [
            'floating_host' => $existing['FLOATING_HOST'] ?? '',
            'local_host' => $existing['PEER_HOST'] ?? '',
        ];
        $this->uploadContents($topology, $this->deploymentConfigContents('disabled', $remoteConfig), self::DEPLOYMENT_CONFIG, 0600);

        $remoteCrontab = '';
        try {
            $remoteCrontab = $this->runRemote($topology, ['crontab', '-l'], 30, false);
        } catch (Throwable) {
            // Missing crontab is already the desired state.
        }
        $this->uploadRemoteCrontab($topology, $this->updatedCrontab($remoteCrontab, false));
    }

    protected function updatedCrontab(string $existing, bool $addRenewal): string
    {
        $lines = preg_split('/\R/', trim($existing)) ?: [];
        $lines = array_values(array_filter($lines, function (string $line): bool {
            if (trim($line) === self::RENEWAL_MARKER) {
                return false;
            }
            if (str_contains($line, 'app:renew-nginx-certificate')) {
                return false;
            }

            return ! (str_contains($line, 'dehydrated -c') && str_contains($line, 'systemctl reload nginx'));
        }));

        if ($addRenewal) {
            $lines[] = self::RENEWAL_MARKER;
            $lines[] = self::RENEWAL_CRON;
        }

        return implode("\n", $lines)."\n";
    }

    protected function uploadRemoteCrontab(array $topology, string $contents): void
    {
        $remotePath = '/tmp/fspbx-root-crontab-'.bin2hex(random_bytes(6));
        $this->uploadContents($topology, $contents, $remotePath, 0600);
        try {
            $this->runRemote($topology, ['crontab', $remotePath], 30, false);
        } finally {
            try {
                $this->runRemote($topology, ['rm', '-f', $remotePath], 20, false);
            } catch (Throwable) {
                // Do not hide the crontab installation failure.
            }
        }
    }

    protected function findLocalNginxConfig(): string
    {
        foreach ([self::NGINX_CONFIG, self::LEGACY_NGINX_CONFIG] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach (glob('/etc/nginx/sites-available/*') ?: [] as $path) {
            $config = is_file($path) ? @file_get_contents($path) : false;
            if ($config !== false && str_contains($config, 'root /var/www/fspbx/public')) {
                return $path;
            }
        }

        throw new RuntimeException('Unable to find the FS PBX Nginx site configuration.');
    }

    protected function findRemoteNginxConfig(array $topology): string
    {
        foreach ([self::NGINX_CONFIG, self::LEGACY_NGINX_CONFIG] as $path) {
            try {
                $this->runRemote($topology, ['test', '-f', $path], 20, false);

                return $path;
            } catch (Throwable) {
                // Try the next standard FS PBX path.
            }
        }

        throw new RuntimeException('Unable to find the FS PBX Nginx site configuration on the peer.');
    }

    protected function uploadContents(array $topology, string $contents, string $destination, int $mode): void
    {
        $temporary = tempnam('/tmp', 'fspbx-cert-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary certificate setup file.');
        }

        try {
            if (file_put_contents($temporary, $contents) === false) {
                throw new RuntimeException('Unable to write a temporary certificate setup file.');
            }
            $this->uploadFile($topology, $temporary, $destination, $mode);
        } finally {
            @unlink($temporary);
        }
    }

    protected function uploadFile(array $topology, string $source, string $destination, int $mode): void
    {
        if (! is_readable($source)) {
            throw new RuntimeException("Unable to read {$source}.");
        }

        $remoteTemporary = '/tmp/fspbx-cert-upload-'.bin2hex(random_bytes(6));
        $this->runProcess([
            'scp', '-q', '-P', (string) $topology['peer_ssh_port'],
            '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new',
            $source, $topology['peer_ssh_target'].':'.$remoteTemporary,
        ], 60, false);

        try {
            $this->runRemote($topology, [
                'install', '-D', '-m', sprintf('%04o', $mode),
                $remoteTemporary, $destination,
            ], 30, false);
        } finally {
            try {
                $this->runRemote($topology, ['rm', '-f', $remoteTemporary], 20, false);
            } catch (Throwable) {
                // Do not hide the upload/install failure.
            }
        }
    }

    protected function runRemote(
        array $topology,
        array $command,
        int $timeout = 60,
        bool $stream = true
    ): string {
        return $this->runProcess(array_merge([
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $topology['peer_ssh_port'],
            $topology['peer_ssh_target'],
        ], $command), $timeout, $stream);
    }

    protected function runProcess(array $command, int $timeout = 60, bool $stream = true): string
    {
        $process = new Process($command, null, null, null, $timeout);
        $process->run($stream ? function (string $type, string $buffer): void {
            $this->output->write($buffer);
        } : null);

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Command failed: '.implode(' ', $command).'. '.$this->processError($process)
            );
        }

        return $process->getOutput();
    }

    protected function processError(Process $process): string
    {
        return trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'No error output was returned.';
    }

    protected function readDeploymentConfig(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match("/^([A-Z_]+)='([^']*)'$/", trim($line), $matches)) {
                $values[$matches[1]] = $matches[2];
            }
        }

        return $values;
    }

    protected function parseDomains(string $value): array
    {
        $domains = preg_split('/[\s,]+/', strtolower(trim($value))) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (string $domain) => $this->normalizeDomain($domain),
            $domains
        ))));
    }

    protected function normalizeDomain(string $domain): string
    {
        return strtolower(trim($domain, " \t\n\r\0\x0B."));
    }

    protected function assertValidDomains(array $domains): void
    {
        if (empty($domains)) {
            throw new RuntimeException('Enter at least one valid fully qualified domain name.');
        }

        foreach ($domains as $domain) {
            if (! $this->isValidDomain($domain)) {
                throw new RuntimeException("Invalid hostname: {$domain}. Enter fully qualified domain names only.");
            }
        }
    }

    protected function isValidDomain(string $domain): bool
    {
        return strlen($domain) <= 253
            && str_contains($domain, '.')
            && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    protected function isValidSshTarget(string $target): bool
    {
        if (! str_starts_with($target, 'root@')) {
            return false;
        }

        $host = substr($target, 5);

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || $this->isValidDomain($host);
    }

    protected function ensureDirectory(string $path, int $permissions): void
    {
        if (! is_dir($path) && ! mkdir($path, $permissions, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create {$path}.");
        }
        @chmod($path, $permissions);
    }

    protected function writeFile(string $path, string $contents, int $mode): void
    {
        $this->ensureDirectory(dirname($path), 0755);
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write {$path}.");
        }
        @chmod($path, $mode);
    }

    protected function copyFile(string $source, string $destination, int $mode): void
    {
        if (! is_readable($source) || ! copy($source, $destination)) {
            throw new RuntimeException("Unable to install {$source} at {$destination}.");
        }
        @chmod($destination, $mode);
    }

    protected function isRoot(): bool
    {
        return ! function_exists('posix_geteuid') || posix_geteuid() === 0;
    }
}
