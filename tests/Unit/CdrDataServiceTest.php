<?php

namespace Tests\Unit;

use App\Models\CDR;
use App\Services\CdrDataService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CdrDataServiceTest extends TestCase
{
    public function test_outbound_fax_collapses_duplicate_loopback_profiles_for_the_same_channel(): void
    {
        $profiles = collect([
            $this->profile('channel-a', '+12137577900', 100, 101),
            $this->profile('channel-a', '12137577900', 101, 101),
            $this->profile('channel-a', '+12137577900', 101, 125),
        ]);

        $normalized = $this->normalize($this->service(true), $profiles);

        $this->assertCount(1, $normalized);
        $this->assertSame(125, (int) $normalized->first()['times']['profile_end_time']);
    }

    public function test_non_fax_call_flow_is_never_deduplicated(): void
    {
        $profiles = collect([
            $this->profile('channel-a', '+12137577900', 100, 101),
            $this->profile('channel-a', '+12137577900', 101, 125),
        ]);

        $this->assertCount(2, $this->normalize($this->service(false), $profiles));
    }

    public function test_basic_dialer_replaces_only_the_loopback_xml_callee_placeholder(): void
    {
        $profiles = collect([
            $this->profile('channel-a', '6467052267', 100, 104, 'XML'),
            $this->profile('channel-a', '101', 104, 116, '101'),
        ]);

        $normalized = $this->normalize($this->service(false, true), $profiles);

        $this->assertSame('6467052267', $normalized[0]['caller_profile']['callee_id_number']);
        $this->assertSame('101', $normalized[1]['caller_profile']['callee_id_number']);
    }

    public function test_non_basic_dialer_preserves_the_xml_callee_placeholder(): void
    {
        $profiles = collect([
            $this->profile('channel-a', '6467052267', 100, 104, 'XML'),
        ]);

        $normalized = $this->normalize($this->service(false), $profiles);

        $this->assertSame('XML', $normalized[0]['caller_profile']['callee_id_number']);
    }

    public function test_outbound_fax_preserves_distinct_channels_and_destinations(): void
    {
        $profiles = collect([
            $this->profile('channel-a', '+12137577900', 100, 101),
            $this->profile('channel-b', '+12137577900', 101, 110),
            $this->profile('channel-b', '+13105551212', 110, 125),
        ]);

        $this->assertCount(3, $this->normalize($this->service(true), $profiles));
    }

    private function service(bool $outboundFax, bool $basicDialer = false): CdrDataService
    {
        return new class($outboundFax, $basicDialer) extends CdrDataService
        {
            public function __construct(
                private bool $outboundFax,
                private bool $basicDialer
            )
            {
            }

            protected function isBasicDialerCdr(CDR $cdr): bool
            {
                return $this->basicDialer;
            }

            protected function isOutboundFaxCdr(CDR $cdr): bool
            {
                return $this->outboundFax;
            }
        };
    }

    private function profile(
        string $uuid,
        string $destination,
        int $created,
        int $ended,
        ?string $callee = null
    ): array
    {
        return [
            'caller_profile' => [
                'uuid' => $uuid,
                'source' => 'mod_loopback',
                'chan_name' => 'sofia/external/12137577900',
                'destination_number' => $destination,
                'callee_id_number' => $callee,
            ],
            'times' => [
                'profile_created_time' => $created,
                'profile_end_time' => $ended,
            ],
        ];
    }

    private function normalize(CdrDataService $service, Collection $profiles): Collection
    {
        $cdr = new CDR();
        $cdr->forceFill([
            'direction' => 'outbound',
            'xml_cdr_uuid' => 'a5db3c74-7c12-42ff-9b4f-f0f5211a7675',
        ]);

        $method = new ReflectionMethod(CdrDataService::class, 'normalizeMainCallFlowData');
        $method->setAccessible(true);

        return $method->invoke($service, $cdr, $profiles);
    }
}
