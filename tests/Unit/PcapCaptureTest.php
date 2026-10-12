<?php

namespace Tests\Unit;

use App\Models\PcapSession;
use App\Services\Pcap\PcapExtractor;
use App\Services\Pcap\PcapService;
use App\Services\Pcap\SipTraceCollector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PcapCaptureTest extends TestCase
{
    private function makeSession(array $attributes = []): PcapSession
    {
        return new PcapSession(array_merge([
            'directions' => ['inbound', 'outbound', 'local'],
            'scope' => 'all',
            'started_at' => Carbon::createFromTimestamp(1000),
            'expires_at' => Carbon::createFromTimestamp(5000),
            'stopped_at' => null,
        ], $attributes));
    }

    private function leg(array $attributes = []): array
    {
        return array_merge([
            'channel_uuid' => 'a',
            'direction' => 'inbound',
            'numbers' => [],
            'created_epoch' => 2000,
            'hangup_epoch' => 2100,
        ], $attributes);
    }

    public function test_leg_is_built_from_a_hangup_event(): void
    {
        $headers = [
            'Unique-ID' => 'b-leg',
            'Channel-Call-UUID' => 'a-leg',
            'variable_domain_uuid' => '7fb63858-d8e6-41b3-a7a9-d8318c5e182d',
            'variable_call_direction' => 'inbound',
            'variable_dialed_user' => '200',
            'variable_extension_uuid' => 'a984468b-618c-4c32-8265-7d22abf8a220',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '200',
            'variable_sip_call_id' => 'abc@1.2.3.4',
            'variable_local_media_port' => '22088',
            'variable_remote_media_ip' => '192.168.10.174',
            'variable_remote_media_port' => '4016',
            'variable_start_epoch' => '1791685578',
            'variable_end_epoch' => '1791685580',
        ];

        $headers['Other-Leg-Unique-ID'] = '8d6b2b0e-1f6e-4c41-9d1e-1d8a5c9b7e10';
        $headers['variable_originator'] = '8d6b2b0e-1f6e-4c41-9d1e-1d8a5c9b7e10';
        $headers['Call-Direction'] = 'outbound';
        $leg = PcapService::legFromEvent(fn ($name) => $headers[$name] ?? null, 'pbx1');

        $this->assertSame(['8d6b2b0e-1f6e-4c41-9d1e-1d8a5c9b7e10'], $leg['linked_uuids']);
        $this->assertSame('outbound', $leg['channel_direction']);
        $this->assertSame('a-leg', $leg['call_uuid']);
        $this->assertSame('b-leg', $leg['channel_uuid']);
        $this->assertSame('pbx1', $leg['node']);
        $this->assertSame(22088, $leg['local_media_port']);
        $this->assertSame(1791685578, $leg['created_epoch']);
        $this->assertSame(1791685580, $leg['hangup_epoch']);
        $this->assertContains('200', $leg['numbers']);
        $this->assertContains('a984468b-618c-4c32-8265-7d22abf8a220', $leg['numbers']);
    }

    public function test_leg_without_call_uuid_uses_its_own_uuid_and_rejects_bad_values(): void
    {
        $headers = [
            'Unique-ID' => 'only',
            'variable_domain_uuid' => 'not-a-uuid',
            'variable_local_media_port' => 'abc',
        ];

        $leg = PcapService::legFromEvent(fn ($name) => $headers[$name] ?? null, 'pbx1');

        $this->assertSame('only', $leg['call_uuid']);
        $this->assertNull($leg['domain_uuid']);
        $this->assertNull($leg['local_media_port']);
        $this->assertNull(PcapService::legFromEvent(fn () => null, 'pbx1'));
    }

    public function test_legs_are_grouped_into_calls_by_linked_uuids(): void
    {
        $legs = [
            ['channel_uuid' => 'a1', 'linked_uuids' => ['b1'], 'created_epoch' => 100, 'channel_direction' => 'inbound'],
            ['channel_uuid' => 'b1', 'linked_uuids' => ['a1'], 'created_epoch' => 100, 'channel_direction' => 'outbound'],
            // A ring group fork that only knows its originator.
            ['channel_uuid' => 'b2', 'linked_uuids' => ['a1'], 'created_epoch' => 101, 'channel_direction' => 'outbound'],
            ['channel_uuid' => 'x1', 'linked_uuids' => [], 'created_epoch' => 100, 'channel_direction' => 'inbound'],
        ];

        $groups = PcapService::groupLegs($legs);
        $ids = array_map(fn ($group) => array_column($group, 'channel_uuid'), $groups);

        $this->assertCount(2, $groups);
        $this->assertContains(['a1', 'b1', 'b2'], $ids);
        $this->assertContains(['x1'], $ids);
        // Same start time: the leg FreeSWITCH received is the caller's leg.
        $this->assertSame('a1', PcapService::firstLeg([$legs[1], $legs[2], $legs[0]])['channel_uuid']);
    }

    public function test_session_matches_call_types(): void
    {
        $session = $this->makeSession(['directions' => ['outbound', 'local']]);

        $this->assertFalse(PcapService::matches($session, $this->leg(['direction' => 'inbound']), [], []));
        $this->assertTrue(PcapService::matches($session, $this->leg(['direction' => 'local']), [], []));
    }

    public function test_session_matches_its_time_window(): void
    {
        $session = $this->makeSession(['stopped_at' => Carbon::createFromTimestamp(3000)]);

        $this->assertFalse(PcapService::matches($session, $this->leg(['created_epoch' => 999]), [], []));
        $this->assertTrue(PcapService::matches($session, $this->leg(['created_epoch' => 2999]), [], []));
        $this->assertFalse(PcapService::matches($session, $this->leg(['created_epoch' => 3001]), [], []));

        $expired = $this->makeSession();
        $this->assertTrue(PcapService::matches($expired, $this->leg(['created_epoch' => 5000]), [], []));
        $this->assertFalse(PcapService::matches($expired, $this->leg(['created_epoch' => 5001]), [], []));
    }

    public function test_session_matches_selected_extensions_on_any_leg(): void
    {
        $session = $this->makeSession(['scope' => 'extensions']);
        $aLeg = $this->leg(['numbers' => ['+15551234567', '9000']]);
        $bLeg = $this->leg(['channel_uuid' => 'b', 'numbers' => ['201']]);

        $this->assertTrue(PcapService::matches($session, $aLeg, [$aLeg, $bLeg], ['201', 'uuid-201']));
        $this->assertFalse(PcapService::matches($session, $aLeg, [$aLeg, $bLeg], ['202']));
    }

    public function test_display_filter_covers_call_ids_media_ports_and_time(): void
    {
        $legs = [
            ['sip_call_id' => 'abc@1.2.3.4', 'local_media_port' => 22088],
            ['sip_call_id' => 'bad"id', 'local_media_port' => 22090],
            ['sip_call_id' => null, 'local_media_port' => null],
        ];

        $filter = PcapExtractor::displayFilter(PcapExtractor::callIds($legs), PcapExtractor::mediaPorts($legs), 100, 200);

        $this->assertSame(
            '(sip.Call-ID == "abc@1.2.3.4" || udp.port == 22088 || udp.port == 22089 || udp.port == 22090 || udp.port == 22091)'
            . ' && frame.time_epoch >= 100 && frame.time_epoch <= 200',
            $filter
        );
        $this->assertSame('tcp or udp portrange 5060-5091 or udp port 22088', PcapExtractor::preFilter([22088]));
    }

    public function test_spool_chunks_are_selected_by_time(): void
    {
        $dir = sys_get_temp_dir() . '/pcap-test-' . uniqid();
        File::ensureDirectoryExists($dir);
        foreach ([1000, 1060, 1120, 1180] as $start) {
            touch("{$dir}/{$start}.pcap");
        }

        try {
            $extractor = new PcapExtractor($dir, $dir);
            $names = fn (array $paths) => array_map(fn ($path) => basename($path), $paths);

            $this->assertSame(['1060.pcap', '1120.pcap'], $names($extractor->chunksFor(1070, 1130)));
            // The newest file is still open, so it covers everything after it starts.
            $this->assertSame(['1180.pcap'], $names($extractor->chunksFor(5000, 6000)));
            $this->assertSame([], $names($extractor->chunksFor(10, 900)));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_packets_are_counted_and_a_partial_last_packet_is_ignored(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pcap');
        $header = pack('VvvVVVV', 0xa1b2c3d4, 2, 4, 0, 0, 65535, 1);
        $packet = fn (int $length) => pack('VVVV', 1, 0, $length, $length) . str_repeat("\0", $length);
        file_put_contents($path, $header . $packet(10) . $packet(20) . substr($packet(30), 0, 25));

        try {
            $this->assertSame(2, PcapExtractor::countPackets($path));
        } finally {
            @unlink($path);
        }
    }

    private function hepPacket(string $callId): string
    {
        $chunk = fn (int $type, string $value) => pack('nnn', 0, $type, 6 + strlen($value)) . $value;
        $sip = "INVITE sip:200@192.168.10.231 SIP/2.0\r\nCall-ID: {$callId}\r\nCSeq: 1 INVITE\r\nContent-Length: 0\r\n\r\n";
        $body = $chunk(1, chr(2)) . $chunk(2, chr(6))
            . $chunk(3, inet_pton('192.168.10.174')) . $chunk(4, inet_pton('192.168.10.231'))
            . $chunk(7, pack('n', 56967)) . $chunk(8, pack('n', 5061))
            . $chunk(9, pack('N', 1500)) . $chunk(10, pack('N', 250000))
            . $chunk(11, chr(1)) . $chunk(15, $sip);

        return 'HEP3' . pack('n', 6 + strlen($body)) . $body;
    }

    public function test_hep_sip_is_rebuilt_as_udp_packets_for_missing_call_ids(): void
    {
        // Two HEP packets to 127.0.0.1:9060 inside Linux cooked v2 frames, as tcpdump -i any writes them.
        $frame = function (string $hep) {
            $udp = pack('nnnn', 40000, 9060, 8 + strlen($hep), 0) . $hep;
            $ip = pack('CCnnnCCn', 0x45, 0, 20 + strlen($udp), 0, 0, 64, 17, 0) . inet_pton('127.0.0.1') . inet_pton('127.0.0.1');
            $data = pack('nnNnCC', 0x0800, 0, 1, 772, 0, 6) . str_repeat("\0", 8) . $ip . $udp;

            return pack('VVVV', 1500, 300000, strlen($data), strlen($data)) . $data;
        };

        $source = tempnam(sys_get_temp_dir(), 'pcap');
        $output = tempnam(sys_get_temp_dir(), 'pcap');
        file_put_contents($source, pack('VvvVVVV', 0xa1b2c3d4, 2, 4, 0, 0, 262144, 276)
            . $frame($this->hepPacket('tls-call')) . $frame($this->hepPacket('other-call')));

        try {
            $this->assertSame(1, PcapExtractor::writeHepSip($source, $output, 9060, ['tls-call'], 1000, 2000));
            $this->assertSame(1, PcapExtractor::countPackets($output));

            $written = file_get_contents($output);
            $this->assertSame(276, unpack('V', substr($written, 20, 4))[1]);
            $this->assertStringContainsString('Call-ID: tls-call', $written);
            $this->assertStringNotContainsString('other-call', $written);
            // Original addresses and HEP time are kept.
            $this->assertStringContainsString(inet_pton('192.168.10.174') . inet_pton('192.168.10.231'), $written);
            $this->assertSame([1500, 250000], array_values(unpack('V2', substr($written, 24, 8))));

            $this->assertSame(0, PcapExtractor::writeHepSip($source, $output, 9060, ['tls-call'], 2000, 3000));
        } finally {
            @unlink($source);
            @unlink($output);
        }
    }

    public function test_hep_parser_rejects_other_data(): void
    {
        $this->assertNull(PcapExtractor::parseHep('not hep'));
        $hep = PcapExtractor::parseHep($this->hepPacket('abc'));
        $this->assertSame(4, $hep['family']);
        $this->assertSame(5061, $hep['dst_port']);
    }

    private function traceEntry(string $head, array $lines): string
    {
        $bytes = array_sum(array_map(fn ($line) => strlen($line) + 2, $lines));

        return sprintf($head, $bytes) . "\n" . str_repeat('-', 72) . "\n" . implode("\n", $lines) . "\n";
    }

    public function test_sip_trace_entries_are_parsed_with_bodies_and_dates(): void
    {
        $invite = ['INVITE sip:200@192.168.10.231 SIP/2.0', 'Call-ID: tls-1', 'Content-Type: application/sdp', 'Content-Length: 13', '', 'v=0', 'o=- 1 1'];
        $log = "2026-10-11 23:59:58.100000 99.00% [DEBUG] sofia.c:1 something\n"
            . $this->traceEntry('recv %d bytes from tls/[192.168.10.174]:56967 at 23:59:59.500000:', $invite)
            . "\n"
            . $this->traceEntry('send %d bytes to udp/[192.168.10.50]:5060 at 00:00:01.250000:', ['SIP/2.0 200 OK', 'Call-ID: udp-1', 'Content-Length: 0', ''])
            // Still being written: the byte count is not reached yet.
            . "send 900 bytes to tls/[192.168.10.174]:56967 at 00:00:02.000000:\n" . str_repeat('-', 72) . "\nSIP/2.0 180 Ringing\n";

        $tz = new \DateTimeZone('America/Toronto');
        $parsed = SipTraceCollector::parse($log, $tz, '2026-10-01');

        $this->assertCount(2, $parsed['entries']);
        [$first, $second] = $parsed['entries'];

        $this->assertSame(['recv', 'tls', '192.168.10.174', 56967], [$first['direction'], $first['transport'], $first['ip'], $first['port']]);
        $this->assertSame((new \DateTimeImmutable('2026-10-11 23:59:59', $tz))->getTimestamp(), $first['seconds']);
        $this->assertSame(500000, $first['micros']);
        $this->assertStringContainsString("Content-Length: 13\r\n\r\nv=0\r\no=- 1 1\r\n", $first['message']);

        // After midnight the date moves to the next day.
        $this->assertSame((new \DateTimeImmutable('2026-10-12 00:00:01', $tz))->getTimestamp(), $second['seconds']);
        $this->assertSame('udp', $second['transport']);

        // Reading resumes at the incomplete entry.
        $this->assertSame(strpos($log, 'send 900 bytes'), $parsed['consumed']);
    }

    public function test_sip_trace_packets_use_freeswitch_tls_port_from_via(): void
    {
        $dir = sys_get_temp_dir() . '/pcap-trace-' . uniqid();
        File::ensureDirectoryExists($dir);
        $log = "{$dir}/freeswitch.log";
        // FreeSWITCH omits the Via port, which means 5061 for TLS.
        file_put_contents($log, "2026-10-11 23:23:00.000000 [DEBUG] x\n" . $this->traceEntry(
            'send %d bytes to tls/[192.168.10.190]:12440 at 23:23:04.186291:',
            ['INVITE sip:200@192.168.10.190:12440;transport=TLS SIP/2.0', 'Via: SIP/2.0/TLS 192.168.10.231;branch=z9hG4bK1', 'Call-ID: b-leg', 'Content-Length: 0', '']
        ));

        $listeners = [
            ['family' => 4, 'ip' => '192.168.10.231', 'port' => 5081, 'internal' => false],
            ['family' => 4, 'ip' => '192.168.10.231', 'port' => 5061, 'internal' => true],
        ];
        // Never touch the server's real cache.
        config(['cache.default' => 'array']);
        $collector = new SipTraceCollector($log, "{$dir}/sip", $listeners, new \DateTimeZone('America/Toronto'));

        try {
            \Illuminate\Support\Facades\Cache::forever('pcap:siptrace_offset', ['inode' => stat($log)['ino'], 'offset' => 0]);
            $this->assertSame(1, $collector->collect(276));

            $written = file_get_contents(glob("{$dir}/sip/*.pcap")[0]);
            // Linux cooked v2 (20) + IPv4 (20): UDP ports follow.
            $this->assertSame([5061, 12440], array_values(unpack('n2', substr($written, 24 + 16 + 40, 4))));
        } finally {
            $collector->stop();
            File::deleteDirectory($dir);
        }
    }

    public function test_durations_set_the_expiry(): void
    {
        $start = Carbon::parse('2026-10-11 12:00:00');

        $this->assertEquals(Carbon::parse('2026-10-11 16:00:00'), PcapService::expiresAt('4h', $start));
        $this->assertEquals(Carbon::parse('2026-10-18 12:00:00'), PcapService::expiresAt('1w', $start));
        $this->assertEquals(Carbon::parse('2026-10-13 12:00:00'), PcapService::expiresAt('48h', $start));
    }

    public function test_files_are_kept_for_the_chosen_hours_up_to_72(): void
    {
        $end = Carbon::parse('2026-10-11 16:00:00');

        $this->assertEquals(Carbon::parse('2026-10-12 16:00:00'), PcapService::deleteAt($end, 24));
        $this->assertEquals(Carbon::parse('2026-10-14 16:00:00'), PcapService::deleteAt($end, 72));
        $this->assertEquals(Carbon::parse('2026-10-14 16:00:00'), PcapService::deleteAt($end, 500));
    }
}
