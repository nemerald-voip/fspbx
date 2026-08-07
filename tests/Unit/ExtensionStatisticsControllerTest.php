<?php

namespace Tests\Unit;

use App\Http\Controllers\ExtensionStatisticsController;
use App\Http\Requests\ExtensionStatisticsRequest;
use App\Services\CdrDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ExtensionStatisticsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_statistics_parameters_are_whitelisted_and_forced_to_the_session_domain(): void
    {
        session(['domain_uuid' => '11111111-1111-4111-8111-111111111111']);

        $request = Mockery::mock(ExtensionStatisticsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'filter' => [
                'search' => ' 1001 ',
                'showGlobal' => true,
                'dateRange' => [
                    '2026-08-07T04:00:00.000Z',
                    '2026-08-08T03:59:59.000Z',
                ],
            ],
            'page' => 2,
            'per_page' => 100,
        ]);

        $params = $this->statisticsParams($request);

        $this->assertSame('11111111-1111-4111-8111-111111111111', $params['domain_uuid']);
        $this->assertSame(2, $params['page']);
        $this->assertSame(100, $params['per_page']);
        $this->assertSame('1001', $params['filter']['search']);
        $this->assertFalse($params['filter']['showGlobal']);
        $this->assertSame(1786075200, $params['filter']['startPeriod']);
        $this->assertSame(1786161599, $params['filter']['endPeriod']);
    }

    public function test_missing_date_range_defaults_to_the_current_account_day(): void
    {
        $domainUuid = '11111111-1111-4111-8111-111111111111';
        session(['domain_uuid' => $domainUuid]);
        Cache::put("{$domainUuid}_timeZone", 'America/New_York', 60);
        Carbon::setTestNow(Carbon::parse('2026-08-07T12:00:00Z'));

        $request = Mockery::mock(ExtensionStatisticsRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([]);

        try {
            $params = $this->statisticsParams($request);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(1786075200, $params['filter']['startPeriod']);
        $this->assertSame(1786161599, $params['filter']['endPeriod']);
    }

    private function statisticsParams(ExtensionStatisticsRequest $request): array
    {
        $controller = new ExtensionStatisticsController(new CdrDataService());
        $method = new ReflectionMethod($controller, 'statisticsParams');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }
}
