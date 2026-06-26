<?php

namespace Tests\Unit;

use App\Services\TigerTmsWebhookSignatureValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

class TigerTmsWebhookSignatureValidatorTest extends TestCase
{
    public function test_validates_ilink_hmac_signature_against_raw_body(): void
    {
        $secretHex = bin2hex(random_bytes(32));
        $timestamp = (string) now()->timestamp;
        $body = '{"eventType":"room.checkout","data":{"roomNumber":"101"}}';
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, hex2bin($secretHex));

        config([
            'tigertms.webhook_secret' => $secretHex,
            'tigertms.webhook_signature_tolerance_seconds' => 300,
        ]);

        $result = app(TigerTmsWebhookSignatureValidator::class)->verify(
            $this->request($timestamp, $signature, $body)
        );

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
    }

    public function test_rejects_signature_when_raw_body_changes(): void
    {
        $secretHex = bin2hex(random_bytes(32));
        $timestamp = (string) now()->timestamp;
        $body = '{"eventType":"room.checkout","data":{"roomNumber":"101"}}';
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, hex2bin($secretHex));

        config([
            'tigertms.webhook_secret' => $secretHex,
            'tigertms.webhook_signature_tolerance_seconds' => 300,
        ]);

        $result = app(TigerTmsWebhookSignatureValidator::class)->verify(
            $this->request($timestamp, $signature, '{"eventType":"room.checkout","data":{"roomNumber":"102"}}')
        );

        $this->assertTrue($result['configured']);
        $this->assertFalse($result['valid']);
    }

    public function test_reports_unconfigured_secret(): void
    {
        config(['tigertms.webhook_secret' => null]);

        $result = app(TigerTmsWebhookSignatureValidator::class)->verify(
            $this->request((string) now()->timestamp, 'sha256=' . str_repeat('0', 64), '{}')
        );

        $this->assertFalse($result['configured']);
        $this->assertFalse($result['valid']);
    }

    private function request(string $timestamp, string $signature, string $body): Request
    {
        return Request::create(
            '/api/pms/tigertms',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ILINK_TIMESTAMP' => $timestamp,
                'HTTP_X_ILINK_SIGNATURE' => $signature,
            ],
            $body
        );
    }
}
