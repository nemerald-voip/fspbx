<?php

namespace Tests\Unit;

use App\Services\SipCaptureService;
use Tests\TestCase;

class SipCaptureServiceTest extends TestCase
{
    public function test_it_builds_the_homer_hep_three_capture_server_value(): void
    {
        $service = $this->service();

        $this->assertSame(
            'udp:212.56.38.50:9060;hep=3;capture_id=$${hep_capture_id}',
            $service->captureServerValue('UDP', '212.56.38.50', 9060)
        );
    }

    public function test_it_wraps_ipv6_collector_addresses(): void
    {
        $service = $this->service();

        $this->assertSame(
            'tcp:[2001:db8::10]:9060;hep=3;capture_id=$${hep_capture_id}',
            $service->captureServerValue('tcp', '2001:db8::10', 9060)
        );
    }

    public function test_it_parses_complete_and_legacy_capture_server_values(): void
    {
        $service = $this->service();

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => 'homer.example.com',
            'collector_port' => 9060,
            'capture_id' => 305,
        ], $service->parseCaptureServerValue('udp:homer.example.com:9060;hep=3;capture_id=305'));

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => '127.0.0.1',
            'collector_port' => 9060,
            'capture_id' => null,
        ], $service->parseCaptureServerValue('udp:127.0.0.1:9060'));

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => 'homer.example.com',
            'collector_port' => 9060,
            'capture_id' => null,
        ], $service->parseCaptureServerValue(
            'udp:homer.example.com:9060;hep=3;capture_id=$${hep_capture_id}'
        ));
    }

    public function test_it_retries_random_capture_ids_that_are_already_used(): void
    {
        $service = new TestableSipCaptureService([101, 3000000001]);

        $this->assertSame(3000000001, $service->availableCaptureId([101, 102]));
    }

    private function service(): SipCaptureService
    {
        return new SipCaptureService();
    }
}

class TestableSipCaptureService extends SipCaptureService
{
    public function __construct(private array $candidates)
    {
    }

    public function availableCaptureId(array $usedCaptureIds): int
    {
        return $this->randomCaptureId(collect($usedCaptureIds));
    }

    protected function generateCaptureIdCandidate(): int
    {
        return array_shift($this->candidates);
    }
}
