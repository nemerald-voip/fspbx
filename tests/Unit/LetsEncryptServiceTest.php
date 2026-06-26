<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\LetsEncryptService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LetsEncryptServiceTest extends TestCase
{
    private string $webroot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webroot = sys_get_temp_dir().'/fspbx-acme-'.bin2hex(random_bytes(8));
        mkdir($this->webroot, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->webroot);

        parent::tearDown();
    }

    public function test_http_challenge_is_published_to_peers_before_preflight_and_cleaned_up(): void
    {
        $service = $this->testableService();
        $path = '/.well-known/acme-challenge/test_token';
        $peers = ['https://fs2.example.com'];

        $cleanup = $service->publishForTest('fs2.example.com', $path, 'proof', $this->webroot, $peers);

        $this->assertSame(['push:present', 'verify:fs2.example.com'], $service->events);
        $this->assertSame('proof', file_get_contents($this->webroot.$path));

        $cleanup();

        $this->assertFileDoesNotExist($this->webroot.$path);
        $this->assertSame('push:cleanup', $service->events[2]);
    }

    public function test_failed_peer_publication_aborts_and_cleans_up_before_validation(): void
    {
        $service = $this->testableService();
        $service->failPresent = true;
        $path = '/.well-known/acme-challenge/test_token';

        try {
            $service->publishForTest(
                'fs2.example.com',
                $path,
                'proof',
                $this->webroot,
                ['https://fs2.example.com']
            );
            $this->fail('Expected peer publication to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Failed to publish the ACME challenge token', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($this->webroot.$path);
        $this->assertSame(['push:present', 'push:cleanup'], $service->events);
    }

    public function test_peer_can_store_and_remove_a_valid_challenge_token(): void
    {
        $service = $this->testableService();
        $path = $this->webroot.'/.well-known/acme-challenge/peer_token';

        $service->storeChallengeToken('peer_token', 'peer-proof');

        $this->assertSame('peer-proof', file_get_contents($path));

        $service->removeChallengeToken('peer_token');

        $this->assertFileDoesNotExist($path);
    }

    public function test_peer_rejects_a_challenge_token_that_could_escape_the_webroot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid ACME challenge token.');

        $this->testableService()->storeChallengeToken('../outside', 'peer-proof');
    }

    public function test_peer_challenge_request_uses_shared_secret_and_expected_payload(): void
    {
        Http::fake([
            'https://fs2.example.com/api/letsencrypt/challenge' => Http::response([], 200),
        ]);

        $service = Mockery::mock(
            LetsEncryptService::class,
            [Mockery::mock(FreeswitchEslService::class)]
        )->makePartial();
        $service->shouldReceive('config')->andReturn([
            'push_secret' => 'shared-secret-value',
            'webroot' => $this->webroot,
        ]);

        $results = $service->pushChallengeToPeers(
            'present',
            'peer_token',
            'peer-proof',
            ['https://fs2.example.com']
        );

        $this->assertTrue($results[0]['ok']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://fs2.example.com/api/letsencrypt/challenge'
                && $request->hasHeader('X-FsPbx-Cert-Secret', 'shared-secret-value')
                && $request['action'] === 'present'
                && $request['token'] === 'peer_token'
                && $request['value'] === 'peer-proof';
        });
    }

    public function test_key_loaders_create_the_missing_letsencrypt_storage_directory(): void
    {
        $service = $this->testableService();
        $storageDir = $this->webroot.'/storage/letsencrypt';

        $accountKey = $service->accountKeyForTest();
        $domainKey = $service->domainKeyForTest();

        $this->assertDirectoryExists($storageDir);
        $this->assertSame('file://'.$storageDir.'/account.key', $accountKey);
        $this->assertFileExists($storageDir.'/account.key');
        $this->assertFileExists($storageDir.'/domain.key');
        $this->assertStringContainsString('PRIVATE KEY', $domainKey);
    }

    public function test_revoke_key_lookup_does_not_create_an_unrelated_account_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Revoke the certificate from the node that issued it.');

        $this->testableService()->accountKeyForTest(false);
    }

    private function testableService(): TestableLetsEncryptService
    {
        return new TestableLetsEncryptService(
            Mockery::mock(FreeswitchEslService::class),
            $this->webroot
        );
    }
}

class TestableLetsEncryptService extends LetsEncryptService
{
    public array $events = [];
    public bool $failPresent = false;

    public function __construct(FreeswitchEslService $esl, private string $testWebroot)
    {
        parent::__construct($esl);
    }

    public function config(): array
    {
        return [
            'domain' => 'app.example.com fs1.example.com fs2.example.com',
            'push_secret' => 'shared-secret-value',
            'webroot' => $this->testWebroot,
        ];
    }

    public function publishForTest(
        string $domain,
        string $path,
        string $value,
        string $webroot,
        array $peers
    ): callable {
        return $this->publishHttpChallenge($domain, $path, $value, $webroot, $peers);
    }

    public function accountKeyForTest(bool $create = true): string
    {
        return $this->accountKey($create);
    }

    public function domainKeyForTest(): string
    {
        return $this->domainKey();
    }

    public function pushChallengeToPeers(
        string $action,
        string $token,
        ?string $value = null,
        ?array $hosts = null
    ): array {
        $this->events[] = 'push:'.$action;

        if ($action === 'present' && $this->failPresent) {
            return [[
                'host' => $hosts[0],
                'ok' => false,
                'status' => 503,
                'error' => 'HTTP 503',
            ]];
        }

        return array_map(fn ($host) => [
            'host' => $host,
            'ok' => true,
            'status' => 200,
            'error' => null,
        ], $hosts ?? []);
    }

    protected function verifyChallengeReachable(string $domain, string $path, string $expected): void
    {
        $this->events[] = 'verify:'.$domain;
    }

    protected function storageDir(): string
    {
        return $this->testWebroot.'/storage/letsencrypt';
    }
}
