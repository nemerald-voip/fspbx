<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\SipRegistrationSummaryService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SipRegistrationSummaryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_counts_unique_extensions_inside_each_domain(): void
    {
        $service = new SipRegistrationSummaryService();

        $counts = $service->countUniqueExtensionsByRealm(collect([
            ['user' => '1001', 'sip_auth_realm' => 'alpha.example.com'],
            ['user' => '1001', 'sip_auth_realm' => 'alpha.example.com'],
            ['user' => '1002', 'sip_auth_realm' => 'ALPHA.EXAMPLE.COM'],
            ['user' => '1001', 'sip_auth_realm' => 'beta.example.com'],
            ['user' => '', 'sip_auth_user' => '1003', 'sip_auth_realm' => 'alpha.example.com'],
            ['user' => '1003', 'sip_auth_realm' => ''],
        ]));

        $this->assertSame([
            'alpha.example.com' => 3,
            'beta.example.com' => 1,
        ], $counts);
    }

    public function test_it_reuses_the_short_lived_registration_snapshot(): void
    {
        config()->set('cache.default', 'array');
        Cache::flush();

        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('getAllSipRegistrations')
            ->once()
            ->andReturn(collect([
                ['user' => '1001', 'sip_auth_realm' => 'alpha.example.com'],
            ]));

        $this->app->instance(FreeswitchEslService::class, $esl);

        $service = new SipRegistrationSummaryService();

        $this->assertSame(['alpha.example.com' => 1], $service->onlineExtensionCountsByRealm());
        $this->assertSame(['alpha.example.com' => 1], $service->onlineExtensionCountsByRealm());
    }
}
