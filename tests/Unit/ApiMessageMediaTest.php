<?php

namespace Tests\Unit;

use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Outbound\ApiMessageMedia;
use App\Services\Messaging\Outbound\MediaPreparationException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Tests\TestCase;

class ApiMessageMediaTest extends TestCase
{
    private function downloader(array $responses, array &$history, ?PublicWebhookUrlValidator $validator = null): ApiMessageMedia
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));
        $client = new Client(['handler' => $handler]);
        $validator ??= new class extends PublicWebhookUrlValidator {
            protected function resolveHost(string $host): array { return ['93.184.216.34']; }
        };
        return new class($validator, Mockery::mock(MessageMediaObjectStorageService::class), $client) extends ApiMessageMedia {
            public function __construct($validator, $storage, private Client $transport) { parent::__construct($validator, $storage); }
            protected function client(): Client { return $this->transport; }
        };
    }

    public function test_download_pins_dns_disables_proxies_and_redirects_and_bounds_time(): void
    {
        $history = [];
        $service = $this->downloader([new Response(200, ['Content-Type' => 'image/jpeg; charset=binary'], 'image')], $history);
        $result = $service->download('https://files.example.test/photo.jpg?private=do-not-log', 100, 2.5);
        $this->assertSame('image', $result['binary']);
        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame('photo.jpg', $result['name']);
        $this->assertSame(['files.example.test:443:93.184.216.34'], $history[0]['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($history[0]['options']['allow_redirects']);
        $this->assertSame('', $history[0]['options']['proxy']);
        $this->assertEqualsWithDelta(2.5, $history[0]['options']['timeout'], 0.1);
        $this->assertTrue($history[0]['options']['verify']);
    }

    public static function badResponses(): array
    {
        return [
            [302, ['Location' => 'https://127.0.0.1/private'], ''],
            [200, ['Content-Length' => '1000'], 'large'],
            [200, [], str_repeat('x', 21)],
            [200, ['Content-Encoding' => 'gzip'], 'compressed'],
            [200, [], ''], [503, [], 'unavailable'],
        ];
    }

    /** @dataProvider badResponses */
    public function test_bad_or_oversized_responses_fail_without_leaking_urls(int $status, array $headers, string $body): void
    {
        $history = [];
        $service = $this->downloader([new Response($status, $headers, $body)], $history);
        try {
            $service->download('https://files.example.test/photo.jpg?secret=hidden', 20, 1);
            $this->fail('Expected download rejection.');
        } catch (MediaPreparationException $exception) {
            $this->assertStringNotContainsString('hidden', $exception->getMessage());
        }
    }

    public function test_private_addresses_and_changed_dns_are_rejected_before_network_io(): void
    {
        $history = [];
        $validator = new class extends PublicWebhookUrlValidator {
            protected function resolveHost(string $host): array { return ['127.0.0.1']; }
        };
        $service = $this->downloader([], $history, $validator);
        foreach (['https://127.0.0.1/a', 'https://169.254.169.254/a', 'https://100.64.0.1/a', 'https://224.0.0.1/a',
            'https://[::ffff:127.0.0.1]/a', 'https://[64:ff9b::7f00:1]/a', 'https://[2002:7f00:1::]/a',
            'https://changed.example.test/a', 'https://user:password@example.test/a'] as $url) {
            try {
                $service->download($url, 100, 1);
                $this->fail('Expected unsafe URL rejection.');
            } catch (MediaPreparationException) {
                $this->assertCount(0, $history);
            }
        }
    }

    public function test_streaming_download_progress_aborts_at_the_byte_limit(): void
    {
        $history = [];
        $service = $this->downloader([new Response(200, [], 'ok')], $history);
        $service->download('https://files.example.test/a', 100, 1);
        $this->expectException(MediaPreparationException::class);
        ($history[0]['options']['progress'])(0, 101);
    }
}
