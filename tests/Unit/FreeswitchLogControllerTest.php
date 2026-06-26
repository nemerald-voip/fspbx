<?php

namespace Tests\Unit;

use App\Http\Controllers\FreeswitchLogController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FreeswitchLogControllerTest extends TestCase
{
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

    private function invokeSearchFiles($files, $searchTerms): array
    {
        $controller = new FreeswitchLogController();
        $method = new ReflectionMethod($controller, 'searchFiles');
        $method->setAccessible(true);

        return $method->invoke(
            $controller,
            $files,
            $searchTerms,
            '',
            'all',
            5120,
            3000,
            'asc',
        );
    }
}
