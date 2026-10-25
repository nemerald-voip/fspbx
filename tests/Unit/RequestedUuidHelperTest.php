<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestedUuidHelperTest extends TestCase
{
    private const UUID = '2d6edddc-84c2-4a2f-ad1a-c13c225a52c9';

    private function requestWith(array $query): Request
    {
        return Request::create('/sip-profiles', 'GET', $query);
    }

    public function test_returns_a_valid_uuid_lower_cased(): void
    {
        $this->assertSame(self::UUID, fspbx_requested_uuid('edit', $this->requestWith(['edit' => strtoupper(self::UUID)])));
    }

    public function test_returns_null_when_the_parameter_is_missing(): void
    {
        $this->assertNull(fspbx_requested_uuid('edit', $this->requestWith([])));
    }

    public function test_returns_null_for_values_that_are_not_uuids(): void
    {
        foreach (['', 'internal', '123', self::UUID . 'x', '../../etc/passwd', "' or 1=1 --", '<script>'] as $value) {
            $this->assertNull(fspbx_requested_uuid('edit', $this->requestWith(['edit' => $value])), $value);
        }
    }

    public function test_returns_null_when_the_parameter_is_an_array(): void
    {
        $this->assertNull(fspbx_requested_uuid('edit', $this->requestWith(['edit' => [self::UUID]])));
    }

    public function test_reads_the_requested_key_only(): void
    {
        $request = $this->requestWith(['other' => self::UUID]);

        $this->assertNull(fspbx_requested_uuid('edit', $request));
        $this->assertSame(self::UUID, fspbx_requested_uuid('other', $request));
    }
}
