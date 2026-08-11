<?php

namespace App\Console\Commands;

use App\Services\ScheduledAnnouncements\AuthoritativeDnsActiveNodeGuard;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RenewNginxCertificate extends Command
{
    protected $signature = 'app:renew-nginx-certificate';

    protected $description = 'Renew and synchronize the FS PBX Nginx Let\'s Encrypt certificate';

    protected string $deploymentConfigPath = '/etc/dehydrated/fspbx-deployment.conf';

    public function __construct(protected AuthoritativeDnsActiveNodeGuard $activeNodeGuard)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->isRoot()) {
            $this->error('Nginx certificate renewal must run as root.');

            return self::FAILURE;
        }

        try {
            $config = $this->readDeploymentConfig();
            $decision = $this->renewalDecision($config);

            if (! $decision['run']) {
                $message = $decision['reason'] ?? 'Renewal skipped.';
                if (($decision['status'] ?? '') === 'active_unknown') {
                    $this->error($message);

                    return self::FAILURE;
                }

                $this->info($message);

                return self::SUCCESS;
            }

            $this->info($decision['reason']);
            $this->runDehydrated();
            $this->info('Nginx certificate renewal check completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{run: bool, status: string, reason: string}
     */
    protected function renewalDecision(array $config): array
    {
        $mode = $config['MODE'] ?? null;
        if ($mode === 'disabled') {
            return ['run' => false, 'status' => 'disabled', 'reason' => 'Nginx certificate renewal is disabled on this former peer.'];
        }
        if ($mode === 'single') {
            return ['run' => true, 'status' => 'single', 'reason' => 'Running the single-server certificate renewal check.'];
        }
        if ($mode !== 'redundant') {
            throw new RuntimeException('The Nginx certificate deployment mode is missing or invalid. Rerun app:install-lets-encrypt-certificate.');
        }

        $floatingHost = $config['FLOATING_HOST'] ?? '';
        $localHost = $config['LOCAL_HOST'] ?? '';
        $peerHost = $config['PEER_HOST'] ?? '';
        if ($floatingHost === '' || $localHost === '' || $peerHost === '') {
            throw new RuntimeException('The redundant certificate topology is incomplete. Rerun app:install-lets-encrypt-certificate.');
        }

        $localIps = $this->resolveHostIps($localHost);
        $peerIps = $this->resolveHostIps($peerHost);
        if (empty($localIps) || empty($peerIps)) {
            return $this->unknownDecision('A direct node hostname did not resolve to an address');
        }
        if (! empty(array_intersect($localIps, $peerIps))) {
            return $this->unknownDecision('The local and peer direct hostnames resolve to the same address');
        }

        $active = $this->activeNodeGuard->canExecute(null, $floatingHost, $localIps);
        $floatingIps = $this->authoritativeAnswerIps($active['answers'] ?? []);

        if (($active['status'] ?? null) === 'active' || ($active['status'] ?? null) === 'standby') {
            if (empty($floatingIps)) {
                return $this->unknownDecision('Authoritative DNS returned no usable address records');
            }

            $owner = $this->exclusiveDnsOwner($floatingIps, $localIps, $peerIps);
            if ($owner === null) {
                return $this->unknownDecision('Authoritative floating DNS does not identify exactly one configured node');
            }

            if ($owner === 'local' && ($active['active'] ?? false) !== true) {
                return $this->unknownDecision('The authoritative DNS guard disagreed with the configured node addresses');
            }
            if ($owner === 'peer' && ($active['status'] ?? null) !== 'standby') {
                return $this->unknownDecision('The authoritative DNS guard disagreed with the configured node addresses');
            }
        }

        if (($active['active'] ?? false) === true) {
            return [
                'run' => true,
                'status' => 'active',
                'reason' => "Authoritative DNS for {$floatingHost} points to this node; running the certificate renewal check.",
            ];
        }

        if (($active['status'] ?? null) === 'standby') {
            return [
                'run' => false,
                'status' => 'standby',
                'reason' => "Authoritative DNS for {$floatingHost} points to the peer; renewal is skipped on this standby node.",
            ];
        }

        return [
            'run' => false,
            'status' => 'active_unknown',
            'reason' => 'Unable to confirm the active certificate node: '.($active['reason'] ?? 'unknown DNS state').'. Renewal failed closed.',
        ];
    }

    protected function authoritativeAnswerIps(array $answersByNameserver): array
    {
        $ips = [];
        foreach ($answersByNameserver as $answers) {
            if (! is_array($answers)) {
                continue;
            }

            foreach ($answers as $ip) {
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = strtolower($ip);
                }
            }
        }

        return array_values(array_unique($ips));
    }

    protected function exclusiveDnsOwner(array $floatingIps, array $localIps, array $peerIps): ?string
    {
        if (empty(array_diff($floatingIps, $localIps))) {
            return 'local';
        }
        if (empty(array_diff($floatingIps, $peerIps))) {
            return 'peer';
        }

        return null;
    }

    protected function unknownDecision(string $reason): array
    {
        return [
            'run' => false,
            'status' => 'active_unknown',
            'reason' => 'Unable to confirm the active certificate node: '.$reason.'. Renewal failed closed.',
        ];
    }

    protected function resolveHostIps(string $hostname): array
    {
        $ips = [];
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                $value = $record[$key] ?? null;
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_IP)) {
                    $ips[] = strtolower($value);
                }
            }
        }

        foreach (@gethostbynamel($hostname) ?: [] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = strtolower($ip);
            }
        }

        return array_values(array_unique($ips));
    }

    protected function readDeploymentConfig(): array
    {
        if (! is_readable($this->deploymentConfigPath)) {
            throw new RuntimeException('Nginx certificate deployment is not configured. Run app:install-lets-encrypt-certificate first.');
        }

        $values = [];
        foreach (file($this->deploymentConfigPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match("/^([A-Z_]+)='([^']*)'$/", trim($line), $matches)) {
                $values[$matches[1]] = $matches[2];
            }
        }

        return $values;
    }

    protected function runDehydrated(): void
    {
        $process = new Process(['dehydrated', '-c'], null, null, null, 600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'No error output was returned.';
            throw new RuntimeException('Dehydrated renewal failed: '.$error);
        }
    }

    protected function isRoot(): bool
    {
        return ! function_exists('posix_geteuid') || posix_geteuid() === 0;
    }
}
