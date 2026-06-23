<?php

namespace Tests\Unit;

use App\Services\TigerTmsApiClient;
use App\Services\TigerTmsSiteMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TigerTmsApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        config([
            'tigertms.base_url' => 'https://tiger.test/ilinkweb',
            'tigertms.username' => 'api-user',
            'tigertms.password' => 'api-pass',
            'tigertms.test_site_id' => '001',
            'tigertms.test_domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
        ]);
    }

    public function test_checkin_authenticates_and_maps_test_domain_to_site_001(): void
    {
        Http::fake([
            'https://tiger.test/ilinkweb/api/authenticate/v1/user' => Http::response([
                'token' => 'jwt-token',
                'expires' => now()->addHour()->toIso8601String(),
            ]),
            'https://tiger.test/ilinkweb/api/pms/v1/site/001/room/110/checkin' => Http::response([
                'result' => 'success',
            ]),
        ]);

        $client = new TigerTmsApiClient(new TigerTmsSiteMapper());
        $response = $client->checkIn('7d58342b-2b29-4dcf-92d6-e9a9e002a4e5', '110', [
            'reservationNumber' => 'ABC-123',
            'arrivalDate' => '2026-06-23',
            'departureDate' => '2026-06-25',
        ]);

        $this->assertTrue($response->successful());

        Http::assertSent(fn ($request) => $request->url() === 'https://tiger.test/ilinkweb/api/authenticate/v1/user'
            && $request['username'] === 'api-user'
            && $request['password'] === 'api-pass');

        Http::assertSent(fn ($request) => $request->url() === 'https://tiger.test/ilinkweb/api/pms/v1/site/001/room/110/checkin'
            && $request->hasHeader('Authorization', 'Bearer jwt-token')
            && $request['reservationNumber'] === 'ABC-123');
    }

    public function test_checkout_leaves_production_domain_uuid_as_site(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';

        Http::fake([
            'https://tiger.test/ilinkweb/api/authenticate/v1/user' => Http::response([
                'token' => 'jwt-token',
                'expires' => now()->addHour()->toIso8601String(),
            ]),
            "https://tiger.test/ilinkweb/api/pms/v1/site/{$uuid}/room/110/checkout" => Http::response([
                'result' => 'success',
            ]),
        ]);

        $response = (new TigerTmsApiClient(new TigerTmsSiteMapper()))->checkOut($uuid, '110');

        $this->assertTrue($response->successful());

        Http::assertSent(fn ($request) => $request->url() === "https://tiger.test/ilinkweb/api/pms/v1/site/{$uuid}/room/110/checkout");
    }

    public function test_authentication_accepts_token_response_field_casing_variants(): void
    {
        Http::fake([
            'https://tiger.test/ilinkweb/api/authenticate/v1/user' => Http::response([
                'Token' => 'jwt-token',
                'Expires' => now()->addHour()->toIso8601String(),
            ]),
            'https://tiger.test/ilinkweb/api/pms/v1/site/001/room/110/checkout' => Http::response([
                'result' => 'success',
            ]),
        ]);

        $response = (new TigerTmsApiClient(new TigerTmsSiteMapper()))->checkOut(
            '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            '110'
        );

        $this->assertTrue($response->successful());

        Http::assertSent(fn ($request) => $request->url() === 'https://tiger.test/ilinkweb/api/pms/v1/site/001/room/110/checkout'
            && $request->hasHeader('Authorization', 'Bearer jwt-token'));
    }
}
