<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Discovers call links without requiring a CDR for every attempted leg. */
class FreeswitchLogCallGraph
{
    private const UUID = '[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}';

    private array $edges = [];
    private array $sessions = [];
    private array $legReferences = [];
    private array $pendingInvites = [];
    private array $seenInvites = [];
    private array $packetCallIds = [];
    private ?float $lastTimestamp = null;

    public function addEntries(array $entries, array $blocks): void
    {
        foreach ($entries as $index => $entry) {
            if (isset($blocks[$index])) {
                if ($blocks[$index]['start'] === $index) {
                    $this->addPacket(array_slice($entries, $index, $blocks[$index]['end'] - $index + 1));
                }

                continue;
            }

            $line = $entry['message'];
            if ($entry['timestamp'] !== null) {
                $this->lastTimestamp = $this->timestamp($entry['timestamp']);
            }

            if (preg_match('/New Channel (\S+) \[(' . self::UUID . ')\]/', $line, $match)) {
                $this->sessions[$match[2]] = [
                    'channel' => $match[1],
                    'start' => $this->lastTimestamp,
                    'end' => null,
                ];
            }

            if (! preg_match('/^(' . self::UUID . ')\s/', $line, $prefix)) {
                continue;
            }

            $uuid = $prefix[1];
            if (str_contains($line, 'Close Channel ') && isset($this->sessions[$uuid])) {
                $this->sessions[$uuid]['end'] = $this->lastTimestamp;
            }

            if (preg_match('/Peer UUID:\s*(' . self::UUID . ')/', $line, $match)) {
                $this->link('uuid:' . $uuid, 'uuid:' . $match[1]);
            }

            if (preg_match('/\b(\S+) Setting leg (?:progress )?timeout to \d+/', $line, $match)) {
                $this->legReferences[] = [$uuid, $match[1], $this->lastTimestamp];
            }

            if (preg_match('/(?:receiving|sending) invite .*?call-id:\s*(\S+)/i', $line, $match)
                && $match[1] !== '(null)') {
                $this->link('uuid:' . $uuid, 'sip:' . $match[1]);
            }

            if (preg_match('/\bsofia\/[^\/\s]+\/(\S+) sending invite call-id:/', $line, $match)
                && $this->lastTimestamp !== null) {
                $endpoint = $this->endpoint($match[1]);
                $this->pendingInvites[$endpoint][$uuid] = $this->lastTimestamp;
            }
        }
    }

    public function discover(Collection $seeds): array
    {
        $byChannel = [];
        foreach ($this->sessions as $uuid => $session) {
            $byChannel[$session['channel']][$uuid] = $session;
            if (isset($this->packetCallIds[$uuid])) {
                $this->link('uuid:' . $uuid, 'sip:' . $uuid);
            }
        }

        foreach ($this->legReferences as [$parent, $channel, $time]) {
            if ($time === null) {
                continue;
            }

            $candidates = array_filter($byChannel[$channel] ?? [], fn ($session) =>
                $session['start'] !== null && $session['start'] <= $time
                && ($session['end'] === null || $session['end'] >= $time)
            );

            // The same phone may receive concurrent calls. A channel name alone
            // is not a unique call identifier; leave ambiguous links unresolved.
            if (count($candidates) === 1) {
                $this->link('uuid:' . $parent, 'uuid:' . array_key_first($candidates));
            }
        }

        $pending = [];
        foreach ($seeds as $seed) {
            $pending[] = 'uuid:' . $seed;
            $pending[] = 'sip:' . $seed;
        }

        $seen = [];
        $result = ['uuids' => [], 'sip_call_ids' => []];
        while ($pending) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;

            if (! isset($this->edges[$node])) {
                continue;
            }

            [$type, $value] = explode(':', $node, 2);
            $result[$type === 'uuid' ? 'uuids' : 'sip_call_ids'][] = $value;
            foreach ($this->edges[$node] as $peer => $_) {
                $pending[] = $peer;
            }
        }

        return $result;
    }

    private function addPacket(array $entries): void
    {
        $text = implode("\n", array_column($entries, 'message'));
        if (! preg_match('/^(?:Call-ID|i):\s*(\S+)/mi', $text, $id)) {
            return;
        }

        $callId = $id[1];
        $this->packetCallIds[$callId] = true;

        if (! preg_match('/^INVITE sips?:([^\s]+) SIP\/2\.0\r?$/mi', $text, $invite)
            || ! preg_match('/\bsend \d+ bytes to /i', $entries[0]['message'])
            || isset($this->seenInvites[$callId])) {
            return;
        }
        $this->seenInvites[$callId] = true;

        $time = $entries[0]['timestamp'] !== null
            ? $this->timestamp($entries[0]['timestamp'])
            : $this->packetTimestamp($entries[0]['message']);
        $endpoint = $this->endpoint($invite[1]);
        if ($time === null) {
            return;
        }

        $candidates = array_filter($this->pendingInvites[$endpoint] ?? [], fn ($sentAt) =>
            $sentAt <= $time && $time - $sentAt <= 2
        );
        if (count($candidates) === 1) {
            $uuid = array_key_first($candidates);
            $this->link('uuid:' . $uuid, 'sip:' . $callId);
            unset($this->pendingInvites[$endpoint][$uuid]);
        }
    }

    private function endpoint(string $value): string
    {
        return explode(';', $value, 2)[0];
    }

    private function packetTimestamp(string $line): ?float
    {
        if ($this->lastTimestamp === null || ! preg_match('/\bat (\d{2}:\d{2}:\d{2}(?:\.\d+)?):/', $line, $match)) {
            return null;
        }

        $time = $this->timestamp(gmdate('Y-m-d', (int) $this->lastTimestamp) . ' ' . $match[1]);
        // Raw SIP trace headers contain only a clock time, including at midnight.
        if ($time < $this->lastTimestamp - 43200) {
            $time += 86400;
        }

        return $time;
    }

    private function timestamp(string $value): float
    {
        return (float) (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('U.u');
    }

    private function link(string $left, string $right): void
    {
        $this->edges[$left][$right] = true;
        $this->edges[$right][$left] = true;
    }
}
