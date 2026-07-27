<?php

namespace Tests\Unit;

use App\Services\YealinkRpsRequestSigner;
use PHPUnit\Framework\TestCase;

class YealinkRpsRequestSignerTest extends TestCase
{
    public function test_it_signs_the_official_body_request_shape(): void
    {
        $headers = (new YealinkRpsRequestSigner())->headers(
            'POST',
            '/api/open/v1/server/list',
            '2df23f2d9c255e7138dc603b3847b58a',
            'd4a4be460a8d43609d8e8a5e7d0d4ad1',
            '{"key":"TestServer","skip":0}',
            [],
            '1544008291631',
            'b681e77450a04d22aaffc914a3379561',
        );

        $this->assertSame('SsPhq3/DEuS3yHj3kYOV9w==', $headers['Content-MD5']);
        $this->assertSame('JVPj8C4CVKbFJW6A33yZcdSsde7Hfxy9EoJiE0epeKM=', $headers['X-Ca-Signature']);
    }

    public function test_it_matches_the_official_query_request_signing_example(): void
    {
        $headers = (new YealinkRpsRequestSigner())->headers(
            'GET',
            '/api/open/v1/device/checkMac',
            '2df23f2d9c255e7138dc603b3847b58a',
            'd4a4be460a8d43609d8e8a5e7d0d4ad1',
            null,
            ['mac' => '001565123123'],
            '1544094691000',
            '9e730a223b48433785494801fb016d39',
        );

        $this->assertArrayNotHasKey('Content-MD5', $headers);
        $this->assertSame('+speRmYv89rutzPc9u5Ij1JrtnrUw7nhJnqQfD1h5AU=', $headers['X-Ca-Signature']);
    }
}
