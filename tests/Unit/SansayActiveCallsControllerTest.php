<?php

namespace Tests\Unit;

use App\Http\Controllers\SansayActiveCallsController;
use App\Services\SansayApiService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class SansayActiveCallsControllerTest extends TestCase
{
    public function test_initial_page_does_not_contact_the_sbc(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldNotReceive('fetchActiveCalls');
        $request = Request::create('/sansay/active-calls');
        $request->headers->set('X-Inertia', 'true');

        $response = (new SansayActiveCallsController($service))->index($request)->toResponse($request)->getData(true);

        $this->assertSame('SansayActiveCalls', $response['component']);
        $this->assertArrayNotHasKey('data', $response['props']);
        $this->assertStringEndsWith('/api/sansay/active-calls/data', $response['props']['routes']['data_route']);
        $this->assertStringEndsWith('/api/sansay/active-calls/delete', $response['props']['routes']['delete']);
    }

    public function test_data_and_select_all_use_the_selected_server_and_search(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldReceive('fetchActiveCalls')->twice()->with('server2')->andReturn(collect([
            ['callID' => 'match', 'ani' => 'Alice', 'duration' => 3661],
            ['callID' => 'other', 'ani' => 'Bob', 'duration' => 1],
        ]));
        $controller = new SansayActiveCallsController($service);
        $request = Request::create('/api/sansay/active-calls/data', 'GET', [
            'filter' => ['server' => 'server2', 'search' => 'ALICE'],
        ]);

        $data = $controller->getData($request)->toArray();
        $selection = $controller->selectAll($request)->getData(true);

        $this->assertSame(['match'], array_column($data['data'], 'callID'));
        $this->assertSame('01:01:01', $data['data'][0]['duration_formatted']);
        $this->assertSame(['match'], $selection['items']);
    }

    public function test_later_pages_are_json_arrays_and_disappearing_pages_are_clamped(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldReceive('fetchActiveCalls')->once()->with('server1')->andReturn(
            collect(range(55, 1))->map(fn ($i) => ['callID' => 'call-'.$i, 'duration' => $i])
        );
        $request = Request::create('/api/sansay/active-calls/data', 'GET', [
            'filter' => ['server' => 'server1'], 'page' => 7, 'per_page' => 1,
        ]);

        $data = (new SansayActiveCallsController($service))->getData($request)->toArray();

        $this->assertSame(2, $data['current_page']);
        $this->assertSame(50, $data['per_page']);
        $this->assertTrue(array_is_list($data['data']));
        $this->assertSame(['call-51', 'call-52', 'call-53', 'call-54', 'call-55'], array_column($data['data'], 'callID'));
    }

    public function test_no_server_returns_an_empty_list_without_contacting_the_sbc(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldNotReceive('fetchActiveCalls');

        $data = (new SansayActiveCallsController($service))->getData(Request::create('/api/sansay/active-calls/data'))->toArray();

        $this->assertSame([], $data['data']);
        $this->assertSame(0, $data['total']);
    }

    public function test_delete_forwards_calls_to_the_selected_server(): void
    {
        $calls = [['callID' => 'selected-call']];
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldReceive('deleteActiveCalls')->once()->with('server2', $calls)->andReturn([]);
        $request = Request::create('/api/sansay/active-calls/delete', 'POST', [
            'filter' => ['server' => 'server2'], 'callsData' => $calls,
        ]);

        $response = (new SansayActiveCallsController($service))->destroy($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('success', $response->getData(true)['messages']);
    }
}
