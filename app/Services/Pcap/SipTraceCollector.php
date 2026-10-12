<?php

namespace App\Services\Pcap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Turns FreeSWITCH SIP trace output into packets for the capture spool.
 *
 * SIP over TLS is encrypted on the wire, but with `sofia global siptrace on`
 * FreeSWITCH logs every message decrypted. Each run reads the new part of
 * freeswitch.log and writes its TLS messages as ordinary UDP packets with the
 * original addresses and times, so a call's PCAP can show its signalling.
 * Plain UDP/TCP messages are skipped because the packet capture has them.
 */
class SipTraceCollector
{
    private const OFFSET_CACHE = 'pcap:siptrace_offset';
    private const MAX_READ_BYTES = 20 * 1024 * 1024;
    private const TRANSPORTS = ['tls', 'wss'];

    /**
     * @param array<int, array{family: int, ip: string, port: int, internal: bool}> $localAddresses FreeSWITCH TLS listeners
     */
    public function __construct(
        private string $logPath,
        private string $outputDir,
        private array $localAddresses,
        private \DateTimeZone $logTimezone,
    ) {
    }

    /**
     * Start reading at the end of the log, so old trace output is ignored.
     */
    public function startAtEnd(): void
    {
        clearstatcache(true, $this->logPath);
        $stat = @stat($this->logPath);
        Cache::forever(self::OFFSET_CACHE, ['inode' => $stat['ino'] ?? null, 'offset' => $stat['size'] ?? 0]);
    }

    public function stop(): void
    {
        Cache::forget(self::OFFSET_CACHE);
    }

    /**
     * Convert new TLS trace messages into a spool file. Returns the number written.
     */
    public function collect(int $linkType): int
    {
        clearstatcache(true, $this->logPath);
        $stat = @stat($this->logPath);
        $state = Cache::get(self::OFFSET_CACHE);
        if (!$stat) {
            return 0;
        }
        // Lost position (for example a cleared cache): start again from the end.
        if (!$state) {
            $this->startAtEnd();
            return 0;
        }

        $offset = (int) $state['offset'];
        // The log was rotated or truncated: continue from the start of the new file.
        if ($state['inode'] !== $stat['ino'] || $stat['size'] < $offset) {
            $offset = 0;
        }
        if ($stat['size'] === $offset) {
            return 0;
        }

        $handle = @fopen($this->logPath, 'rb');
        if (!$handle) {
            return 0;
        }
        fseek($handle, $offset);
        $text = (string) fread($handle, self::MAX_READ_BYTES);
        fclose($handle);

        $parsed = self::parse($text, $this->logTimezone, (new \DateTimeImmutable('now', $this->logTimezone))->format('Y-m-d'));
        Cache::forever(self::OFFSET_CACHE, ['inode' => $stat['ino'], 'offset' => $offset + $parsed['consumed']]);

        $packets = [];
        $learned = [];
        foreach ($parsed['entries'] as $entry) {
            if (!in_array($entry['transport'], self::TRANSPORTS, true)) {
                continue;
            }

            $local = $this->localAddress($entry, $learned);
            if ($local === null) {
                continue;
            }

            $peer = ['ip' => $entry['ip'], 'port' => $entry['port']];
            [$src, $dst] = $entry['direction'] === 'recv' ? [$peer, $local] : [$local, $peer];
            $family = str_contains($entry['ip'], ':') ? 6 : 4;
            $ip = PcapExtractor::ipUdpPacket([
                'family' => $family,
                'src' => (string) @inet_pton($src['ip']),
                'dst' => (string) @inet_pton($dst['ip']),
                'src_port' => $src['port'],
                'dst_port' => $dst['port'],
                'payload' => $entry['message'],
            ]);
            if ($ip !== null) {
                $packets[] = [$entry['seconds'], $entry['micros'], PcapExtractor::linkHeader($linkType, $family) . $ip];
            }
        }

        if (!$packets) {
            return 0;
        }

        usort($packets, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        File::ensureDirectoryExists($this->outputDir, 0770);
        $first = $packets[0][0];
        $last = end($packets)[0];
        $path = $this->outputDir . "/{$first}-{$last}-" . substr(md5((string) $offset), 0, 6) . '.pcap';

        $handle = fopen($path, 'wb');
        fwrite($handle, pack('VvvVVVV', 0xa1b2c3d4, 2, 4, 0, 0, 262144, $linkType));
        foreach ($packets as [$sec, $usec, $data]) {
            fwrite($handle, pack('VVVV', $sec, $usec, strlen($data), strlen($data)) . $data);
        }
        fclose($handle);

        return count($packets);
    }

    /**
     * Parse sofia trace entries:
     *
     *   recv 659 bytes from tls/[192.168.10.174]:56967 at 23:13:22.966015:
     *   ------------------------------------------------------------------------
     *   REGISTER sip:192.168.10.231 SIP/2.0
     *   ...
     *
     * The log drops carriage returns, so the byte count (CRLF lines) tells
     * where a message ends even when its body follows a blank line. Trace
     * lines carry no date; it comes from the nearest normal log line.
     *
     * @return array{entries: array<int, array>, consumed: int} consumed stops before an incomplete entry
     */
    public static function parse(string $text, \DateTimeZone $timezone, string $defaultDate): array
    {
        $entries = [];
        $date = $defaultDate;
        $stampSeconds = null;
        $consumed = 0;
        $length = strlen($text);
        $position = 0;

        $readLine = function () use ($text, $length, &$position): ?string {
            if ($position >= $length) {
                return null;
            }
            $end = strpos($text, "\n", $position);
            if ($end === false) {
                // An unterminated last line may still be being written.
                return null;
            }
            $line = substr($text, $position, $end - $position);
            $position = $end + 1;

            return rtrim($line, "\r");
        };

        while (($start = $position) < $length && ($line = $readLine()) !== null) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2}):(\d{2})\.\d+ /', $line, $m)) {
                $date = $m[1];
                $stampSeconds = $m[2] * 3600 + $m[3] * 60 + $m[4];
                $consumed = $position;
                continue;
            }

            if (!preg_match('/^(recv|send) (\d+) bytes (?:from|to) ([a-z]+)\/\[([^\]]+)\]:(\d+) at (\d{2}):(\d{2}):(\d{2})\.(\d+):$/', $line, $m)) {
                $consumed = $position;
                continue;
            }

            $bytes = (int) $m[2];
            $separator = $readLine();
            if ($separator === null) {
                break;
            }

            $lines = [];
            $size = 0;
            $complete = $bytes === 0;
            while (!$complete && ($body = $readLine()) !== null) {
                $lines[] = $body;
                $size += strlen($body) + 2;
                $complete = $size >= $bytes;
            }
            if (!$complete) {
                $position = $start;
                break;
            }
            $consumed = $position;

            $time = $m[6] * 3600 + $m[7] * 60 + $m[8];
            $day = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
            // Trace lines just after midnight belong to the next day.
            if ($stampSeconds !== null && $time < $stampSeconds - 43200) {
                $day = $day->modify('+1 day');
            }

            $entries[] = [
                'direction' => $m[1],
                'transport' => $m[3],
                'ip' => $m[4],
                'port' => (int) $m[5],
                'seconds' => $day->setTime((int) $m[6], (int) $m[7], (int) $m[8])->getTimestamp(),
                'micros' => (int) substr(str_pad($m[9], 6, '0'), 0, 6),
                'message' => implode("\r\n", $lines) . "\r\n",
            ];
        }

        return ['entries' => $entries, 'consumed' => $consumed];
    }

    /**
     * FreeSWITCH's side of a traced message. Requests it sends and responses it
     * receives carry its own address in the top Via; otherwise reuse what this
     * connection showed earlier, then fall back to the internal TLS listener.
     */
    private function localAddress(array $entry, array &$learned): ?array
    {
        $peerKey = $entry['ip'] . '|' . $entry['port'];
        $isRequest = !str_starts_with($entry['message'], 'SIP/2.0 ');
        $carriesLocalVia = ($entry['direction'] === 'send') === $isRequest;

        if ($carriesLocalVia && preg_match('/^(?:Via|v)[ \t]*:[ \t]*SIP\/2\.0\/([A-Z]+)[ \t]+(\[[^\]]+\]|[^:;\s]+)(?::(\d+))?/mi', $entry['message'], $via)) {
            $host = trim($via[2], '[]');
            // RFC 3261: a Via without a port means 5061 for TLS and 5060 otherwise.
            $port = !empty($via[3]) ? (int) $via[3] : (in_array(strtoupper($via[1]), ['TLS', 'WSS'], true) ? 5061 : 5060);
            foreach ($this->localAddresses as $address) {
                if ($address['ip'] === $host && $port === $address['port']) {
                    return $learned[$peerKey] = ['ip' => $address['ip'], 'port' => $address['port']];
                }
            }
        }

        if (isset($learned[$peerKey])) {
            return $learned[$peerKey];
        }

        $family = str_contains($entry['ip'], ':') ? 6 : 4;
        $candidates = array_values(array_filter($this->localAddresses, fn ($address) => $address['family'] === $family));
        usort($candidates, fn ($a, $b) => (int) $b['internal'] <=> (int) $a['internal']);

        return isset($candidates[0]) ? ['ip' => $candidates[0]['ip'], 'port' => $candidates[0]['port']] : null;
    }
}
