<?php

namespace Tests\Unit;

use App\Http\Controllers\AppsController;
use App\Http\Controllers\SansayRegistrationsController;
use App\Http\Controllers\UserLogsController;
use App\Services\SansayApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdditionalAxiosPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.connections.additional_pages_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.default', 'additional_pages_test');
        DB::purge('additional_pages_test');
        Carbon::setTestNow(Carbon::parse('2026-09-21T12:00:00Z'));
        session([
            'domain_uuid' => 'account-a', 'domain_name' => 'alpha.test',
            'domains' => collect([['domain_uuid' => 'account-a'], ['domain_uuid' => 'account-b']]),
            'permissions' => [(object) ['permission_name' => 'user_log_view']],
        ]);
        Cache::put('account-a_timeZone', 'America/New_York', 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('additional_pages_test');
        parent::tearDown();
    }

    public function test_shells_do_not_load_records_or_contact_sansay(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldNotReceive('fetchStats');
        Carbon::setTestNow(Carbon::parse('2026-09-21T12:00:00Z'));
        DB::enableQueryLog();
        foreach ([new AppsController(), new UserLogsController(), new SansayRegistrationsController($service)] as $controller) {
            $request = $this->request('/page');
            $request->headers->set('X-Inertia', 'true');
            $response = $controller->index($request)->toResponse($request)->getData(true);
            $this->assertArrayNotHasKey('data', $response['props']);
            $this->assertStringContainsString('/api/', $response['props']['routes']['data_route']);
            if ($controller instanceof UserLogsController) {
                $this->assertSame('America/New_York', $response['props']['timezone']);
                $this->assertSame('2026-09-21T04:00:00+00:00', $response['props']['startPeriod']);
            }
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_ringotel_data_preserves_enabled_accounts_and_activation_sorting(): void
    {
        $this->domains();
        Schema::create('v_domain_settings', function (Blueprint $table) {
            foreach (['domain_setting_uuid', 'domain_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value'] as $name) $table->string($name)->nullable();
            $table->boolean('domain_setting_enabled');
        });
        DB::table('v_domains')->where('domain_uuid', 'account-c')->update(['domain_enabled' => 'false']);
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => 'org', 'domain_uuid' => 'account-b', 'domain_setting_category' => 'app shell',
            'domain_setting_subcategory' => 'org_id', 'domain_setting_value' => 'org-b', 'domain_setting_enabled' => true,
        ]);
        $controller = new AppsController();
        $data = $controller->getData($this->request('/api/apps/data', ['sort' => '-ringotel_status']))->toArray();

        $this->assertSame(['account-b', 'account-a'], array_column($data['data'], 'domain_uuid'));
        $this->assertSame(['true', 'false'], array_column($data['data'], 'ringotel_status'));
        $selection = $controller->selectAll($this->request('/api/apps/select-all'))->getData(true);
        $this->assertSame(['account-a', 'account-b'], $selection['items']);
    }

    public function test_user_logs_and_selection_share_dates_and_accessible_account_scope(): void
    {
        $this->domains();
        Schema::create('v_user_logs', function (Blueprint $table) {
            foreach (['user_log_uuid', 'domain_uuid', 'timestamp', 'user_uuid', 'username', 'email', 'type', 'result', 'remote_address', 'user_agent'] as $name) $table->string($name)->nullable();
        });
        foreach ([['local', 'account-a', '2026-09-21T10:00:00+00:00'], ['old', 'account-a', '2026-09-20T10:00:00+00:00'], ['allowed', 'account-b', '2026-09-21T10:00:00+00:00'], ['hidden', 'account-c', '2026-09-21T10:00:00+00:00']] as [$id, $domain, $time]) {
            DB::table('v_user_logs')->insert(['user_log_uuid' => $id, 'domain_uuid' => $domain, 'timestamp' => $time, 'username' => $id]);
        }
        $controller = new UserLogsController();
        $filters = ['dateRange' => ['2026-09-21T04:00:00Z', '2026-09-22T03:59:59Z']];
        $data = $controller->getData($this->request('/api/user-logs/data', ['filter' => $filters]))->toArray();
        $this->assertSame(['local'], array_column($data['data'], 'user_log_uuid'));

        $filters['showGlobal'] = true;
        $data = $controller->getData($this->request('/api/user-logs/data', ['filter' => $filters]))->toArray();
        $selection = $controller->selectAll($this->request('/api/user-logs/select-all', ['filter' => $filters]))->getData(true);
        $this->assertEqualsCanonicalizing(['local', 'allowed'], array_column($data['data'], 'user_log_uuid'));
        $this->assertEqualsCanonicalizing(['local', 'allowed'], $selection['items']);

        $grammar = new \Illuminate\Database\Query\Grammars\PostgresGrammar();
        $grammar->setConnection(DB::connection());
        DB::connection()->setQueryGrammar($grammar);
        $filters['search'] = 'Alice';
        $queries = DB::pretend(fn () => $controller->selectAll($this->request('/api/user-logs/select-all', ['filter' => $filters])));
        $this->assertContains('%Alice%', $queries[0]['bindings']);
        $this->assertContains('account-a', $queries[0]['bindings']);
        $this->assertContains('account-b', $queries[0]['bindings']);
    }

    public function test_user_log_data_and_selection_require_permission(): void
    {
        session(['permissions' => []]);
        foreach (['getData', 'selectAll'] as $method) {
            try {
                (new UserLogsController())->$method($this->request('/api/user-logs/data'));
                $this->fail('Expected a forbidden response.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_user_logs_reject_reversed_date_ranges(): void
    {
        $this->expectException(ValidationException::class);
        (new UserLogsController())->getData($this->request('/api/user-logs/data', [
            'filter' => ['dateRange' => ['2026-09-22T00:00:00Z', '2026-09-21T00:00:00Z']],
        ]));
    }

    public function test_sansay_local_search_and_select_all_use_the_same_server_and_account(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldReceive('fetchStats')->twice()->with(['server' => 'server2', 'userDomain' => 'alpha.test'])->andReturn(collect([
            ['id' => 'local', 'username' => 'Alice', 'userDomain' => 'alpha.test'],
            ['id' => 'other', 'username' => 'Bob', 'userDomain' => 'alpha.test'],
            ['id' => 'global', 'username' => 'Alice', 'userDomain' => 'beta.test'],
        ]));
        $controller = new SansayRegistrationsController($service);
        $request = $this->request('/api/sansay/registrations/data', ['filter' => ['server' => 'server2', 'showGlobal' => false, 'search' => 'ALICE']]);
        $data = $controller->getData($request)->toArray();
        $selection = $controller->selectAll($request)->getData(true);

        $this->assertSame(['local'], array_column($data['data'], 'id'));
        $this->assertSame(['local'], $selection['items']);
    }

    public function test_sansay_global_sorting_pagination_and_delete_payload(): void
    {
        $service = Mockery::mock(SansayApiService::class);
        $service->shouldReceive('fetchStats')->once()->with(['server' => 'server1'])->andReturn(collect(range(1, 55))->map(fn ($i) => ['id' => 'reg-'.$i, 'userPort' => $i]));
        $service->shouldReceive('deleteStats')->once()->with('server1', [['id' => 'reg-5']])->andReturn([]);
        $controller = new SansayRegistrationsController($service);
        $request = $this->request('/api/sansay/registrations/data', ['filter' => ['server' => 'server1', 'showGlobal' => 'true'], 'sort' => '-userPort', 'page' => 4]);
        $data = $controller->getData($request)->toArray();
        $this->assertSame(2, $data['current_page']);
        $this->assertTrue(array_is_list($data['data']));
        $this->assertSame(['reg-5', 'reg-4', 'reg-3', 'reg-2', 'reg-1'], array_column($data['data'], 'id'));
        $response = $controller->destroy($this->request('/api/sansay/registrations/delete', ['filter' => ['server' => 'server1'], 'statsData' => [['id' => 'reg-5']]]));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_new_data_and_action_routes_use_the_authenticated_api_group(): void
    {
        foreach (['apps.data', 'apps.select.all', 'apps.item.options', 'apps.organization.update', 'apps.connection.create', 'apps.token.update', 'apps.users.sync', 'sansay.registrations.data', 'sansay.registrations.delete', 'user-logs.data'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertStringStartsWith('api/', $route->uri());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }

    private function domains(): void
    {
        Schema::create('v_domains', function (Blueprint $table) {
            foreach (['domain_uuid', 'domain_name', 'domain_description', 'domain_enabled'] as $name) $table->string($name)->nullable();
        });
        foreach (['a', 'b', 'c'] as $id) {
            DB::table('v_domains')->insert(['domain_uuid' => 'account-'.$id, 'domain_name' => $id.'.test', 'domain_description' => $id, 'domain_enabled' => 'true']);
        }
    }

    private function request(string $path, array $params = []): Request
    {
        $request = Request::create($path, 'GET', $params);
        $this->app->instance('request', $request);
        return $request;
    }
}
