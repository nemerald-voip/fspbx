<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class HomerRtcpReports
{
    public function streams(string $body, array $ids, array $window): array
    {
        $rtcp = json_decode($body, true)['data']['rtcp'] ?? null;
        if (! is_array($rtcp) || ! array_key_exists('data', $rtcp)) {
            throw new RuntimeException(__('HOMER returned an unexpected QoS response.'));
        }
        $rows = $rtcp['data'] ?? [];
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException(__('HOMER returned an unexpected QoS response.'));
        }
        if (count($rows) > 10000) {
            throw new RuntimeException(__('This call has too many RTCP reports to display.'));
        }
        // HOMER also matches the base Call-ID of B2BUA legs for media reports.
        $allowed = array_fill_keys(array_merge($ids, preg_replace('/_b2b-\d+$/', '', $ids)), true);
        $streams = [];
        $reportCount = 0;
        $pointCount = 0;
        foreach ($rows as $row) {
            $id = $row['session_id'] ?? $row['cid'] ?? null;
            if (! is_string($id) || ! isset($allowed[$id])) {
                continue;
            }
            $ms = $this->timestamp($row['timestamp'] ?? $row['time'] ?? $row['create_date'] ?? $row['ts'] ?? null);
            if ($ms === null || $ms < $window['from'] || $ms > $window['to']) {
                continue;
            }
            $payload = $row['payload'] ?? $row['raw'] ?? $row['data_extra'] ?? null;
            if (is_string($payload)) {
                $payload = json_decode(str_replace("\x10", '', $payload), true);
            }
            if (! is_array($payload) || ! in_array($payload['type'] ?? 200, [200, 201, 202, 207, '200', '201', '202', '207'], true)) {
                continue;
            }
            $blocks = $payload['report_blocks'] ?? [];
            if (! is_array($blocks) || ! array_is_list($blocks) || count($blocks) > 31) {
                continue;
            }
            $accepted = false;
            foreach ($blocks ?: [[]] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $metrics = $this->metrics($payload, $block);
                if ($metrics === []) {
                    continue;
                }
                $identity = [
                    'call_id' => $id,
                    'source' => $this->endpoint($row, 'src'),
                    'destination' => $this->endpoint($row, 'dst'),
                    'sender_ssrc' => $this->label($payload['ssrc'] ?? null),
                    'report_ssrc' => $this->label($block['source_ssrc'] ?? $block['ssrc'] ?? null),
                ];
                $key = json_encode($identity, JSON_THROW_ON_ERROR);
                $streams[$key] ??= $identity + ['points' => []];
                $streams[$key]['points'][] = ['time' => $ms] + $metrics;
                $accepted = true;
                if (++$pointCount > 10000 || count($streams) > 256) {
                    throw new RuntimeException(__('This call has too many RTCP reports to display.'));
                }
            }
            if ($accepted) {
                $reportCount++;
            }
        }
        return ['streams' => array_values($streams), 'report_count' => $reportCount];
    }

    private function metrics(array $payload, array $block): array
    {
        $sender = $payload['sender_information'] ?? [];
        $fraction = $this->number($block['fraction_lost'] ?? null);
        $loss = $fraction !== null && $fraction >= 0 && $fraction <= 255 ? $fraction * 100 / 256 : null;
        $jitter = $this->number($block['ia_jitter'] ?? null);
        return array_filter([
            'jitter' => $jitter,
            'loss' => $loss,
            'mos' => $jitter !== null && $loss !== null ? $this->estimatedMos($jitter, $loss, $this->number($block['dlsr'] ?? null)) : null,
            'packets_lost' => $this->number($block['packets_lost'] ?? null),
            'packets' => $this->number($sender['packets'] ?? null),
            'octets' => $this->number($sender['octets'] ?? null),
            'highest_seq_no' => $this->number($block['highest_seq_no'] ?? null),
            'lsr' => $this->number($block['lsr'] ?? null),
        ], fn ($value) => $value !== null);
    }

    // Match HOMER's RTCP QoS panel estimate when jitter and interval loss are available.
    private function estimatedMos(float $jitter, float $loss, ?float $delay): float
    {
        $latency = ($delay > 0 && $delay < 1000 ? $delay : 10) + 2 * $jitter + 10;
        $rating = 93.2 - ($latency < 160 ? $latency / 40 : ($latency - 120) / 10);
        $rating = max(0, min(100, $rating - 2.5 * $loss));
        return round(min(4.7, 1 + 0.035 * $rating + 0.000007 * $rating * ($rating - 60) * (100 - $rating)), 2);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    private function label(mixed $value): string
    {
        return is_scalar($value) && strlen((string) $value) <= 128 ? (string) $value : '';
    }

    private function endpoint(array $row, string $side): string
    {
        $address = $this->label($row[$side.'_ip'] ?? null);
        $port = $this->label($row[$side.'_port'] ?? null);
        if ($address === '') {
            return '—';
        }
        return $port === '' ? $address : (str_contains($address, ':') ? '['.$address.']' : $address).':'.$port;
    }

    private function timestamp(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $time = (float) $value;
            return (int) round(match (true) {
                $time >= 1e16 => $time / 1e6,
                $time >= 1e14 => $time / 1e3,
                $time >= 1e11 => $time,
                default => $time * 1000,
            });
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d\d-\d\d[T ]\d\d:\d\d:\d\d(?:\.\d{1,9})?(?:Z|[+-]\d\d:?\d\d)?$/D', $value)) {
            return null;
        }
        try {
            return (int) (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Uv');
        } catch (\Exception) {
            return null;
        }
    }
}
