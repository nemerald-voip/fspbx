<?php

/**
 * Opt-in PHP lab using disposable loopback instances and folders.
 * Usage: php tests/Unit/run-syncthing-network-lab.php /path/to/syncthing-v1 /path/to/syncthing-v2
 * No host firewall, Supervisor, FreeSWITCH, or application update is run.
 * Only this test observes file transfers; the repair never waits for a peer.
 */

use App\Console\Commands\Updates\Update212SyncthingApi as SyncthingApi;
use App\Console\Commands\Updates\Update212 as Repair;

require_once __DIR__.'/../../app/Console/Commands/Updates/Update212.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function waitFor(callable $condition, string $message, int $seconds = 75): void
{
    $deadline = microtime(true) + $seconds;
    do {
        try {
            if ($condition()) {
                return;
            }
        } catch (RuntimeException $error) {
            // Local API startup and file scans are asynchronous in this lab.
        }
        usleep(250000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException($message);
}

function removeLab(string $directory): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($directory);
}

class SyncthingLabNode
{
    public string $binary;
    public string $home;
    public string $data;
    public string $ip;
    public string $id;
    public int $udp;
    public SyncthingApi $api;
    public bool $failRestartOnce = false;
    private $process = null;

    public function __construct(public string $directory, string $binary, int $index)
    {
        mkdir($directory, 0700, true);
        check(is_file($binary) && is_executable($binary), 'Pass existing executable Syncthing binaries');
        $this->binary = $directory.'/syncthing';
        // The monitor launches its resolved executable path. Use the installed
        // basename for both processes, rather than the versioned download name.
        check(copy($binary, $this->binary) && chmod($this->binary, 0700), 'Cannot prepare the lab executable');
        $this->home = $directory.'/config';
        $this->data = $directory.'/data';
        mkdir($this->data, 0700);
        mkdir($directory.'/repair/backup', 0700, true);
        $this->ip = '127.0.0.'.($index + 2);
        $this->udp = 45001 + $index;
        $port = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        check($port !== false, 'Cannot allocate a lab GUI port');
        $apiAddress = stream_socket_get_name($port, false);
        fclose($port);
        (new Repair())->command([$this->binary, 'generate', '--home='.$this->home], timeout: 20);
        $doc = Repair::xml(Repair::read($this->home.'/config.xml'));
        $xpath = new DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('/configuration/folder')) as $folder) {
            $folder->parentNode->removeChild($folder);
        }
        $options = $xpath->query('/configuration/options')->item(0);
        // B initially accepts only 22001. A cannot dial B:22000, so B must
        // initiate their TCP connection. This models a one-way firewall path.
        foreach ([
            'listenAddress' => ['tcp://'.$this->ip.':'.($index === 0 ? 22000 : 22001), 'quic://'.$this->ip.':'.$this->udp],
            'globalAnnounceEnabled' => ['false'], 'localAnnounceEnabled' => ['false'],
            'relaysEnabled' => ['false'], 'natEnabled' => ['false'], 'urAccepted' => ['-1'],
            'crashReportingEnabled' => ['false'], 'autoUpgradeIntervalH' => ['0'],
            'stunServer' => ['127.0.0.1:1'], 'stunKeepaliveMinS' => ['0'], 'reconnectionIntervalS' => ['5'],
        ] as $name => $values) {
            foreach (iterator_to_array($xpath->query('./'.$name, $options)) as $node) {
                $options->removeChild($node);
            }
            foreach ($values as $value) {
                $options->appendChild($doc->createElement($name, $value));
            }
        }
        $xpath->query('/configuration/gui/address')->item(0)->nodeValue = $apiAddress;
        $xpath->query('/configuration/gui')->item(0)->setAttribute('tls', 'false');
        $doc->save($this->home.'/config.xml');
        $this->api = new SyncthingApi($this->home.'/config.xml');
    }

    public function start(): void
    {
        $this->process = proc_open([$this->binary, 'serve', '--home='.$this->home, '--no-browser', '--no-restart', '--no-upgrade'],
            [['file', '/dev/null', 'r'], ['file', $this->directory.'/syncthing.log', 'a'],
                ['file', $this->directory.'/syncthing.log', 'a']], $pipes, null,
            array_merge(getenv(), ['STNOUPGRADE' => '1', 'STNORESTART' => '1']));
        check(is_resource($this->process), 'Could not start a lab node');
        waitFor(fn () => $this->api->request('system/status'), 'Lab API did not start', 30);
        $this->id = $this->api->request('system/status')['myID'];
    }

    public function pid(): int
    {
        return proc_get_status($this->process)['pid'];
    }

    public function workerPid(): int
    {
        $sockets = Repair::udpSockets((new Repair())->command(['ss', '-H', '-u', '-a', '-n', '-p'])['stdout']);
        $owners = array_values(array_unique(array_column(array_filter($sockets,
            fn ($socket) => $socket[0] === $this->ip && $socket[1] === $this->udp), 2)));
        check(count($owners) === 1, 'Cannot identify the lab socket owner');
        return $owners[0];
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process);
            $deadline = microtime(true) + 15;
            while (proc_get_status($this->process)['running'] && microtime(true) < $deadline) {
                usleep(100000);
            }
            if (proc_get_status($this->process)['running']) {
                proc_terminate($this->process, 9);
            }
        }
        proc_close($this->process);
        $this->process = null;
    }

    public function pair(self $peer): void
    {
        $device = $this->api->request('config/defaults/device');
        $device['deviceID'] = $peer->id;
        $device['addresses'] = [Repair::tcpUrl($peer->ip)];
        $this->api->request('config/devices/'.$peer->id, $device, 'PUT');
        $folder = $this->api->request('config/defaults/folder');
        // PHP's associative JSON decode turns an empty object into []; this
        // field is a map in Syncthing's full-folder PUT schema.
        $folder['versioning']['params'] = (object) ($folder['versioning']['params'] ?? []);
        $folder = array_replace($folder, ['id' => 'lab', 'path' => $this->data, 'rescanIntervalS' => 1,
            'devices' => [['deviceID' => $this->id], ['deviceID' => $peer->id]]]);
        $this->api->request('config/folders/lab', $folder, 'PUT');
    }

    public function command(array $args): array
    {
        check(array_slice($args, 0, 1) === ['supervisorctl'] && ($args[2] ?? '') === 'syncthing',
            'Unexpected host command in the lab');
        if (in_array($args[1], ['stop', 'restart'], true)) {
            $this->stop();
        }
        if ($args[1] === 'restart' && $this->failRestartOnce) {
            $this->failRestartOnce = false;
            throw new RuntimeException('Simulated local restart failure');
        }
        if (in_array($args[1], ['start', 'restart'], true)) {
            $this->start();
        }
        return ['code' => 0, 'stdout' => '', 'stderr' => ''];
    }
}

class SyncthingLabRepair extends Repair
{
    public function __construct(private SyncthingLabNode $node, private SyncthingLabNode $peer)
    {
        parent::__construct($node->directory.'/repair');
    }

    public function address(string $value): string
    {
        // Loopback is allowed only inside this disposable test.
        check(filter_var($value, FILTER_VALIDATE_IP) !== false, 'Invalid lab IP');
        return $value;
    }

    public function prepare(array $hints): ?array
    {
        // Exercise real monitor/worker ownership; only the Supervisor response
        // is simulated. Skip host firewall/FreeSWITCH preparation in this lab.
        $this->assertSupervisorOwnsProcess('syncthing RUNNING pid '.$this->node->pid().', uptime 0:00:01',
            $this->node->workerPid());
        return [$this->node->api, ['phase' => 'preparing', 'my_id' => $this->node->id,
            'peers' => [$this->peer->id => $this->peer->ip], 'config' => $this->node->api->config]];
    }

    public function command(array $args, ?string $input = null, int $timeout = 15, bool $check = true): array
    {
        return $this->node->command($args);
    }
}

if (count($argv) !== 3) {
    fwrite(STDERR, "Usage: php run-syncthing-network-lab.php /path/to/syncthing-v1 /path/to/syncthing-v2\n");
    exit(1);
}
$root = sys_get_temp_dir().'/fspbx-syncthing-php-lab-'.bin2hex(random_bytes(8));
$nodes = [];
$failed = false;
try {
    foreach (array_slice($argv, 1) as $index => $binary) {
        $node = new SyncthingLabNode($root.'/'.$index, $binary, $index);
        $nodes[] = $node;
        $node->start();
    }
    [$left, $right] = $nodes;
    $left->pair($right);
    $right->pair($left);
    file_put_contents($left->data.'/before.txt', 'before conversion');
    waitFor(fn () => @file_get_contents($right->data.'/before.txt') === 'before conversion', 'Initial sync failed');
    $before = $left->api->request('config/options');
    $repair = new SyncthingLabRepair($left, $right);
    // Prove identity checking uses the live certificate rather than accepting
    // any local key, then exercise rollback of an actual local restart failure.
    try {
        $repair->checkLocalCertificate($left->api, implode('-', array_fill(0, 8, 'AAAAAAA')));
        throw new LogicException('Wrong device identity was accepted');
    } catch (RuntimeException $expected) {
        check(str_contains($expected->getMessage(), 'identity differs'), 'Unexpected identity failure');
    }
    $left->failRestartOnce = true;
    try {
        $repair->repair();
        throw new LogicException('Failed restart did not trigger rollback');
    } catch (RuntimeException $expected) {
        check($expected->getMessage() === 'Simulated local restart failure', 'Unexpected rollback failure: '.$expected->getMessage());
    }
    check($repair->load()['phase'] === 'rolled-back', 'Rollback result missing');
    check($left->api->request('config/options') === $before, 'Rollback changed unrelated settings');
    echo "PHP local restart failure and configuration rollback passed.\n";

    $rightBefore = $right->api->request('config/options');
    $rightPid = $right->pid();
    $repair->repair();
    check($repair->load()['phase'] === 'complete', 'First node did not complete');
    check($right->pid() === $rightPid && $right->api->request('config/options') === $rightBefore, 'First repair changed the second node');
    file_put_contents($left->data.'/between-left.txt', 'A updated');
    file_put_contents($right->data.'/between-right.txt', 'B unchanged');
    waitFor(fn () => @file_get_contents($right->data.'/between-left.txt') === 'A updated', 'A-to-B sync failed');
    waitFor(fn () => @file_get_contents($left->data.'/between-right.txt') === 'B unchanged', 'B-to-A sync failed');
    echo "PHP first-node conversion and file transfers with the unchanged peer passed.\n";

    $leftPid = $left->pid();
    // Model opening B's TCP listener when its own update runs.
    $right->api->request('config/options', ['listenAddresses' => [Repair::tcpUrl($right->ip), 'quic://'.$right->ip.':'.$right->udp]]);
    $right->stop();
    $right->start();
    $second = new SyncthingLabRepair($right, $left);
    $second->repair();
    check($second->load()['phase'] === 'complete', 'Second node did not complete');
    check($left->pid() === $leftPid, 'Second repair restarted the first node');
    foreach ($nodes as $node) {
        $options = $node->api->request('config/options');
        check($options['listenAddresses'] === [Repair::tcpUrl($node->ip)], 'Unexpected listeners');
        check(!$options['localAnnounceEnabled'] && !$options['globalAnnounceEnabled'] && !$options['relaysEnabled'], 'Unrelated options changed');
    }
    file_put_contents($right->data.'/after.txt', 'after conversion');
    waitFor(fn () => @file_get_contents($left->data.'/after.txt') === 'after conversion', 'Final file transfer failed');
    echo "PHP sequential conversion lab passed on both Syncthing versions.\n";
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, $error->getMessage()."\n");
    foreach ($nodes as $node) {
        fwrite(STDERR, substr(@file_get_contents($node->directory.'/syncthing.log') ?: '', -2500)."\n");
    }
} finally {
    foreach ($nodes as $node) {
        $node->stop();
    }
    if (is_dir($root)) {
        removeLab($root);
    }
}
exit($failed ? 1 : 0);
