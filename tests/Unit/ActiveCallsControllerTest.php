<?php

namespace Tests\Unit;

use App\Http\Controllers\ActiveCallsController;
use App\Services\FreeswitchEslService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActiveCallsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        session(['domain_uuid' => 'active-calls-test', 'domain_name' => 'alpha.test']);
        Cache::put('active-calls-test_timeZone', 'America/New_York', 60);
        $this->permissions('call_active_view');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initial_page_contains_configuration_without_loading_calls(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('getAllChannels');
        $this->app->instance(FreeswitchEslService::class, $esl);
        $request = Request::create('/active-calls');
        $request->headers->set('X-Inertia', 'true');

        $response = (new ActiveCallsController())->index($request)->toResponse($request)->getData(true);

        $this->assertSame('ActiveCalls', $response['component']);
        $this->assertArrayNotHasKey('data', $response['props']);
        $this->assertStringEndsWith('/api/active-calls/data', $response['props']['routes']['data_route']);
        $this->assertStringEndsWith('/api/active-calls/action', $response['props']['routes']['action']);
        $this->assertFalse($response['props']['showGlobal']);
    }

    public function test_data_requires_view_permission_before_reading_channels(): void
    {
        $this->permissions();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('getAllChannels');

        try {
            (new ActiveCallsController())->getData(Request::create('/api/active-calls/data'), $esl);
            $this->fail('Expected a forbidden response.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_search_and_tenant_scope_apply_even_when_global_is_requested(): void
    {
        $request = Request::create('/api/active-calls/data', 'GET', [
            'filter' => ['search' => 'ALICE', 'showGlobal' => 'true'],
        ]);
        $esl = $this->channels([
            $this->channel('local-match', 'alpha.test', 'Alice'),
            $this->channel('local-other', 'alpha.test', 'Bob'),
            $this->channel('other-tenant', 'beta.test', 'Alice'),
        ]);

        $result = (new ActiveCallsController())->getData($request, $esl)->toArray();

        $this->assertSame(['local-match'], array_column($result['data'], 'uuid'));
        $this->assertSame(1, $result['total']);
        $this->assertSame('America/New_York', $result['data'][0]['display_timezone']);
        $this->assertSame(1700000000000, $result['data'][0]['start_epoch']);
        $this->assertSame('playback: test.wav', $result['data'][0]['app_full']);
    }

    public function test_authorized_global_data_is_paginated_and_sorted_by_duration(): void
    {
        $this->permissions('call_active_view', 'call_active_all');
        $calls = [];
        for ($i = 1; $i <= 55; $i++) {
            $calls[] = $this->channel('call-'.$i, $i % 2 ? 'alpha.test' : 'beta.test', 'Alice', 1700000000 + $i);
        }
        $request = Request::create('/api/active-calls/data', 'GET', [
            'filter' => ['showGlobal' => true], 'sort' => '-duration', 'page' => 2, 'per_page' => 50,
        ]);

        $result = (new ActiveCallsController())->getData($request, $this->channels($calls))->toArray();

        $this->assertSame(55, $result['total']);
        $this->assertSame(2, $result['current_page']);
        $this->assertSame(['call-51', 'call-52', 'call-53', 'call-54', 'call-55'], array_column($result['data'], 'uuid'));
        $this->assertSame('UTC', $result['data'][0]['display_timezone']);
    }

    public function test_disappearing_last_page_and_invalid_sort_use_safe_defaults(): void
    {
        $request = Request::create('/api/active-calls/data', 'GET', ['page' => 4, 'per_page' => 1, 'sort' => 'unknown']);
        $esl = $this->channels([
            $this->channel('older', 'alpha.test', 'Alice', 1700000000),
            $this->channel('newer', 'alpha.test', 'Alice', 1700000010),
        ]);

        $result = (new ActiveCallsController())->getData($request, $esl)->toArray();

        $this->assertSame(1, $result['current_page']);
        $this->assertSame(50, $result['per_page']);
        $this->assertSame(['newer', 'older'], array_column($result['data'], 'uuid'));
    }

    public function test_select_all_uses_the_same_search_and_tenant_filters_as_data(): void
    {
        $request = Request::create('/api/active-calls/select-all', 'POST', [
            'filter' => ['search' => 'Alice', 'showGlobal' => true],
        ]);
        $esl = $this->channels([
            $this->channel('matched', 'alpha.test', 'Alice'),
            $this->channel('unmatched', 'alpha.test', 'Bob'),
            $this->channel('other-tenant', 'beta.test', 'Alice'),
        ]);

        $response = (new ActiveCallsController())->selectAll($request, $esl);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['matched'], $response->getData(true)['items']);
    }

    public function test_action_and_select_all_retain_their_permission_checks(): void
    {
        $this->permissions();
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('getAllChannels');
        $esl->shouldNotReceive('killChannel');
        $controller = new ActiveCallsController();

        $this->assertSame(403, $controller->handleAction($esl)->getStatusCode());
        $this->assertSame(403, $controller->selectAll(Request::create('/api/active-calls/select-all'), $esl)->getStatusCode());
    }

    private function permissions(string ...$names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function channels(array $calls): FreeswitchEslService
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('getAllChannels')->once()->andReturn(collect($calls));

        return $esl;
    }

    private function channel(string $uuid, string $domain, string $name, int $created = 1700000000): array
    {
        return [
            'uuid' => $uuid, 'context' => $domain, 'cid_name' => $name,
            'created_epoch' => $created, 'application' => 'playback', 'application_data' => 'test.wav',
        ];
    }
}
