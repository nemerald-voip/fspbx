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
     */
    public function __construct(private string $spoolDir, private string $workDir, private array $tools = [])
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
    public static function preFilter(array $ports): string
    {
        $parts = ['tcp', 'udp portrange ' . PcapService::SIP_PORT_RANGE];
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
        if (!$chunks) {
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
                $this->run(['tcpdump', '-n', '-r', $chunk, '-w', $part, self::preFilter($ports)], false);
                if (is_file($part) && self::countPackets($part) > 0) {
                    $parts[] = $part;
                }
            }

            if (!$parts) {
                return 0;
            }

            $merged = $parts[0];
            if (count($parts) > 1) {
                $merged = "{$tmp}/merged.pcap";
                $this->run(array_merge(['mergecap', '-F', 'pcap', '-w', $merged], $parts));
            }

            File::ensureDirectoryExists(dirname($output), 0770);
            $this->run(['tshark', '-n', '-r', $merged, '-Y', self::displayFilter($callIds, $ports, $from, $to), '-F', 'pcap', '-w', $output]);

            return is_file($output) ? self::countPackets($output) : 0;
        } finally {
            File::deleteDirectory($tmp);
        }
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
