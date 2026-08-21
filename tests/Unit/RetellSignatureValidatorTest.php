<?php

namespace Tests\Unit;

use App\Services\AiTools\RetellSignatureValidator;
use PHPUnit\Framework\TestCase;

class RetellSignatureValidatorTest extends TestCase
{
    public function test_it_accepts_the_documented_signature_format_within_five_minutes(): void
    {
        $body = '{"name":"fspbx_send_email"}';
        $timestamp = 1720000000000;
        $digest = hash_hmac('sha256', $body . $timestamp, 'retell-key');

        $this->assertTrue((new RetellSignatureValidator())->valid(
            $body,
            "v={$timestamp},d={$digest}",
            'retell-key',
            $timestamp + 299999,
        ));
    }

    public function test_it_rejects_stale_or_modified_requests(): void
    {
        $body = '{"name":"fspbx_send_email"}';
        $timestamp = 1720000000000;
        $digest = hash_hmac('sha256', $body . $timestamp, 'retell-key');
        $validator = new RetellSignatureValidator();

        $this->assertFalse($validator->valid($body, "v={$timestamp},d={$digest}", 'retell-key', $timestamp + 300001));
        $this->assertFalse($validator->valid($body . ' ', "v={$timestamp},d={$digest}", 'retell-key', $timestamp));
        $this->assertFalse($validator->valid($body, 'invalid', 'retell-key', $timestamp));
    }
}
