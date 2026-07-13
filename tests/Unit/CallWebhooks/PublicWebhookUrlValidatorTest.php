<?php

namespace Tests\Unit\CallWebhooks;

use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PublicWebhookUrlValidatorTest extends TestCase
{
    public function test_it_requires_https(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must use HTTPS');

        (new PublicWebhookUrlValidator())->validateAndResolve('http://203.0.113.10/webhook');
    }

    public function test_it_rejects_private_ip_literals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public IP addresses');

        (new PublicWebhookUrlValidator())->validateAndResolve('https://127.0.0.1/webhook');
    }

    public function test_it_rejects_a_host_when_any_dns_answer_is_private(): void
    {
        $validator = new class extends PublicWebhookUrlValidator
        {
            protected function resolveHost(string $host): array
            {
                return ['8.8.8.8', '10.0.0.10'];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public IP addresses');

        $validator->validateAndResolve('https://crm.example.test/webhook');
    }

    public function test_it_returns_a_public_address_for_pinning(): void
    {
        $validator = new class extends PublicWebhookUrlValidator
        {
            protected function resolveHost(string $host): array
            {
                return ['8.8.8.8'];
            }
        };

        $this->assertSame(
            '8.8.8.8',
            $validator->validateAndResolve('https://crm.example.test/webhook')
        );
    }
}
