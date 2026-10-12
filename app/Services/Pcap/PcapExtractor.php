<?php

namespace App\Services\Pcap;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Cuts one call's packets out of the rolling capture spool.
 *
 * The capture service writes SIP and RTP into one-minute files named {start-epoch}.pcap.
 * A call is the SIP dialogs of its legs (by Call-ID) plus the RTP/RTCP on FreeSWITCH's
 * media ports for those legs, limited to the call's time window.
 */
class PcapExtractor
{
    /**
     * @param array<string, string> $tools full paths for tcpdump, mergecap and tshark
     * @param int|null $hepPort UDP port of FreeSWITCH's HEP (SIP Capture) stream, when it is on
     */
    public function __construct(private string $spoolDir, private string $workDir, private array $tools = [], private ?int $hepPort = null)
    {
    }

    /**
     * Spool files whose packets may fall inside [$from, $to].
     *
     * @return string[]
     */
    public function chunksFor(int $from, int $to): array
    {
        $chunks = [];
        foreach (glob(rtrim($this->spoolDir, '/') . '/*.pcap') ?: [] as $path) {
            $start = (int) basename($path, '.pcap');
            if ($start > 0) {
                $chunks[$start] = $path;
            }
        }
        ksort($chunks);

        $starts = array_keys($chunks);
        $selected = [];
        foreach ($starts as $index => $start) {
            // A file runs until the next one starts; the newest one is still being written.
            $end = $starts[$index + 1] ?? PHP_INT_MAX;
            if ($start <= $to && $end >= $from) {
                $selected[] = $chunks[$start];
            }
        }

        return $selected;
    }

    /**
     * SIP trace files (decrypted TLS signalling) named {first}-{last}-{id}.pcap
     * that overlap [$from, $to].
     *
     * @return string[]
     */
    public function traceFilesFor(int $from, int $to): array
    {
        $files = [];
        foreach (glob(rtrim($this->spoolDir, '/') . '/sip/*.pcap') ?: [] as $path) {
            if (preg_match('/^(\d+)-(\d+)-/', basename($path), $m) && (int) $m[1] <= $to && (int) $m[2] >= $from) {
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * FreeSWITCH-side RTP ports and their RTCP companions.
     *
     * @return int[]
     */
    public static function mediaPorts(iterable $legs): array
    {
        $ports = [];
        foreach ($legs as $leg) {
            $port = (int) ($leg['local_media_port'] ?? 0);
            if ($port > 0 && $port < 65535) {
                $ports[] = $port;
                $ports[] = $port + 1;
            }
        }

        return array_values(array_unique($ports));
    }

    /**
     * Call-IDs that are safe to put inside a quoted Wireshark filter string.
     *
     * @return string[]
     */
    public static function callIds(iterable $legs): array
    {
        $ids = [];
        foreach ($legs as $leg) {
            $id = trim((string) ($leg['sip_call_id'] ?? ''));
            if ($id !== '' && preg_match('/^[\x21-\x7E]+$/', $id) && !str_contains($id, '"') && !str_contains($id, '\\')) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fast tcpdump pre-filter: all SIP plus this call's media ports.
     */
    public static function preFilter(array $ports, ?int $hepPort = null): string
    {
        $parts = ['tcp', 'udp portrange ' . PcapService::SIP_PORT_RANGE];
        if ($hepPort) {
            $parts[] = 'udp port ' . $hepPort;
        }
        foreach ($ports as $port) {
            $parts[] = 'udp port ' . (int) $port;
        }

        return implode(' or ', $parts);
    }

    /**
     * Exact Wireshark filter: this call's SIP dialogs and media inside its time window.
     */
    public static function displayFilter(array $callIds, array $ports, int $from, int $to): string
    {
        $match = [];
        foreach ($callIds as $id) {
            $match[] = 'sip.Call-ID == "' . $id . '"';
        }
        foreach ($ports as $port) {
            $match[] = 'udp.port == ' . (int) $port;
        }

        return '(' . implode(' || ', $match) . ') && frame.time_epoch >= ' . $from . ' && frame.time_epoch <= ' . $to;
    }

    /**
     * Write the call's packets to $output and return the packet count.
     */
    public function extract(array $legs, int $from, int $to, string $output): int
    {
        $callIds = self::callIds($legs);
        $ports = self::mediaPorts($legs);
        if (!$callIds && !$ports) {
            return 0;
        }

        $chunks = $this->chunksFor($from, $to);
        $traces = $this->traceFilesFor($from, $to);
        if (!$chunks && !$traces) {
            return 0;
        }

        $tmp = rtrim($this->workDir, '/') . '/' . Str::uuid();
        File::ensureDirectoryExists($tmp, 0770);

        try {
            $parts = [];
            foreach ($chunks as $index => $chunk) {
                $part = "{$tmp}/part{$index}.pcap";
                // The newest file is still open, so tcpdump may report a truncated
                // file after copying every complete packet. Keep whatever it wrote.
                $this->run(['tcpdump', '-n', '-r', $chunk, '-w', $part, self::preFilter($ports, $this->hepPort)], false);
                if (is_file($part) && self::countPackets($part) > 0) {
                    $parts[] = $part;
                }
            }
            // Trace files only hold SIP, so they skip the pre-filter.
            $parts = array_merge($parts, $traces);

            if (!$parts) {
                return 0;
            }

            $merged = $parts[0];
            if (count($parts) > 1) {
                $merged = "{$tmp}/merged.pcap";
                $this->run(array_merge(['mergecap', '-F', 'pcap', '-w', $merged], $parts));
            }

            File::ensureDirectoryExists(dirname($output), 0770);
            $plain = "{$tmp}/plain.pcap";
            $this->run(['tshark', '-n', '-r', $merged, '-Y', self::displayFilter($callIds, $ports, $from, $to), '-F', 'pcap', '-w', $plain]);

            // SIP over TLS is encrypted on the wire. Fill in readable SIP for
            // those dialogs from FreeSWITCH's HEP copy when SIP Capture is on.
            $hepFile = null;
            if ($this->hepPort) {
                $missing = array_values(array_diff($callIds, $this->callIdsIn($plain)));
                if ($missing) {
                    $hepFile = "{$tmp}/hep.pcap";
                    if (self::writeHepSip($merged, $hepFile, $this->hepPort, $missing, $from, $to) === 0) {
                        $hepFile = null;
                    }
                }
            }

            if ($hepFile) {
                $this->run(['mergecap', '-F', 'pcap', '-w', $output, $plain, $hepFile]);
            } else {
                File::move($plain, $output);
            }

            return is_file($output) ? self::countPackets($output) : 0;
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /**
     * Call-IDs of the readable SIP in a capture file.
     *
     * @return string[]
     */
    private function callIdsIn(string $path): array
    {
        $tshark = $this->tools['tshark'] ?? 'tshark';
        $process = new Process([$tshark, '-n', '-r', $path, '-Y', 'sip', '-T', 'fields', '-e', 'sip.Call-ID'], null, null, null, 120);
        $process->run();

        return array_values(array_unique(array_filter(array_map('trim', explode("\n", $process->getOutput())))));
    }

    /**
     * Rebuild HEP-carried SIP for the given Call-IDs as ordinary UDP packets with
     * their original addresses and times, in the same link type as $source.
     * Returns the number of packets written.
     */
    public static function writeHepSip(string $source, string $output, int $hepPort, array $callIds, int $from, int $to): int
    {
        $linkType = null;
        $packets = [];
        foreach (self::readPackets($source, $linkType) as [$seconds, $micros, $frame]) {
            $udp = self::udpPayload($frame, $linkType, $hepPort);
            if ($udp === null || ($hep = self::parseHep($udp)) === null) {
                continue;
            }

            $time = $hep['seconds'] ?? $seconds;
            if ($time < $from || $time > $to || !preg_match('/^(?:Call-ID|i)[ \t]*:[ \t]*(\S+)/mi', $hep['payload'], $match)) {
                continue;
            }
            if (!in_array($match[1], $callIds, true)) {
                continue;
            }

            $ip = self::ipUdpPacket($hep);
            if ($ip !== null) {
                $packets[] = [$time, $hep['micros'] ?? $micros, self::linkHeader($linkType, $hep['family']) . $ip];
            }
        }

        if (!$packets || $linkType === null) {
            return 0;
        }

        $handle = fopen($output, 'wb');
        fwrite($handle, pack('VvvVVVV', 0xa1b2c3d4, 2, 4, 0, 0, 262144, $linkType));
        foreach ($packets as [$sec, $usec, $data]) {
            fwrite($handle, pack('VVVV', $sec, $usec, strlen($data), strlen($data)) . $data);
        }
        fclose($handle);

        return count($packets);
    }

    /**
     * Decode a HEPv3 packet into addresses, ports, time and SIP payload.
     */
    public static function parseHep(string $data): ?array
    {
        if (strlen($data) < 6 || substr($data, 0, 4) !== 'HEP3') {
            return null;
        }

        $total = min(strlen($data), unpack('n', substr($data, 4, 2))[1]);
        $hep = ['family' => null];
        for ($offset = 6; $offset + 6 <= $total;) {
            ['vendor' => $vendor, 'type' => $type, 'length' => $length] = unpack('nvendor/ntype/nlength', substr($data, $offset, 6));
            if ($length < 6 || $offset + $length > $total) {
                break;
            }
            $value = substr($data, $offset + 6, $length - 6);
            $offset += $length;
            if ($vendor !== 0) {
                continue;
            }

            match ($type) {
                1 => $hep['family'] = ord($value) === 10 ? 6 : 4,
                3, 5 => $hep['src'] = $value,
                4, 6 => $hep['dst'] = $value,
                7 => $hep['src_port'] = unpack('n', $value)[1],
                8 => $hep['dst_port'] = unpack('n', $value)[1],
                9 => $hep['seconds'] = unpack('N', $value)[1],
                10 => $hep['micros'] = unpack('N', $value)[1],
                11 => $hep['protocol'] = ord($value),
                15 => $hep['payload'] = $value,
                default => null,
            };
        }

        // Protocol type 1 is SIP.
        if (($hep['protocol'] ?? 1) !== 1 || !isset($hep['payload'], $hep['src'], $hep['dst'], $hep['src_port'], $hep['dst_port'])) {
            return null;
        }
        $hep['family'] ??= strlen($hep['src']) === 16 ? 6 : 4;

        return $hep;
    }

    /**
     * @return \Generator<int, array{0: int, 1: int, 2: string}>
     */
    private static function readPackets(string $path, ?int &$linkType): \Generator
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        try {
            $header = fread($handle, 24);
            if (strlen($header) < 24) {
                return;
            }
            $format = match (bin2hex(substr($header, 0, 4))) {
                'd4c3b2a1', '4d3cb2a1' => 'V',
                'a1b2c3d4', 'a1b23c4d' => 'N',
                default => null,
            };
            if ($format === null) {
                return;
            }
            $linkType = unpack($format, substr($header, 20, 4))[1] & 0xFFFF;

            while (strlen($record = fread($handle, 16)) === 16) {
                $values = unpack("{$format}seconds/{$format}micros/{$format}length", substr($record, 0, 12));
                $frame = $values['length'] > 0 ? fread($handle, $values['length']) : '';
                if (strlen($frame) !== $values['length']) {
                    break;
                }
                yield [$values['seconds'], $values['micros'], $frame];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * UDP payload of a frame when it is addressed to $port.
     */
    private static function udpPayload(string $frame, ?int $linkType, int $port): ?string
    {
        $offset = match ($linkType) {
            1 => 14,      // Ethernet
            113 => 16,    // Linux cooked
            276 => 20,    // Linux cooked v2
            101, 228 => 0, // Raw IPv4
            default => null,
        };
        if ($offset === null || strlen($frame) < $offset + 20) {
            return null;
        }

        $version = ord($frame[$offset]) >> 4;
        if ($version === 4) {
            $headerLength = (ord($frame[$offset]) & 0x0F) * 4;
            if (ord($frame[$offset + 9]) !== 17) {
                return null;
            }
        } elseif ($version === 6 && strlen($frame) >= $offset + 48) {
            $headerLength = 40;
            if (ord($frame[$offset + 6]) !== 17) {
                return null;
            }
        } else {
            return null;
        }

        $udp = $offset + $headerLength;
        if (strlen($frame) < $udp + 8 || unpack('n', substr($frame, $udp + 2, 2))[1] !== $port) {
            return null;
        }

        return substr($frame, $udp + 8);
    }

    public static function ipUdpPacket(array $hep): ?string
    {
        $payload = $hep['payload'];
        $udp = pack('nnnn', $hep['src_port'], $hep['dst_port'], 8 + strlen($payload), 0) . $payload;

        if ($hep['family'] === 6) {
            if (strlen($hep['src']) !== 16 || strlen($hep['dst']) !== 16) {
                return null;
            }
            return pack('NnCC', 0x60000000, strlen($udp), 17, 64) . $hep['src'] . $hep['dst'] . $udp;
        }

        if (strlen($hep['src']) !== 4 || strlen($hep['dst']) !== 4 || 20 + strlen($udp) > 65535) {
            return null;
        }
        $header = pack('CCnnnCCn', 0x45, 0, 20 + strlen($udp), 0, 0x4000, 64, 17, 0) . $hep['src'] . $hep['dst'];
        $sum = 0;
        foreach (unpack('n*', $header) as $word) {
            $sum += $word;
        }
        while ($sum >> 16) {
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        return substr_replace($header, pack('n', ~$sum & 0xFFFF), 10, 2) . $udp;
    }

    public static function linkHeader(int $linkType, int $family): string
    {
        $protocol = $family === 6 ? 0x86DD : 0x0800;

        return match ($linkType) {
            1 => str_repeat("\0", 12) . pack('n', $protocol),
            113 => pack('nnn', 0, 1, 6) . str_repeat("\0", 8) . pack('n', $protocol),
            276 => pack('nnNnCC', $protocol, 0, 0, 1, 0, 6) . str_repeat("\0", 8),
            default => '',
        };
    }

    /**
     * Count records in a classic libpcap file without loading it.
     */
    public static function countPackets(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return 0;
        }

        try {
            $header = fread($handle, 24);
            if (strlen($header) < 24) {
                return 0;
            }

            $magic = bin2hex(substr($header, 0, 4));
            $format = match ($magic) {
                'd4c3b2a1', '4d3cb2a1' => 'V',
                'a1b2c3d4', 'a1b23c4d' => 'N',
                default => null,
            };
            if ($format === null) {
                return 0;
            }

            $size = filesize($path);
            $count = 0;
            while (strlen($record = fread($handle, 16)) === 16) {
                $length = unpack($format, substr($record, 8, 4))[1];
                // Stop at a partly written last packet.
                if (ftell($handle) + $length > $size) {
                    break;
                }
                fseek($handle, $length, SEEK_CUR);
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    private function run(array $command, bool $mustSucceed = true): void
    {
        $command[0] = $this->tools[$command[0]] ?? $command[0];
        $process = new Process($command, null, null, null, 120);
        $process->run();

        if ($mustSucceed && !$process->isSuccessful()) {
            throw new RuntimeException(basename($command[0]) . ': ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
