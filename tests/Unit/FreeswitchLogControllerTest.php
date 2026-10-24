<?php

namespace Tests\Unit;

use App\Http\Controllers\FreeswitchLogController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FreeswitchLogControllerTest extends TestCase
{
    private const PARENT = '11111111-1111-4111-8111-111111111111';
    private const CHILD = '22222222-2222-4222-8222-222222222222';
    private const OTHER = '33333333-3333-4333-8333-333333333333';

    public function test_search_includes_full_sip_trace_packet_when_call_id_matches(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fspbx-fs-log-');

        file_put_contents($path, implode("\n", [
            '2026-06-17 17:00:00.000000 [INFO] switch_core_state_machine.c:100 unrelated line',
            '2026-06-17 17:00:01.000000 [INFO] sofia.c:1049 recv 955 bytes from udp/[192.0.2.10]:5060 at 17:00:01.000000:',
            '------------------------------------------------------------------------',
            'INVITE sip:1000@example.com SIP/2.0',
            'Via: SIP/2.0/UDP 192.0.2.10:5060;branch=z9hG4bK',
            'Call-ID: matched-sip-call-id',
            'CSeq: 102 INVITE',
            '------------------------------------------------------------------------',
            '2026-06-17 17:00:02.000000 [INFO] switch_core_state_machine.c:100 another unrelated line',
        ]) . "\n");

        try {
            $result = $this->invokeSearchFiles(
                files: collect([[
                    'path' => $path,
                    'basename' => basename($path),
                    'size' => filesize($path),
                    'readable' => true,
                ]]),
                searchTerms: collect(['matched-sip-call-id']),
            );

            $messages = array_column($result['lines'], 'message');

            $this->assertSame([
                '2026-06-17 17:00:01.000000 [INFO] sofia.c:1049 recv 955 bytes from udp/[192.0.2.10]:5060 at 17:00:01.000000:',
                '------------------------------------------------------------------------',
                'INVITE sip:1000@example.com SIP/2.0',
                'Via: SIP/2.0/UDP 192.0.2.10:5060;branch=z9hG4bK',
                'Call-ID: matched-sip-call-id',
                'CSeq: 102 INVITE',
                '------------------------------------------------------------------------',
            ], $messages);
        } finally {
            @unlink($path);
        }
    }

    public function test_packet_ends_at_uuid_prefixed_or_cpu_timestamped_log_lines(): void
    {
        foreach ([
            self::OTHER . ' 2026-10-23 11:22:13.039323 92.73% [DEBUG] unrelated debug',
            '2026-10-23 11:22:13.039323 92.73% [WARNING] unrelated warning',
            self::OTHER . ' EXECUTE [depth=0] sofia/internal/2000 playback(unrelated.wav)',
            self::OTHER . ' Dialplan: sofia/internal/2000 parsing unrelated',
        ] as $boundary) {
            $packet = $this->packet('incoming-call', '11:22:13.058394');
            $result = $this->searchText([$packet . $boundary . "\nUnrelated continuation\n"], ['incoming-call']);
            $messages = implode("\n", array_column($result['lines'], 'message'));

            $this->assertStringContainsString('INVITE sip:1000@192.0.2.10:55380', $messages);
            $this->assertStringContainsString('Call-ID: incoming-call', $messages);
            $this->assertStringNotContainsString($boundary, $messages);
            $this->assertStringNotContainsString('Unrelated continuation', $messages);
        }
    }

    public function test_whole_call_recovers_unanswered_leg_and_packets_across_log_rotation_without_cdr(): void
    {
        $firstFile = $this->originateLogs();
        $secondFile = $this->packet('outgoing-call', '11:22:13.058394')
            . $this->log(self::CHILD, 'Channel sofia/internal/1000@192.0.2.10:55380 entering state [calling][0]')
            . $this->packet('outgoing-call', '11:22:14.059493')
            . $this->log(self::CHILD, 'Hangup sofia/internal/1000@192.0.2.10:55380 [CS_CONSUME_MEDIA] [NO_ANSWER]', '11:22:38.019324')
            . $this->log(self::CHILD, 'Close Channel sofia/internal/1000@192.0.2.10:55380 [CS_DESTROY]', '11:22:38.019324')
            . $this->log(self::OTHER, 'New Channel sofia/internal/1000@192.0.2.10:55380 [' . self::OTHER . ']', '11:22:40.000000')
            . $this->log(self::OTHER, 'sofia/internal/1000@192.0.2.10:55380 sending invite call-id: (null)', '11:22:40.000000')
            . $this->packet('unrelated-call-to-same-phone', '11:22:40.010000');

        $result = $this->searchText([$firstFile, $secondFile], [self::PARENT], true);
        $messages = implode("\n", array_column($result['lines'], 'message'));

        $this->assertContains(self::CHILD, $result['discovered']['uuids']);
        $this->assertContains('outgoing-call', $result['discovered']['sip_call_ids']);
        $this->assertSame(2, substr_count($messages, 'Call-ID: outgoing-call'));
        $this->assertStringContainsString('entering state [calling][0]', $messages);
        $this->assertStringContainsString('NO_ANSWER', $messages);
        $this->assertStringNotContainsString(self::OTHER, $messages);
        $this->assertStringNotContainsString('unrelated-call-to-same-phone', $messages);
    }

    public function test_discovery_is_bidirectional_and_runs_before_text_filtering(): void
    {
        $text = $this->originateLogs() . $this->packet('outgoing-call', '11:22:13.058394');
        $result = $this->searchText([$text], ['outgoing-call'], true, 'New Channel');
        $messages = implode("\n", array_column($result['lines'], 'message'));

        $this->assertContains(self::PARENT, $result['discovered']['uuids']);
        $this->assertContains(self::CHILD, $result['discovered']['uuids']);
        $this->assertStringContainsString('New Channel', $messages);
        $this->assertStringNotContainsString('Call-ID:', $messages);
    }

    public function test_single_leg_search_does_not_expand_to_outgoing_call(): void
    {
        $result = $this->searchText([
            $this->originateLogs() . $this->packet('outgoing-call', '11:22:13.058394'),
        ], [self::PARENT]);
        $messages = implode("\n", array_column($result['lines'], 'message'));

        $this->assertStringContainsString('Setting leg timeout', $messages);
        $this->assertStringNotContainsString(self::CHILD, $messages);
        $this->assertStringNotContainsString('Call-ID: outgoing-call', $messages);
        $this->assertSame(['uuids' => [], 'sip_call_ids' => []], $result['discovered']);
    }

    public function test_concurrent_calls_to_same_endpoint_are_not_guessed_as_related(): void
    {
        $text = $this->log(self::CHILD, 'New Channel sofia/internal/1000@192.0.2.10:55380 [' . self::CHILD . ']')
            . $this->log(self::OTHER, 'New Channel sofia/internal/1000@192.0.2.10:55380 [' . self::OTHER . ']')
            . $this->log(self::PARENT, 'sofia/internal/1000@192.0.2.10:55380 Setting leg timeout to 25')
            . $this->log(self::CHILD, 'sofia/internal/1000@192.0.2.10:55380 sending invite call-id: (null)')
            . $this->log(self::OTHER, 'sofia/internal/1000@192.0.2.10:55380 sending invite call-id: (null)')
            . $this->packet('ambiguous-call', '11:22:13.058394');

        $parentResult = $this->searchText([$text], [self::PARENT], true);
        $childResult = $this->searchText([$text], [self::CHILD], true);

        $this->assertEmpty($parentResult['discovered']['uuids']);
        $this->assertEmpty($childResult['discovered']['sip_call_ids']);
        $this->assertStringNotContainsString('Call-ID: ambiguous-call', implode("\n", array_column($childResult['lines'], 'message')));
    }

    public function test_explicit_peer_uuid_and_call_id_links_support_multiple_hops(): void
    {
        $text = $this->log(self::PARENT, 'Originate Resulted in Success: [sofia/internal/1000] Peer UUID: ' . self::CHILD)
            . $this->log(self::CHILD, 'Originate Resulted in Success: [sofia/internal/2000] Peer UUID: ' . self::OTHER)
            . $this->log(self::OTHER, 'sofia/internal/2000 sending invite call-id: third-leg-call')
            . $this->packet('third-leg-call', '11:22:13.058394');
        $result = $this->searchText([$text], [self::PARENT], true);

        $this->assertEqualsCanonicalizing([self::PARENT, self::CHILD, self::OTHER], $result['discovered']['uuids']);
        $this->assertContains('third-leg-call', $result['discovered']['sip_call_ids']);
        $this->assertStringContainsString('Call-ID: third-leg-call', implode("\n", array_column($result['lines'], 'message')));
    }

    public function test_packet_clock_rollover_does_not_lose_outgoing_invite(): void
    {
        $text = str_replace('11:22:13.039323', '23:59:59.999000', $this->originateLogs())
            . $this->packet('midnight-call', '00:00:00.010000');
        $result = $this->searchText([$text], [self::PARENT], true);

        $this->assertContains('midnight-call', $result['discovered']['sip_call_ids']);
    }

    private function originateLogs(): string
    {
        return $this->log(self::PARENT, 'sofia/external/caller receiving invite from 192.0.2.20 call-id: incoming-call')
            . $this->log(self::CHILD, 'New Channel sofia/internal/1000@192.0.2.10:55380 [' . self::CHILD . ']')
            . $this->log(self::PARENT, 'sofia/internal/1000@192.0.2.10:55380 Setting leg timeout to 25')
            . $this->log(self::CHILD, 'sofia/internal/1000@192.0.2.10:55380 sending invite call-id: (null)');
    }

    private function log(string $uuid, string $message, string $time = '11:22:13.039323'): string
    {
        return "$uuid 2026-10-23 $time 92.73% [DEBUG] sofia.c:100 $message\n";
    }

    private function packet(string $callId, string $time): string
    {
        return implode("\n", [
            "send 400 bytes to udp/[192.0.2.10]:55536 at $time:",
            '------------------------------------------------------------------------',
            'INVITE sip:1000@192.0.2.10:55380;received=192.0.2.10:55536 SIP/2.0',
            "Call-ID: $callId",
            'CSeq: 1 INVITE',
            'Content-Type: application/sdp',
            'Content-Length: 5',
            '',
            'v=0',
            '',
        ]);
    }

    private function searchText(array $texts, array $terms, bool $discoverLegs = false, string $textSearch = ''): array
    {
        $paths = [];
        try {
            foreach ($texts as $text) {
                $path = tempnam(sys_get_temp_dir(), 'fspbx-fs-log-');
                file_put_contents($path, $text);
                $paths[] = $path;
            }

            return $this->invokeSearchFiles(
                collect($paths)->map(fn ($path) => [
                    'path' => $path,
                    'basename' => basename($path),
                    'size' => filesize($path),
                    'readable' => true,
                ]),
                collect($terms),
                $discoverLegs,
                $textSearch,
            );
        } finally {
            foreach ($paths as $path) {
                unlink($path);
            }
        }
    }

    private function invokeSearchFiles($files, $searchTerms, bool $discoverLegs = false, string $textSearch = ''): array
    {
        $controller = new FreeswitchLogController();
        $method = new ReflectionMethod($controller, 'searchFiles');
        $method->setAccessible(true);

        return $method->invoke(
            $controller,
            $files,
            $searchTerms,
            $textSearch,
            'all',
            5120,
            3000,
            'asc',
            $discoverLegs,
        );
    }
}
