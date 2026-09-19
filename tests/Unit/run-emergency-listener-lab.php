<?php
/** Opt-in transport test. Temporary FreeSWITCH only; no real calls, alerts or DB. */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\FreeswitchEslService;
use Illuminate\Container\Container;
use Psr\Log\NullLogger;

function ensure(bool $condition, string $message): void
{
    if (! $condition) { throw new RuntimeException($message); }
}

function acknowledged($connection, string $command): void
{
    $reply = $connection->sendRecv($command);
    ensure($reply && str_starts_with((string) $reply->getHeader('Reply-Text'), '+OK'), 'Lab command rejected');
}

class EndEmergencyListenerLab extends RuntimeException {}

$container = new Container();
$container->instance('log', new NullLogger());
Container::setInstance($container);
ensure(class_exists('ESLconnection'), 'PHP ESL extension required');
pcntl_async_signals(true);
pcntl_signal(SIGALRM, fn () => throw new RuntimeException('Lab event timeout'), false);
$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
ensure((bool) $probe, 'Cannot allocate a lab socket: '.$error);
$port = substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$lab = sys_get_temp_dir().'/fspbx-emergency-listener-'.bin2hex(random_bytes(6));
$secret = bin2hex(random_bytes(24));
$args = ['/usr/bin/freeswitch', '-nf', '-nonat', '-nosql', '-nocal', '-np'];
foreach (['conf', 'log', 'run', 'db', 'scripts', 'temp', 'recordings', 'storage', 'cache'] as $directory) {
    mkdir($lab.'/'.$directory, 0700, true);
    array_push($args, '-'.$directory, $lab.'/'.$directory);
}
file_put_contents($lab.'/conf/freeswitch.xml', <<<XML
<document type="freeswitch/xml"><section name="configuration">
<configuration name="modules.conf"><modules><load module="mod_event_socket"/></modules></configuration>
<configuration name="event_socket.conf"><settings>
<param name="listen-ip" value="127.0.0.1"/><param name="listen-port" value="{$port}"/>
<param name="password" value="{$secret}"/></settings></configuration>
</section><section name="dialplan"/><section name="directory"/></document>
XML);
$process = proc_open($args, [0 => ['file', '/dev/null', 'r'],
    1 => ['file', $lab.'/console.log', 'w'], 2 => ['redirect', 1]], $pipes);
ensure(is_resource($process), 'Cannot start disposable FreeSWITCH');
$connections = [];
try {
    $sender = null;
    for ($i = 0; $i < 100; $i++) {
        ensure(proc_get_status($process)['running'], 'Lab FreeSWITCH exited; inspect '.$lab.'/console.log');
        try {
            $candidate = new ESLconnection('127.0.0.1', $port, $secret);
            if ($candidate->connected()) { $sender = $candidate; break; }
        } catch (Exception $error) { /* Wait for the lab socket to open. */ }
        usleep(100000);
    }
    ensure((bool) $sender, 'Lab startup timed out');
    $connections[] = $sender;
    $baseline = new ESLconnection('127.0.0.1', $port, $secret);
    $connections[] = $baseline;
    acknowledged($baseline, 'event plain ALL');

    $cases = [
        // Direction is relative to FreeSWITCH, not the eventual PSTN route.
        'phone-dials-outside' => [true, 'CHANNEL_CREATE', ['variable_direction' => 'inbound', 'variable_call_direction' => 'outbound', 'Caller-Destination-Number' => '911']],
        'alternate-emergency-number' => [true, 'CHANNEL_CREATE', ['variable_direction' => 'inbound', 'Caller-Destination-Number' => '112']],
        'ordinary-inbound' => [true, 'CHANNEL_CREATE', ['variable_direction' => 'inbound', 'Caller-Destination-Number' => '100']],
        'carrier-leg' => [false, 'CHANNEL_CREATE', ['variable_direction' => 'outbound', 'Caller-Destination-Number' => '911']],
        'notification-leg' => [false, 'CHANNEL_CREATE', ['variable_direction' => 'outbound', 'Caller-Destination-Number' => '100']],
        'missing-direction' => [false, 'CHANNEL_CREATE', ['Caller-Destination-Number' => '911']],
        'wrong-variable' => [false, 'CHANNEL_CREATE', ['variable_call_direction' => 'inbound', 'Caller-Destination-Number' => '911']],
        'different-event-type' => [false, 'CHANNEL_ANSWER', ['variable_direction' => 'inbound', 'Caller-Destination-Number' => '911']],
    ];
    $expected = array_keys(array_filter($cases, fn ($case) => $case[0])); sort($expected);
    $all = array_keys($cases); sort($all);
    foreach (['initial', 'reconnected'] as $round) {
        $connection = new ESLconnection('127.0.0.1', $port, $secret);
        $connections[] = $connection;
        $service = new class($connection) extends FreeswitchEslService {
            public function __construct($connection) { $this->conn = $connection; }
        };
        ensure($service->subscribeToEvents('plain', 'CHANNEL_CREATE', ['variable_direction' => 'inbound']), 'Subscription failed');
        foreach ($cases as $name => [$allowed, $kind, $headers]) {
            $wire = 'sendevent '.$kind."\nEmergency-Lab-Case: ".$name;
            foreach ($headers as $header => $value) { $wire .= "\n".$header.': '.$value; }
            acknowledged($sender, $wire);
        }
        acknowledged($sender, "sendevent CHANNEL_CREATE\nvariable_direction: inbound\nEmergency-Lab-Case: end");
        $received = [];
        pcntl_alarm(5);
        try {
            $service->listen(function ($event) use (&$received) {
                $name = $event->getHeader('Emergency-Lab-Case');
                if ($name === 'end') { throw new EndEmergencyListenerLab(); }
                if ($name) { $received[] = $name; }
            });
        } catch (EndEmergencyListenerLab $done) {
            // End this finite test through the shared listener's disconnect path.
        } finally { pcntl_alarm(0); }
        $unfiltered = [];
        pcntl_alarm(5);
        try {
            while ($event = $baseline->recvEvent()) {
                $name = $event->getHeader('Emergency-Lab-Case');
                if ($name === 'end') { break; }
                if ($name) { $unfiltered[] = $name; }
            }
        } finally { pcntl_alarm(0); }
        sort($received); sort($unfiltered);
        file_put_contents($lab.'/'.$round.'.json', json_encode(compact('expected', 'received', 'all', 'unfiltered'), JSON_PRETTY_PRINT));
        ensure($received === $expected, 'Wrong emergency event set; inspect '.$lab.'/'.$round.'.json');
        ensure($unfiltered === $all, 'Other listener lost events; inspect '.$lab.'/'.$round.'.json');
        echo 'PASS '.$round.': 3 inbound create cases delivered; 5 other cases excluded; second listener received all 8'.PHP_EOL;
    }
} finally {
    foreach ($connections as $connection) { $connection->disconnect(); }
    proc_terminate($process);
    for ($i = 0; $i < 50 && proc_get_status($process)['running']; $i++) { usleep(100000); }
    if (proc_get_status($process)['running']) { proc_terminate($process, 9); }
    proc_close($process);
    echo 'Lab evidence: '.$lab.PHP_EOL;
}
