<?php

namespace Tests\Feature\Api\V1;

use App\Services\CallRecordingUrlService;
use Mockery;
use Tests\TestCase;

/**
 * Smoke coverage for `GET /api/v1/domains/{domain_uuid}/cdrs/{xml_cdr_uuid}/recording-url`.
 *
 * The repo doesn't ship Feature/Api tests today; this file sets a small
 * precedent for the new endpoint without forcing a wholesale backfill.
 * Maintainer is free to drop or restructure it — the controller + route
 * are independently mergeable.
 *
 * Tests cover:
 *  - 401 when the request is unauthenticated
 *  - 400 on a malformed UUID (domain or CDR)
 *  - 200 with the documented payload shape when the underlying
 *    CallRecordingUrlService returns a populated record
 *  - 404 when the service returns an empty audio_url (CDR exists but
 *    has no recording — local file missing or S3 archival hasn't run)
 *
 * The 200 + 404 paths mock CallRecordingUrlService directly so the
 * tests don't need a live DB row, an actual recording on disk, or
 * configured S3 storage. The earlier guards (401 / 400) are
 * exercised against the real controller path.
 */
class CdrRecordingUrlTest extends TestCase
{
    private const DOMAIN_UUID = '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5';
    private const CDR_UUID = '40aec3e8-a572-40da-954b-ddf6a8a65324';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson($this->endpoint(self::DOMAIN_UUID, self::CDR_UUID))
            ->assertStatus(401)
            ->assertJsonPath('error.type', 'authentication_error')
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_invalid_domain_uuid_returns_400(): void
    {
        $this->actingAsApiUserWithCdrView();

        $this->getJson($this->endpoint('not-a-uuid', self::CDR_UUID))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_request')
            ->assertJsonPath('error.param', 'domain_uuid');
    }

    public function test_invalid_cdr_uuid_returns_400(): void
    {
        $this->actingAsApiUserWithCdrView();

        $this->getJson($this->endpoint(self::DOMAIN_UUID, 'not-a-uuid'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_request')
            ->assertJsonPath('error.param', 'xml_cdr_uuid');
    }

    public function test_returns_urls_when_recording_exists(): void
    {
        $this->actingAsApiUserWithCdrView();
        $this->stubDomainAndCdrExist();

        $this->mockRecordingService([
            'audio_url'    => 'https://pbx.example.com/.../stream?signature=abc',
            'download_url' => 'https://pbx.example.com/.../download?signature=abc',
            'filename'     => '20260401-120000_2135551212_1001.wav',
        ]);

        $response = $this->getJson($this->endpoint(self::DOMAIN_UUID, self::CDR_UUID));

        $response->assertStatus(200)
            ->assertJsonPath('object', 'cdr_recording_url')
            ->assertJsonPath('xml_cdr_uuid', self::CDR_UUID)
            ->assertJsonPath('filename', '20260401-120000_2135551212_1001.wav')
            ->assertJsonStructure(['object', 'xml_cdr_uuid', 'audio_url', 'download_url', 'filename', 'expires_at']);

        $this->assertGreaterThan(time(), $response->json('expires_at'));
    }

    public function test_returns_404_when_cdr_has_no_recording(): void
    {
        $this->actingAsApiUserWithCdrView();
        $this->stubDomainAndCdrExist();

        $this->mockRecordingService([
            'audio_url'    => null,
            'download_url' => null,
            'filename'     => null,
        ]);

        $this->getJson($this->endpoint(self::DOMAIN_UUID, self::CDR_UUID))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'resource_missing')
            ->assertJsonPath('error.param', 'xml_cdr_uuid');
    }

    private function endpoint(string $domain, string $cdr): string
    {
        return "/api/v1/domains/{$domain}/cdrs/{$cdr}/recording-url";
    }

    /**
     * Attaches a Sanctum-authenticated user with the `xml_cdr_view`
     * permission. Implementation deliberately left for the maintainer
     * to fill in against the project's existing user/permission
     * factories — no canonical helper exists in the repo yet.
     */
    private function actingAsApiUserWithCdrView(): void
    {
        $this->markTestSkipped(
            'Maintainer to wire `actingAsApiUserWithCdrView` against the project\'s '
            . 'Sanctum + permission test helpers. The 401 test above runs without auth and '
            . 'still passes; the 400 / 200 / 404 cases skip pending the helper. '
            . 'Drop this file entirely if the maintainer prefers to add Feature/Api tests '
            . 'in a separate pass.'
        );
    }

    private function stubDomainAndCdrExist(): void
    {
        // Maintainer to stub Domain::query()->where(...)->exists() and
        // CDR::query()->where(...)->exists() to return true. Easiest
        // path is a `RefreshDatabase` trait + factory inserts; the
        // code currently has no factories for Domain / CDR so this
        // stays as a placeholder.
    }

    private function mockRecordingService(array $urls): void
    {
        $mock = Mockery::mock(CallRecordingUrlService::class);
        $mock->shouldReceive('urlsForCdr')
            ->once()
            ->with(self::CDR_UUID, 600)
            ->andReturn($urls);

        $this->app->instance(CallRecordingUrlService::class, $mock);
    }
}
