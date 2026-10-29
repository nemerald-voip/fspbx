<?php

namespace Tests\Unit;

use Closure;
use DOMXPath;
use App\Console\Commands\Updates\Update212SyncthingApi as SyncthingApi;
use App\Console\Commands\Updates\Update212 as Repair;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../app/Console/Commands/Updates/Update212.php';

class FakeSyncthingApi extends SyncthingApi
{
    public array $options = ['listenAddresses' => ['default'], 'localAnnounceEnabled' => true,
        'relaysEnabled' => true, 'globalAnnounceEnabled' => true, 'natEnabled' => true,
        'announceLANAddresses' => true, 'maxRecvKbps' => 123];
    public array $devices = [['deviceID' => 'PEER', 'addresses' => ['dynamic']],
        ['deviceID' => 'SELF', 'addresses' => ['dynamic']]];
    public array $calls = [];
    public bool $afterRestart = false;
    public bool $failPatch = false;
    public bool $restartRequired = false;

    public function __construct(public string $config)
    {
        $this->port = 8384;
    }

    public function request(string $path, ?array $data = null, ?string $method = null): mixed
    {
        TestCase::assertFalse($this->afterRestart, 'Repair must not poll the API after restarting Syncthing');
        $this->calls[] = [$path, $data];
        if ($data !== null) {
            if ($path === 'config/options') {
                if ($this->failPatch) {
                    throw new RuntimeException('API unavailable');
                }
                $this->options = array_replace($this->options, $data);
            } elseif ($path === 'config/devices/PEER') {
                $this->devices[0] = array_replace($this->devices[0], $data);
            } else {
                TestCase::fail('Unexpected API mutation: '.$path);
            }
            file_put_contents($this->config, Repair::restoreXml(file_get_contents($this->config),
                ['options' => $this->options, 'addresses' => ['PEER' => $this->devices[0]['addresses']]]));
            return null;
        }
        return match ($path) {
            'system/status' => ['myID' => 'SELF'],
            'config/restart-required' => ['requiresRestart' => $this->restartRequired],
            'config' => ['options' => $this->options, 'devices' => $this->devices],
            // A disconnected peer is intentional: the retained address hint is
            // enough once preparation has identified it. No reconnect wait.
            'system/connections' => ['connections' => ['PEER' => ['connected' => false]]],
            default => throw new RuntimeException('Unexpected API read: '.$path),
        };
    }
}

class SyncthingRepairHarness extends Repair
{
    public ?Closure $run = null;
    public array $commands = [];
    public ?SyncthingApi $fakeApi = null;
    public bool $overrideDetection = false;
    public ?array $conflict = null;

    public function command(array $args, ?string $input = null, int $timeout = 15, bool $check = true): array
    {
        $this->commands[] = $args;
        if ($this->run === null) {
            throw new RuntimeException('Unexpected host command: '.implode(' ', $args));
        }
        return ($this->run)($args, $input, $timeout, $check);
    }

    public function detectConflict(): ?array
    {
        return $this->overrideDetection ? $this->conflict : parent::detectConflict();
    }

    protected function api(string $path): SyncthingApi
    {
        return $this->fakeApi ?? parent::api($path);
    }

    public function checkLocalCertificate(SyncthingApi $api, string $id): void {}
}

class SyncthingNetworkRepairTest extends TestCase
{
    private const IP = '172.30.10.12';
    private const CORE = '<configuration name="switch.conf"><settings><!-- <param name="rtp-start-port" value="10000"/> --></settings></configuration>';
    private const SS = 'UNCONN 0 0 0.0.0.0:21027 0.0.0.0:* users:(("syncthing",pid=123,fd=38))';
    private const XML = '<configuration><device id="PEER"><address>dynamic</address></device><gui enabled="true" tls="false"><address>127.0.0.1:8384</address><apikey>secret</apikey></gui><options><listenAddress>default</listenAddress><localAnnounceEnabled>true</localAnnounceEnabled><globalAnnounceEnabled>true</globalAnnounceEnabled><natEnabled>true</natEnabled></options><folder id="recordings"><path>/keep/recordings</path></folder></configuration>';
    private string $root;
    private SyncthingRepairHarness $repair;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/syncthing-php-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->repair = new SyncthingRepairHarness($this->root.'/state', $this->root.'/rules', [$this->root.'/config.xml']);
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        (new Filesystem())->deleteDirectory($this->root);
    }

    public static function result(string $stdout = '', int $code = 0): array
    {
        return ['stdout' => $stdout, 'stderr' => '', 'code' => $code];
    }

    private function fixture(): array
    {
        mkdir($this->repair->stateDir.'/backup', 0700, true);
        file_put_contents($this->root.'/config.xml', self::XML);
        $api = new FakeSyncthingApi($this->root.'/config.xml');
        $state = ['phase' => 'preparing', 'my_id' => 'SELF', 'peers' => ['PEER' => self::IP], 'config' => $api->config];
        $this->repair->save($state);
        return [$api, $state];
    }

    private function detection(string $core = self::CORE, string $sockets = self::SS): string
    {
        mkdir($this->root.'/fs/autoload_configs', 0700, true);
        $path = $this->root.'/fs/autoload_configs/switch.conf.xml';
        file_put_contents($path, $core);
        touch($path, 1);
        $this->repair->run = fn ($args) => match ($args[0]) {
            'ss' => self::result($sockets),
            'pgrep' => self::result((string) getmypid()),
            'getconf' => (new Repair())->command($args),
            'fs_cli' => self::result($args[2] === 'global_getvar conf_dir' ? $this->root.'/fs' : $core),
            default => throw new RuntimeException('Unexpected command'),
        };
        return $path;
    }

    public function test_default_range_ignores_comments_and_accepts_explicit_params(): void
    {
        $this->assertSame([16384, 32768], Repair::configuredRange(self::CORE));
        $this->assertSame([24000, 32768], Repair::configuredRange(str_replace('</settings>',
            '<param name="rtp-start-port" value="24000"/></settings>', self::CORE)));
    }

    /** @dataProvider invalidRanges */
    public function test_ambiguous_ranges_are_rejected(string $settings): void
    {
        $this->expectException(RuntimeException::class);
        Repair::configuredRange(str_replace('</settings>', $settings.'</settings>', self::CORE));
    }

    public static function invalidRanges(): array
    {
        return [['<param name="rtp-start-port" value="$${start}"/>'],
            [str_repeat('<param name="rtp-start-port" value="10000"/>', 2)],
            ['<param name="rtp-start-port" value="40000"/>']];
    }

    public function test_detects_live_owned_udp_overlap(): void
    {
        $this->detection();
        $this->assertSame(['range' => [16384, 32768], 'ports' => [21027], 'pid' => 123], $this->repair->detectConflict());
    }

    public function test_nonoverlapping_rtp_range_is_skipped(): void
    {
        $this->detection(str_replace('</settings>', '<param name="rtp-start-port" value="24000"/></settings>', self::CORE));
        $this->assertNull($this->repair->detectConflict());
    }

    /** @dataProvider unaffectedSockets */
    public function test_unaffected_hosts_have_no_side_effects(string $sockets): void
    {
        $this->repair->run = fn ($args) => self::result($sockets);
        $this->repair->repair();
        $this->assertSame([['ss', '-H', '-u', '-a', '-n', '-p']], $this->repair->commands);
        $this->assertDirectoryDoesNotExist($this->repair->stateDir);
    }

    public static function unaffectedSockets(): array
    {
        return [[''], [str_replace('syncthing', 'freeswitch', self::SS)],
            [str_replace('0.0.0.0:21027', '127.0.0.1:21027', self::SS)]];
    }

    public function test_changed_core_config_is_not_guessed(): void
    {
        $path = $this->detection();
        touch($path, time() + 10);
        $this->expectExceptionMessage('after startup');
        try {
            $this->repair->repair();
        } finally {
            $this->assertDirectoryDoesNotExist($this->repair->stateDir);
        }
    }

    public function test_default_listeners_preserve_relay_and_custom_tcp(): void
    {
        $this->assertSame(['tcp://0.0.0.0:22000', Repair::RELAY], Repair::listenersWithoutQuic(['listenAddresses' => ['default']]));
        $this->assertSame(['tcp://[::]:22000', 'relay://example.test:443'], Repair::listenersWithoutQuic([
            'listenAddresses' => ['tcp://[::]:22000', 'quic://0.0.0.0:22000', 'relay://example.test:443']]));
    }

    /** @dataProvider unsupportedListeners */
    public function test_unsupported_listeners_require_review(array $listeners): void
    {
        $this->expectException(RuntimeException::class);
        Repair::listenersWithoutQuic(['listenAddresses' => $listeners]);
    }

    public static function unsupportedListeners(): array
    {
        return [[['unknown://host']], [['quic://0.0.0.0:22000']]];
    }

    public function test_connection_formats_keep_the_authenticated_private_ip(): void
    {
        [$api] = $this->fixture();
        $connection = ['connected' => true, 'type' => 'tcp-server', 'address' => self::IP.':50001'];
        foreach ([$connection, ['connected' => true, 'primary' => $connection]] as $value) {
            $this->assertSame(['PEER' => self::IP], $this->repair->peerPlan($api->request('config'), 'SELF', ['PEER' => $value], ['PEER' => '198.51.100.1']));
        }
    }

    public function test_relay_ip_cannot_become_a_firewall_source(): void
    {
        [$api] = $this->fixture();
        $connection = ['connected' => true, 'type' => 'relay-client', 'address' => '192.0.2.44:443'];
        $this->assertSame(['PEER' => self::IP], $this->repair->peerPlan($api->request('config'), 'SELF', ['PEER' => $connection], ['PEER' => self::IP]));
        $this->expectExceptionMessage('No authenticated direct address');
        $this->repair->peerPlan($api->request('config'), 'SELF', ['PEER' => $connection], []);
    }

    public function test_paused_devices_require_review(): void
    {
        [$api] = $this->fixture();
        $api->devices[0]['paused'] = true;
        $this->expectExceptionMessage('Paused/restricted');
        $this->repair->peerPlan($api->request('config'), 'SELF', [], ['PEER' => self::IP]);
    }

    public function test_ipv6_and_mapped_ipv4_addresses(): void
    {
        $this->assertSame(self::IP, $this->repair->address('::ffff:172.30.10.12'));
        $this->assertSame('2001:db8::1', $this->repair->address('2001:db8:0:0::1'));
        $this->assertSame('tcp://[2001:db8::1]:22000', Repair::tcpUrl('2001:db8::1'));
    }

    /** @dataProvider unsafeAddresses */
    public function test_unsafe_addresses_are_rejected(string $ip): void
    {
        $this->expectException(RuntimeException::class);
        $this->repair->address($ip);
    }

    public static function unsafeAddresses(): array
    {
        return [['0.0.0.0'], ['127.0.0.1'], ['ff02::1'], ['fe80::1'], ['::1'], ['169.254.1.1'], ['1.2.3.4; echo bad']];
    }

    public function test_persistent_rule_preserves_other_tables_and_accepts_quoted_comments(): void
    {
        $original = "*filter\n:INPUT DROP [0:0]\n-A INPUT -p udp --dport 16384:32768 -j ACCEPT\nCOMMIT\n*mangle\n:OUTPUT ACCEPT [0:0]\nCOMMIT\n";
        $rule = $this->repair->firewallRule(self::IP);
        $updated = Repair::addPersistentRule($original, $rule);
        $this->assertSame($updated, Repair::addPersistentRule($updated, $rule));
        $quoted = str_replace('fspbx-syncthing', '"fspbx-syncthing"', $updated);
        $this->assertSame($quoted, Repair::addPersistentRule($quoted, $rule));
        $this->assertLessThan(strpos($updated, '--dport 16384'), strpos($updated, '--dport 22000'));
        $this->assertStringEndsWith("*mangle\n:OUTPUT ACCEPT [0:0]\nCOMMIT\n", $updated);
    }

    /** @dataProvider invalidRules */
    public function test_invalid_saved_firewalls_are_rejected(string $rules): void
    {
        $this->expectException(RuntimeException::class);
        Repair::addPersistentRule($rules, $this->repair->firewallRule(self::IP));
    }

    public static function invalidRules(): array
    {
        return [[''], ["*filter\n:INPUT DROP [0:0]\n"], ["*filter\nCOMMIT\n*filter\nCOMMIT\n"]];
    }

    /** @dataProvider firewallCases */
    public function test_firewall_validation_precedes_changes_and_rules_are_repeatable(bool $fail): void
    {
        $this->fixture();
        mkdir($this->repair->rulesDir);
        $path = $this->repair->rulesDir.'/rules.v4';
        $original = "*filter\n:INPUT DROP [0:0]\n-A INPUT -p tcp --dport 22 -j ACCEPT\nCOMMIT\n";
        file_put_contents($path, $original);
        $live = false;
        $this->repair->run = function ($args, $input) use ($fail, &$live) {
            if ($args[0] === 'systemctl') {
                return self::result($args[1] === 'is-enabled' ? 'enabled' : '', $args[1] === 'is-enabled' ? 0 : 3);
            }
            if ($args[0] === 'iptables-restore') {
                $this->assertStringContainsString('--dport 22000', $input);
                if ($fail) {
                    throw new RuntimeException('Invalid saved rules');
                }
            } elseif ($args[0] === 'iptables' && $args[3] === '-C') {
                return self::result('', $live ? 0 : 1);
            } elseif ($args[0] === 'iptables' && $args[3] === '-I') {
                $live = true;
            } else {
                $this->fail('Unexpected firewall command');
            }
            return self::result();
        };
        if ($fail) {
            $this->expectExceptionMessage('Invalid saved rules');
        }
        try {
            $this->repair->prepareFirewall(['PEER' => self::IP]);
            $this->repair->prepareFirewall(['PEER' => self::IP]);
        } finally {
            if ($fail) {
                $this->assertFalse($live);
                $this->assertSame($original, file_get_contents($path));
            } else {
                $this->assertTrue($live);
                $this->assertSame(1, substr_count(file_get_contents($path), '--dport 22000'));
                $this->assertSame($original, file_get_contents($this->repair->stateDir.'/backup/rules.v4'));
                $this->assertCount(1, array_filter($this->repair->commands, fn ($args) => ($args[3] ?? null) === '-I'));
            }
        }
    }

    public static function firewallCases(): array { return [[false], [true]]; }

    public function test_repair_returns_after_local_restart_without_reconnection_checks(): void
    {
        [$api, $state] = $this->fixture();
        $this->repair->run = function ($args) use ($api) {
            $this->assertSame(['supervisorctl', 'restart', 'syncthing'], $args);
            $api->afterRestart = true; // Any further API call fails the test.
            return self::result();
        };
        $this->repair->configure($api, $state);
        $this->assertSame('complete', $this->repair->load()['phase']);
        $this->assertSame(['tcp://0.0.0.0:22000', Repair::RELAY], $api->options['listenAddresses']);
        $this->assertFalse($api->options['localAnnounceEnabled']);
        foreach (['relaysEnabled', 'globalAnnounceEnabled', 'natEnabled', 'announceLANAddresses'] as $key) {
            $this->assertTrue($api->options[$key]);
        }
        $this->assertSame(123, $api->options['maxRecvKbps']);
        $this->assertSame(['tcp://172.30.10.12:22000', 'dynamic'], $api->devices[0]['addresses']);
        $this->assertCount(1, $this->repair->commands);
        $this->assertStringContainsString('/keep/recordings', file_get_contents($api->config));
        $this->assertStringNotContainsString('reconnected', $this->repair->load()['message']);
    }

    /** @dataProvider failureCases */
    public function test_local_failures_restore_only_owned_network_settings(bool $apiFailure): void
    {
        [$api, $state] = $this->fixture();
        $api->failPatch = $apiFailure;
        $this->repair->run = function ($args) use ($api) {
            if ($args[1] === 'restart') {
                file_put_contents($api->config, str_replace('/keep/recordings', '/new/recordings', file_get_contents($api->config)));
                throw new RuntimeException('Restart failed');
            }
            $this->assertContains($args[1], ['stop', 'start']);
            return self::result();
        };
        $this->expectExceptionMessage($apiFailure ? 'API unavailable' : 'Restart failed');
        try {
            $this->repair->configure($api, $state);
        } finally {
            $this->assertSame('rolled-back', $this->repair->load()['phase']);
            $xml = new \DOMXPath(Repair::xml(file_get_contents($api->config)));
            $this->assertSame('default', $xml->evaluate('string(//options/listenAddress)'));
            $this->assertSame('true', $xml->evaluate('string(//options/localAnnounceEnabled)'));
            $this->assertSame('dynamic', $xml->evaluate('string(//device/address)'));
            $this->assertSame($apiFailure ? '/keep/recordings' : '/new/recordings', $xml->evaluate('string(//folder/path)'));
            $this->assertSame('secret', $xml->evaluate('string(//gui/apikey)'));
        }
    }

    public static function failureCases(): array { return [[false], [true]]; }

    public function test_rollback_handles_an_already_stopped_service(): void
    {
        [$api, $state] = $this->fixture();
        $state['phase'] = 'applying';
        $state['before'] = ['options' => $api->options, 'addresses' => ['PEER' => ['dynamic']]];
        $this->repair->run = fn ($args) => match ($args[1]) {
            'stop' => self::result('', 2),
            'status' => self::result('syncthing STOPPED Not started', 3),
            'start' => self::result(),
        };
        $this->repair->rollback($state);
        $this->assertSame('rolled-back', $this->repair->load()['phase']);
    }

    public function test_interrupted_repair_requires_rollback_before_a_new_attempt(): void
    {
        [, $state] = $this->fixture();
        $state['phase'] = 'applying';
        $this->repair->save($state);
        $this->expectExceptionMessage('manual review');
        try {
            $this->repair->repair();
        } finally {
            $this->assertSame([], $this->repair->commands);
        }
    }

    public function test_supervisor_must_own_the_process_being_repaired(): void
    {
        [$api] = $this->fixture();
        $this->repair->fakeApi = $api;
        $this->repair->overrideDetection = true;
        $this->repair->conflict = ['pid' => 123];
        $this->repair->run = fn ($args) => self::result(match ($args[0]) {
            'ss' => 'LISTEN 0 4096 *:8384 *:* users:(("syncthing",pid=123,fd=34))',
            'supervisorctl' => 'syncthing RUNNING pid 456, uptime 1:00:00',
            default => throw new RuntimeException('Unexpected command'),
        });
        $this->expectExceptionMessage('Supervisor does not own');
        $this->repair->repair(['PEER' => self::IP]);
    }

    public function test_existing_restart_requirement_is_preserved(): void
    {
        [$api, $state] = $this->fixture();
        $api->restartRequired = true;
        $this->expectExceptionMessage('awaiting restart');
        try {
            $this->repair->configure($api, $state);
        } finally {
            $this->assertSame([], $this->repair->commands);
            $this->assertSame(self::XML, file_get_contents($api->config));
        }
    }

    public function test_subprocess_does_not_interpret_arguments_as_shell_code(): void
    {
        $value = '`false`; $(false) "a b"';
        $result = (new Repair())->command([PHP_BINARY, '-r', 'echo $argv[1], stream_get_contents(STDIN);', $value], input: str_repeat('x', 100000));
        $this->assertSame($value.str_repeat('x', 100000), $result['stdout']);
        $this->assertSame(7, (new Repair())->command([PHP_BINARY, '-r', 'exit(7);'], check: false)['code']);
    }

    public function test_subprocess_timeout_is_enforced(): void
    {
        $this->expectExceptionMessage('timed out');
        (new Repair())->command([PHP_BINARY, '-r', 'sleep(10);'], timeout: 1);
    }

    private function initializeConfig(string $path): void
    {
        $installer = file_get_contents(__DIR__.'/../../install/install_syncthing.sh');
        preg_match("/<<'SYNCTHING_SETUP_PHP'\\n(.*?)\\nSYNCTHING_SETUP_PHP/s", $installer, $matches);
        $this->assertArrayHasKey(1, $matches);
        // Execute only the installer's offline PHP block, never its host setup.
        (new Repair())->command([PHP_BINARY, '/dev/stdin', '--initialize-config', $path], input: $matches[1]);
    }

    public function test_fresh_configuration_is_ready_without_starting_a_service(): void
    {
        file_put_contents($this->root.'/config.xml', <<<'XML'
<configuration version="37">
    <device id="LOCAL"><address>dynamic</address></device>
    <folder id="default" path="/home/www-data/Sync"/>
    <gui enabled="true"><address>127.0.0.1:8384</address><apikey>keep-key</apikey></gui>
    <options>
        <listenAddress>default</listenAddress>
        <localAnnounceEnabled>true</localAnnounceEnabled>
        <globalAnnounceEnabled>true</globalAnnounceEnabled>
        <relaysEnabled>true</relaysEnabled>
        <natEnabled>true</natEnabled>
        <maxSendKbps>123</maxSendKbps>
    </options>
</configuration>
XML);
        chmod($this->root.'/config.xml', 0640);
        $meta = stat($this->root.'/config.xml');
        $this->initializeConfig($this->root.'/config.xml');

        $xpath = new DOMXPath(Repair::xml(file_get_contents($this->root.'/config.xml')));
        $this->assertSame(['tcp://0.0.0.0:22000', Repair::RELAY], array_map(
            fn ($node) => $node->textContent,
            iterator_to_array($xpath->query('/configuration/options/listenAddress')),
        ));
        foreach (['localAnnounceEnabled' => 'false', 'globalAnnounceEnabled' => 'true',
            'relaysEnabled' => 'true', 'natEnabled' => 'true', 'maxSendKbps' => '123'] as $name => $value) {
            $this->assertSame($value, $xpath->evaluate('string(/configuration/options/'.$name.')'));
        }
        $this->assertSame('LOCAL', $xpath->evaluate('string(/configuration/device/@id)'));
        $this->assertSame('/home/www-data/Sync', $xpath->evaluate('string(/configuration/folder/@path)'));
        $this->assertSame('keep-key', $xpath->evaluate('string(/configuration/gui/apikey)'));
        clearstatcache(true, $this->root.'/config.xml');
        $this->assertSame($meta['mode'], fileperms($this->root.'/config.xml'));
        $this->assertSame($meta['uid'], fileowner($this->root.'/config.xml'));
        $this->assertSame($meta['gid'], filegroup($this->root.'/config.xml'));
        $this->assertSame(['config.xml'], array_values(array_diff(scandir($this->root), ['.', '..'])));

        $before = $xpath->document->C14N();
        $this->initializeConfig($this->root.'/config.xml');
        $this->assertSame($before, Repair::xml(file_get_contents($this->root.'/config.xml'))->C14N());
    }

    /** @dataProvider invalidConfigurations */
    public function test_invalid_configuration_is_left_untouched(string $xml): void
    {
        file_put_contents($this->root.'/config.xml', $xml);
        try {
            $this->initializeConfig($this->root.'/config.xml');
            $this->fail('Invalid configuration was accepted');
        } catch (RuntimeException $expected) {
            $this->assertSame($xml, file_get_contents($this->root.'/config.xml'));
        }
    }

    public static function invalidConfigurations(): array
    {
        return [
            ['<configuration/>'],
            ['<configuration><options/><options/></configuration>'],
            ['<!DOCTYPE configuration [<!ENTITY x "value">]><configuration><options/></configuration>'],
        ];
    }
}
