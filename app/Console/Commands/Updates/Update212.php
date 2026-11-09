<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

/** One-time Syncthing network correction for existing installations. */
class Update212
{
    public const RELAY = 'dynamic+https://relays.syncthing.net/endpoint';

    public function __construct(
        public string $stateDir = '/var/lib/fspbx-update-2.1.2',
        public string $rulesDir = '/etc/iptables',
        public array $configPaths = [
            '/home/www-data/.local/state/syncthing/config.xml',
            '/home/www-data/.config/syncthing/config.xml',
        ],
        private string $lockPath = '/run/lock/fspbx-update-2.1.2.lock',
        public string $ufwConfigPath = '/etc/ufw/ufw.conf',
    ) {}

    public function apply(): bool
    {
        $lock = null;
        try {
            $this->assertRoot();
            $lock = fopen($this->lockPath, 'c');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Cannot acquire the local update 2.1.2 lock');
            }
            $this->repair();
        } catch (Throwable $error) {
            echo 'WARNING: Syncthing network update was not completed: '.$error->getMessage()."\n";
            echo "Continuing the application update. Review Syncthing manually on this server.\n";
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
        }

        // Host-service failures must not stop the application update.
        return true;
    }

    protected function assertRoot(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            throw new RuntimeException('Run app:update as root; no Syncthing changes made');
        }
    }

    public function command(array $args, ?string $input = null, int $timeout = 15, bool $check = true): array
    {
        // Files avoid pipe deadlocks with large saved firewalls. Array arguments
        // bypass the shell, and errors never include credentials or config bodies.
        $streams = [tmpfile(), tmpfile(), tmpfile()];
        if (in_array(false, $streams, true)) {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            throw new RuntimeException('Cannot allocate command streams');
        }
        $process = null;
        try {
            self::writeStream($streams[0], $input ?? '');
            rewind($streams[0]);
            $process = proc_open($args, $streams, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot run '.basename($args[0]));
            }
            $deadline = hrtime(true) + $timeout * 1_000_000_000;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException(basename($args[0]).' timed out');
                }
                usleep(20_000);
            } while (true);
            rewind($streams[1]);
            rewind($streams[2]);
            $result = ['code' => $status['exitcode'], 'stdout' => stream_get_contents($streams[1]),
                'stderr' => stream_get_contents($streams[2])];
            if ($check && $result['code'] !== 0) {
                throw new RuntimeException(basename($args[0]).' failed (exit '.$result['code'].')');
            }
            return $result;
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                proc_close($process);
            }
            foreach ($streams as $stream) {
                fclose($stream);
            }
        }
    }

    private static function writeStream($stream, string $data): void
    {
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset += $written) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Cannot write configuration data');
            }
        }
    }

    public static function read(string $path): string
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Cannot read '.$path);
        }
        return $data;
    }

    public static function atomicWrite(string $path, string $data, int $mode = 0600, ?array $owner = null): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Refusing symlink: '.$path);
        }
        $temporary = tempnam(dirname($path), '.'.basename($path));
        if ($temporary === false || dirname($temporary) !== realpath(dirname($path))) {
            if ($temporary !== false) {
                unlink($temporary);
            }
            throw new RuntimeException('Cannot create temporary configuration file');
        }
        try {
            if (!chmod($temporary, $mode) || ($owner !== null &&
                (!chown($temporary, $owner[0]) || !chgrp($temporary, $owner[1])))) {
                throw new RuntimeException('Cannot preserve file permissions');
            }
            $stream = fopen($temporary, 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open temporary configuration file');
            }
            try {
                self::writeStream($stream, $data);
                if (!fflush($stream) || !fsync($stream)) {
                    throw new RuntimeException('Cannot persist configuration file');
                }
            } finally {
                fclose($stream);
            }
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace '.$path);
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function xml(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new RuntimeException('Invalid or unsupported XML configuration');
            }
            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function address(string $value): string
    {
        $packed = @inet_pton($value);
        if ($packed === false) {
            throw new RuntimeException('Peer address is not a usable server IP');
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }
        $ip = inet_ntop($packed);
        $first = ord($packed[0]);
        $unsafe = strlen($packed) === 4
            ? ($ip === '0.0.0.0' || $first === 127 || ($first >= 224 && $first <= 239)
                || ($first === 169 && ord($packed[1]) === 254))
            : ($ip === '::' || $ip === '::1' || $first === 255
                || ($first === 254 && (ord($packed[1]) & 0xc0) === 0x80));
        if ($unsafe) {
            throw new RuntimeException('Peer address is not a usable server IP');
        }
        return $ip;
    }

    public static function tcpUrl(string $ip): string
    {
        return 'tcp://'.(str_contains($ip, ':') ? '['.$ip.']' : $ip).':22000';
    }

    public function firewallRule(string $ip): array
    {
        $ip = $this->address($ip);
        return ['-s', $ip.(str_contains($ip, ':') ? '/128' : '/32'), '-p', 'tcp', '-m', 'tcp',
            '--dport', '22000', '-m', 'comment', '--comment', 'fspbx-syncthing', '-j', 'ACCEPT'];
    }

    public static function addPersistentRule(string $contents, array $rule): string
    {
        $lines = explode("\n", rtrim($contents, "\n"));
        $starts = array_keys($lines, '*filter', true);
        if (count($starts) !== 1) {
            throw new RuntimeException('Cannot identify the saved iptables filter table');
        }
        $start = $starts[0];
        $end = null;
        for ($i = $start + 1; $i < count($lines); $i++) {
            if ($lines[$i] === 'COMMIT') {
                $end = $i;
                break;
            }
        }
        if ($end === null) {
            throw new RuntimeException('Incomplete saved iptables filter table');
        }
        // Tokens here are fixed flags and validated IPs; no shell escaping is
        // needed in iptables-save syntax. Accept its quoted comment form too.
        $full = '-A INPUT '.implode(' ', $rule);
        foreach (array_slice($lines, $start + 1, $end - $start - 1) as $line) {
            $normalized = str_replace(['"fspbx-syncthing"', "'fspbx-syncthing'"], 'fspbx-syncthing', $line);
            if (preg_replace('/\s+/', ' ', trim($normalized)) === $full) {
                return $contents;
            }
        }
        $position = $start + 1;
        while ($position < $end && ($lines[$position] === '' || str_starts_with($lines[$position], ':')
            || str_starts_with($lines[$position], '#'))) {
            $position++;
        }
        array_splice($lines, $position, 0, [$full]);
        return implode("\n", $lines)."\n";
    }

    public function allowPeers(array $peers, ?string $backupDirectory = null): void
    {
        if (trim($this->command(['systemctl', 'is-enabled', 'netfilter-persistent'], check: false)['stdout']) !== 'enabled') {
            throw new RuntimeException('Enabled netfilter-persistent is required to preserve the TCP rule after reboot');
        }
        // UFW's oneshot unit can remain active after UFW is disabled or
        // removed. Check its boot configuration and actual chains instead.
        if (file_exists($this->ufwConfigPath)) {
            $count = preg_match_all('/^\h*ENABLED\h*=(.*)$/m', self::read($this->ufwConfigPath), $matches);
            if ($count !== 1 || !preg_match('/^(?:no|"no"|\'no\')\h*(?:#.*)?$/', trim($matches[1][0]))) {
                throw new RuntimeException('UFW is enabled or its configuration is unclear; manual review required');
            }
        }
        foreach (['firewalld', 'nftables'] as $service) {
            if ($this->command(['systemctl', 'is-active', $service], check: false)['code'] === 0) {
                throw new RuntimeException($service.' manages this firewall; manual review required');
            }
        }
        $changes = [];
        foreach ([4, 6] as $family) {
            $ips = array_unique(array_filter($peers, fn ($ip) => str_contains($ip, ':') === ($family === 6)));
            if (!$ips) {
                continue;
            }
            sort($ips);
            $binary = $family === 4 ? 'iptables' : 'ip6tables';
            $path = $this->rulesDir.'/rules.v'.$family;
            $original = $updated = self::read($path);
            $live = $this->command([$binary, '-w', '5', '-S'])['stdout'];
            if (preg_match('/^(?:-N |:)ufw-/m', $original."\n".$live)) {
                throw new RuntimeException('UFW chains exist in the live or saved firewall; manual review required');
            }
            foreach ($ips as $ip) {
                $updated = self::addPersistentRule($updated, $this->firewallRule($ip));
            }
            $this->command([$binary.'-restore', '--test'], input: $updated);
            $changes[] = compact('binary', 'path', 'original', 'updated', 'ips');
        }
        // Validate every file/family before changing any persisted or live rule.
        foreach ($changes as $change) {
            ['binary' => $binary, 'path' => $path, 'original' => $original, 'updated' => $updated, 'ips' => $ips] = $change;
            if ($backupDirectory !== null) {
                $backup = $backupDirectory.'/'.basename($path);
                if (!file_exists($backup)) {
                    self::atomicWrite($backup, $original);
                }
            }
            if ($updated !== $original) {
                $meta = stat($path);
                self::atomicWrite($path, $updated, $meta['mode'] & 0777, [$meta['uid'], $meta['gid']]);
            }
            foreach ($ips as $ip) {
                $rule = $this->firewallRule($ip);
                if ($this->command(array_merge([$binary, '-w', '5', '-C', 'INPUT'], $rule), check: false)['code'] !== 0) {
                    $this->command(array_merge([$binary, '-w', '5', '-I', 'INPUT', '1'], $rule));
                }
            }
        }
    }

    public function prepareFirewall(array $peers): void
    {
        $ips = [];
        foreach ($peers as $addresses) {
            array_push($ips, ...(array) $addresses);
        }
        $this->allowPeers($ips, $this->stateDir.'/backup');
    }

    public function load(): array
    {
        $path = $this->stateDir.'/state.json';
        return file_exists($path) ? json_decode(self::read($path), true, 512, JSON_THROW_ON_ERROR) : [];
    }

    public function save(array $state): void
    {
        self::atomicWrite($this->stateDir.'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }

    public static function udpSockets(string $output): array
    {
        $sockets = [];
        foreach (explode("\n", $output) as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (count($fields) >= 5 && preg_match('/\("syncthing",pid=(\d+),/', $line, $owner)
                && preg_match('/^(.*):(\d+)$/', $fields[3], $endpoint)) {
                $sockets[] = [trim($endpoint[1], '[]'), (int) $endpoint[2], (int) $owner[1]];
            }
        }
        return $sockets;
    }

    public static function configuredRange(string $xml): array
    {
        $xpath = new DOMXPath(self::xml($xml));
        $configs = $xpath->query('//configuration[@name="switch.conf"]');
        if ($configs->length !== 1) {
            throw new RuntimeException('Cannot identify the FreeSWITCH core configuration');
        }
        $range = [];
        foreach (['rtp-start-port' => 16384, 'rtp-end-port' => 32768] as $name => $default) {
            $matches = $xpath->query('./settings/param[@name="'.$name.'"]', $configs->item(0));
            if ($matches->length > 1) {
                throw new RuntimeException('Duplicate RTP range settings require review');
            }
            $value = $matches->length ? $matches->item(0)->getAttribute('value') : (string) $default;
            if (!ctype_digit($value)) {
                throw new RuntimeException('RTP range contains an unresolved value');
            }
            $range[] = (int) $value;
        }
        if ($range[0] < 1 || $range[0] >= $range[1] || $range[1] > 65535) {
            throw new RuntimeException('Invalid RTP range');
        }
        return $range;
    }

    public function detectConflict(): ?array
    {
        $sockets = array_values(array_filter(self::udpSockets($this->command(['ss', '-H', '-u', '-a', '-n', '-p'])['stdout']),
            fn ($row) => in_array($row[1], [21027, 22000], true) && in_array($row[0], ['0.0.0.0', '::', '*'], true)));
        if (!$sockets) {
            return null;
        }
        $pid = trim($this->command(['pgrep', '-x', 'freeswitch'], check: false)['stdout']);
        if (!ctype_digit($pid)) {
            throw new RuntimeException('Cannot confirm one running FreeSWITCH instance');
        }
        $confDir = trim($this->command(['fs_cli', '-x', 'global_getvar conf_dir'])['stdout']);
        if (!str_starts_with($confDir, '/') || str_contains($confDir, "\n")) {
            throw new RuntimeException('Cannot identify the FreeSWITCH configuration directory');
        }
        $core = $confDir.'/autoload_configs/switch.conf.xml';
        // reloadxml does not reset the RTP allocator. A changed core file makes
        // the running range uncertain, even if xml_locate reflects its new value.
        $stat = self::read('/proc/'.$pid.'/stat');
        $fields = preg_split('/\s+/', trim(substr($stat, strrpos($stat, ')') + 1)));
        $ticks = trim($this->command(['getconf', 'CLK_TCK'])['stdout']);
        if (!preg_match('/^btime (\d+)$/m', self::read('/proc/stat'), $boot)
            || !ctype_digit($ticks) || (int) $ticks === 0 || !isset($fields[19])) {
            throw new RuntimeException('Cannot identify the FreeSWITCH start time');
        }
        if (filemtime($core) > (int) $boot[1] + (int) $fields[19] / (int) $ticks) {
            throw new RuntimeException('FreeSWITCH core config changed after startup; RTP range is uncertain');
        }
        $range = self::configuredRange(self::read($core));
        if ($range !== self::configuredRange($this->command([
            'fs_cli', '-x', 'xml_locate configuration configuration name switch.conf'])['stdout'])) {
            throw new RuntimeException('Loaded and on-disk FreeSWITCH RTP ranges differ');
        }
        $conflicts = array_values(array_filter($sockets, fn ($row) => $row[1] >= $range[0] && $row[1] <= $range[1]));
        if (!$conflicts) {
            return null;
        }
        if (count(array_unique(array_column($conflicts, 2))) !== 1) {
            throw new RuntimeException('Multiple Syncthing socket owners require review');
        }
        $ports = array_values(array_unique(array_column($conflicts, 1)));
        sort($ports);
        return ['range' => $range, 'ports' => $ports, 'pid' => $conflicts[0][2]];
    }

    public static function listenersWithoutQuic(array $options): array
    {
        $listeners = [];
        foreach ($options['listenAddresses'] as $value) {
            if ($value === 'default') {
                array_push($listeners, 'tcp://0.0.0.0:22000', self::RELAY);
            } elseif (preg_match('#^(tcp[46]?://|relay://|dynamic\+https://)#', $value)) {
                $listeners[] = $value;
            } elseif (!preg_match('#^quic[46]?://#', $value)) {
                throw new RuntimeException('Unknown Syncthing listener; manual review required');
            }
        }
        if (!array_filter($listeners, fn ($value) => preg_match('#^tcp[46]?://.*:22000$#', $value))) {
            throw new RuntimeException('No existing TCP 22000 listener');
        }
        return array_values(array_unique($listeners));
    }

    protected function peerIp(string $endpoint, bool $defaultPortOnly = true): ?string
    {
        // Older discovery responses contain bare IP:port endpoints.
        $url = @parse_url(str_contains($endpoint, '://') ? $endpoint : 'tcp://'.$endpoint);
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['tcp', 'tcp4', 'tcp6', 'quic', 'quic4', 'quic6'], true)
            || !isset($url['host']) || array_diff(array_keys($url), ['scheme', 'host', 'port'])
            || ($url['port'] ?? 22000) < 1 || ($defaultPortOnly && ($url['port'] ?? 22000) !== 22000)) {
            return null;
        }
        try {
            return $this->address(trim($url['host'], '[]'));
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @return array<string, list<string>> Peer device IDs mapped to server IPs. */
    public function peerPlan(array $config, string $myId, array $connections, array $hints, array $discovery = []): array
    {
        $peers = [];
        foreach ($config['devices'] as $device) {
            $id = $device['deviceID'];
            if ($id === $myId) {
                continue;
            }
            if (!empty($device['paused']) || !empty($device['allowedNetworks'])) {
                throw new RuntimeException('Paused/restricted Syncthing peers require review');
            }
            foreach ($device['addresses'] as $value) {
                if (preg_match('/^quic[46]?:/', $value)) {
                    throw new RuntimeException('Explicit QUIC peer addresses require review');
                }
            }
            $connection = $connections[$id] ?? [];
            $ips = [];
            // Relay addresses identify the relay, never the paired server.
            if (!empty($connection['connected'])) {
                $details = array_merge([$connection['primary'] ?? [], $connection], $connection['secondary'] ?? []);
                foreach ($details as $detail) {
                    if (preg_match('/^(tcp|quic)-(client|server)$/', $detail['type'] ?? '')
                        && ($ip = $this->peerIp($detail['address'] ?? '', false)) !== null) {
                        $ips = [$ip];
                        break;
                    }
                }
            }
            if (!$ips && isset($hints[$id])) {
                $ips = array_map($this->address(...), (array) $hints[$id]);
            }
            if (!$ips) {
                $cached = in_array('dynamic', $device['addresses'], true) ? ($discovery[$id] ?? []) : [];
                $cached = $cached['addresses'] ?? $cached;
                // These are address candidates, not proof of a live connection.
                // Use only this configured device's known endpoints; Syncthing
                // still authenticates its device ID when it connects. Keep all
                // usable candidates instead of guessing which interface works.
                foreach (array_merge($device['addresses'], is_array($cached) ? $cached : []) as $endpoint) {
                    if (is_string($endpoint) && ($ip = $this->peerIp($endpoint)) !== null) {
                        $ips[] = $ip;
                    }
                }
            }
            if (!$ips) {
                throw new Update212PeerAddressUnavailable('No usable peer IP in Syncthing connections, configuration or discovery cache for '.substr($id, 0, 7));
            }
            $peers[$id] = array_values(array_unique($ips));
            sort($peers[$id]);
        }
        if (!$peers) {
            throw new RuntimeException('No configured Syncthing peers');
        }
        return $peers;
    }

    protected function pauseBeforePeerLookup(): void
    {
        sleep(5);
    }

    public function checkLocalCertificate(Update212SyncthingApi $api, string $id): void
    {
        $cert = self::read(dirname($api->config).'/cert.pem');
        $encoded = str_replace('-', '', $id);
        $digest = '';
        for ($offset = 0; $offset < 56; $offset += 14) {
            $digest .= substr($encoded, $offset, 13);
        }
        $bytes = openssl_x509_fingerprint($cert, 'sha256', true);
        $bits = '';
        foreach (str_split($bytes ?: '') as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $base32 = '';
        foreach (str_split($bits, 5) as $chunk) {
            $base32 .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec(str_pad($chunk, 5, '0'))];
        }
        if (!preg_match('/^[A-Z2-7]{56}$/', $encoded) || !$bytes || !hash_equals($base32, $digest)) {
            throw new RuntimeException('On-disk Syncthing identity differs from the running instance; refusing restart');
        }
        if (!openssl_x509_check_private_key($cert, self::read(dirname($api->config).'/key.pem'))) {
            throw new RuntimeException('Syncthing certificate and private key do not match');
        }
    }

    protected function api(string $path): Update212SyncthingApi
    {
        return new Update212SyncthingApi($path);
    }

    protected function processIdentity(int $pid): array
    {
        $status = self::read('/proc/'.$pid.'/status');
        $binary = @stat('/proc/'.$pid.'/exe');
        if (!preg_match('/^Name:\s+(\S+)$/m', $status, $name)
            || !preg_match('/^PPid:\s+(\d+)$/m', $status, $parent)
            || !preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $uid)
            || $binary === false) {
            throw new RuntimeException('Cannot identify Syncthing process '.$pid);
        }

        return ['name' => $name[1], 'parent' => (int) $parent[1], 'uid' => (int) $uid[1],
            'binary' => [$binary['dev'], $binary['ino']]];
    }

    public function assertSupervisorOwnsProcess(string $status, int $socketPid): void
    {
        if (!preg_match('/^syncthing\s+RUNNING\s+pid (\d+),/', trim($status), $supervised)) {
            throw new RuntimeException('Supervisor is not running the Syncthing program');
        }
        $supervisorPid = (int) $supervised[1];
        if ($supervisorPid === $socketPid) {
            return;
        }

        // Syncthing keeps a monitor process even with STNORESTART=1. Supervisor
        // manages that parent while its worker owns the API and sync sockets.
        $worker = $this->processIdentity($socketPid);
        $monitor = $this->processIdentity($supervisorPid);
        if ($worker['parent'] !== $supervisorPid || $worker['name'] !== 'syncthing'
            || $monitor['name'] !== 'syncthing' || $worker['uid'] !== $monitor['uid']
            || $worker['binary'] !== $monitor['binary']) {
            throw new RuntimeException('Supervisor does not own the Syncthing process being repaired'
                .' (Supervisor PID '.$supervisorPid.', socket PID '.$socketPid.')');
        }
    }

    public function prepare(array $hints): ?array
    {
        if (in_array($this->load()['phase'] ?? '', ['applying', 'rollback-failed'], true)) {
            throw new RuntimeException('An unfinished update requires manual review of the saved backup before another attempt');
        }
        $conflict = $this->detectConflict();
        if ($conflict === null) {
            echo "Syncthing: no default UDP/RTP overlap detected; no changes.\n";
            return null;
        }
        $paths = array_values(array_filter($this->configPaths, 'file_exists'));
        if (count($paths) !== 1 || is_link($paths[0])) {
            throw new RuntimeException('Cannot identify one Syncthing configuration');
        }
        $api = $this->api($paths[0]);
        $owners = [];
        foreach (explode("\n", $this->command(['ss', '-H', '-t', '-l', '-n', '-p'])['stdout']) as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (count($fields) >= 5 && str_ends_with($fields[3], ':'.$api->port)
                && preg_match_all('/\("syncthing",pid=(\d+),/', $line, $matches)) {
                array_push($owners, ...array_map('intval', $matches[1]));
            }
        }
        $owners = array_values(array_unique($owners));
        if (count($owners) !== 1 || ($conflict && $owners[0] !== $conflict['pid'])) {
            throw new RuntimeException('API and conflicting UDP sockets do not have the same owner');
        }
        if ($api->request('config/restart-required')['requiresRestart']) {
            throw new RuntimeException('Syncthing already has unrelated changes pending restart');
        }
        $config = $api->request('config');
        self::listenersWithoutQuic($config['options']);
        $myId = $api->request('system/status')['myID'];
        $this->checkLocalCertificate($api, $myId);
        for ($attempt = 0; ; $attempt++) {
            try {
                $discovery = $api->request('system/discovery');
            } catch (Throwable) {
                // Discovery is a fallback. An unavailable cache must not prevent
                // using a live connection or an explicitly configured peer IP.
                $discovery = [];
            }
            try {
                $peers = $this->peerPlan($config, $myId, $api->request('system/connections')['connections'], $hints,
                    is_array($discovery) ? $discovery : []);
                break;
            } catch (Update212PeerAddressUnavailable $error) {
                // A dynamic-only peer can briefly have neither a connection nor
                // a cached address. Refresh before any host/config changes, with
                // at most three retries. Other validation failures stop at once.
                if ($attempt >= 3) {
                    throw $error;
                }
                if ($attempt === 0) {
                    echo "Syncthing: peer address unavailable; retrying lookup up to 3 times, 5 seconds apart.\n";
                }
                $this->pauseBeforePeerLookup();
            }
        }
        $status = $this->command(['supervisorctl', 'status', 'syncthing'])['stdout'];
        $this->assertSupervisorOwnsProcess($status, $owners[0]);
        if (is_link($this->stateDir)) {
            throw new RuntimeException('Repair directory must not be a symlink');
        }
        if (!is_dir($this->stateDir) && !mkdir($this->stateDir, 0700, true)) {
            throw new RuntimeException('Cannot create repair directory');
        }
        $meta = stat($this->stateDir);
        if ($meta['uid'] !== 0 || ($meta['mode'] & 0077)) {
            throw new RuntimeException('Repair directory must be root-owned and private');
        }
        if (!is_dir($this->stateDir.'/backup') && !mkdir($this->stateDir.'/backup', 0700)) {
            throw new RuntimeException('Cannot create repair backup directory');
        }
        $state = ['phase' => 'preparing', 'started_at' => time(), 'config' => $paths[0],
            'my_id' => $myId, 'peers' => $peers, 'conflict' => $conflict];
        $this->save($state);
        $this->prepareFirewall($peers);
        echo 'Syncthing: TCP 22000 allowed from '.implode(', ', array_unique(array_merge(...array_values($peers)))).".\n";
        return [$api, $state];
    }

    public static function restoreXml(string $xml, array $snapshot): string
    {
        $document = self::xml($xml);
        $xpath = new DOMXPath($document);
        $options = $xpath->query('/configuration/options')->item(0);
        if (!$options) {
            throw new RuntimeException('Cannot restore Syncthing network settings');
        }
        $replace = function (DOMElement $parent, string $name, array $values) use ($document, $xpath): void {
            foreach (iterator_to_array($xpath->query('./'.$name, $parent)) as $node) {
                $parent->removeChild($node);
            }
            foreach ($values as $value) {
                $node = $document->createElement($name);
                $node->appendChild($document->createTextNode($value));
                $parent->appendChild($node);
            }
        };
        $replace($options, 'listenAddress', $snapshot['options']['listenAddresses']);
        $replace($options, 'localAnnounceEnabled', [$snapshot['options']['localAnnounceEnabled'] ? 'true' : 'false']);
        foreach ($xpath->query('/configuration/device') as $device) {
            if (isset($snapshot['addresses'][$device->getAttribute('id')])) {
                $replace($device, 'address', $snapshot['addresses'][$device->getAttribute('id')]);
            }
        }
        return $document->saveXML();
    }

    public function rollback(array $state): void
    {
        if (!in_array($state['phase'] ?? '', ['applying', 'rollback-failed'], true)) {
            return;
        }
        try {
            if ($this->command(['supervisorctl', 'stop', 'syncthing'], timeout: 45, check: false)['code'] !== 0) {
                $status = $this->command(['supervisorctl', 'status', 'syncthing'], check: false)['stdout'];
                if (!preg_match('/^syncthing\s+(STOPPED|FATAL)\b/', $status)) {
                    throw new RuntimeException('Cannot confirm Syncthing stopped before restoring its configuration');
                }
            }
            $path = $state['config'];
            $meta = stat($path);
            self::atomicWrite($path, self::restoreXml(self::read($path), $state['before']),
                $meta['mode'] & 0777, [$meta['uid'], $meta['gid']]);
            $this->command(['supervisorctl', 'start', 'syncthing'], timeout: 45);
            $state['phase'] = 'rolled-back';
            $state['message'] = 'Previous network settings restored; peer TCP firewall rules retained.';
            echo 'Syncthing: '.$state['message']."\n";
        } catch (Throwable $error) {
            $state['phase'] = 'rollback-failed';
            $state['message'] = 'Automatic rollback failed; inspect Supervisor and the saved config backup.';
            throw $error;
        } finally {
            $this->save($state);
        }
    }

    public function configure(Update212SyncthingApi $api, array $state): void
    {
        if ($api->request('system/status')['myID'] !== $state['my_id']) {
            throw new RuntimeException('Syncthing device identity changed');
        }
        $this->checkLocalCertificate($api, $state['my_id']);
        if ($api->request('config/restart-required')['requiresRestart']) {
            throw new RuntimeException('Unrelated Syncthing changes are awaiting restart');
        }
        $config = $api->request('config');
        // Accept backups from the original update, which stored one IP per
        // peer. Use the prepared addresses even if a connection changes while
        // the firewall is being saved; no second live connection is required.
        $state['peers'] = array_map(fn ($ips) => (array) $ips, $state['peers']);
        if ($this->peerPlan($config, $state['my_id'], [], $state['peers']) != $state['peers']) {
            throw new RuntimeException('Peer membership changed during repair');
        }
        $target = ['listenAddresses' => self::listenersWithoutQuic($config['options']), 'localAnnounceEnabled' => false];
        $addresses = [];
        foreach ($config['devices'] as $device) {
            if (isset($state['peers'][$device['deviceID']])) {
                $addresses[$device['deviceID']] = $device['addresses'];
            }
        }
        $state['before'] = ['options' => array_intersect_key($config['options'], $target), 'addresses' => $addresses];
        self::atomicWrite($this->stateDir.'/backup/config.xml', self::read($api->config));
        $state['phase'] = 'applying';
        $this->save($state); // Journal precedes every configuration mutation.
        try {
            foreach ($state['peers'] as $id => $ips) {
                $api->request('config/devices/'.$id, ['addresses' => array_values(array_unique(array_merge(array_map(self::tcpUrl(...), $ips), $addresses[$id])))]);
            }
            $api->request('config/options', $target);
            $this->command(['supervisorctl', 'restart', 'syncthing'], timeout: 45);
            // Return after the local restart. Syncthing handles peer reconnection.
            $state['phase'] = 'complete';
            $state['message'] = 'TCP networking configured; Syncthing restarted.';
            $this->save($state);
            echo 'Syncthing: '.$state['message']."\n";
        } catch (Throwable $error) {
            $this->rollback($state);
            throw $error;
        }
    }

    public function repair(array $hints = []): void
    {
        try {
            $prepared = $this->prepare($hints);
            if ($prepared !== null) {
                $this->configure(...$prepared);
            }
        } catch (Throwable $error) {
            $state = $this->load();
            if (in_array($state['phase'] ?? '', ['preparing', 'rolled-back', 'rollback-failed'], true)) {
                if ($state['phase'] === 'preparing') {
                    $state['phase'] = 'failed';
                    $state['message'] = 'Repair stopped before Syncthing configuration changes.';
                }
                $state['error'] = $error->getMessage();
                $this->save($state);
            }
            throw $error;
        }
    }
}

class Update212PeerAddressUnavailable extends RuntimeException {}

class Update212SyncthingApi
{
    public int $port;
    protected string $url;
    protected string $key;

    public function __construct(public string $config)
    {
        $xpath = new DOMXPath(Update212::xml(Update212::read($config)));
        $gui = $xpath->query('/configuration/gui')->item(0);
        if (!$gui || ($gui->hasAttribute('enabled') && $gui->getAttribute('enabled') !== 'true')
            || ($gui->hasAttribute('tls') && $gui->getAttribute('tls') !== 'false')) {
            throw new RuntimeException('This repair requires the standard local HTTP Syncthing API');
        }
        $binding = $xpath->evaluate('string(./address)', $gui);
        if (!preg_match('/^(127\.0\.0\.1|0\.0\.0\.0|\[::\]|\[::1\]):(\d+)$/', $binding, $matches)
            || (int) $matches[2] < 1 || (int) $matches[2] > 65535) {
            throw new RuntimeException('Nonstandard Syncthing GUI binding requires review');
        }
        $this->port = (int) $matches[2];
        $this->url = 'http://'.($matches[1] === '[::1]' ? '[::1]' : '127.0.0.1').':'.$this->port.'/rest/';
        $this->key = $xpath->evaluate('string(./apikey)', $gui);
        if ($this->key === '' || strpbrk($this->key, "\r\n") !== false) {
            throw new RuntimeException('Syncthing API key is missing or invalid');
        }
    }

    public function request(string $path, ?array $data = null, ?string $method = null): mixed
    {
        $curl = curl_init($this->url.$path);
        try {
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP, CURLOPT_CUSTOMREQUEST => $method ?? ($data === null ? 'GET' : 'PATCH'),
                CURLOPT_HTTPHEADER => ['X-API-Key: '.$this->key, 'Content-Type: application/json']]);
            if ($data !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
            }
            $body = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($body === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Local Syncthing API request failed for '.$path.' (HTTP '.$status.')');
            }
            return $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            curl_close($curl);
        }
    }
}
