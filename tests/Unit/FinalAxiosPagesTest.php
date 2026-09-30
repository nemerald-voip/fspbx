<?php

namespace Tests\Unit;

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\WhitelistedNumbersController;
use App\Jobs\AuditStaleRingotelUsers;
use App\Models\WhitelistedNumbers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinalAxiosPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.connections.final_pages_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.default', 'final_pages_test');
        DB::purge('final_pages_test');
        session([
            'domain_uuid' => 'account-a',
            'domains' => collect([['domain_uuid' => 'account-a'], ['domain_uuid' => 'account-b']]),
            'permissions' => [(object) ['permission_name' => 'device_all']],
        ]);
        foreach (['a', 'b', 'c'] as $id) Cache::put('account-'.$id.'_timeZone', 'UTC', 60);
    }

    protected function tearDown(): void
    {
        DB::disconnect('final_pages_test');
        parent::tearDown();
    }

    public function test_shells_supply_api_routes_without_loading_data(): void
    {
        DB::enableQueryLog();
        foreach ([new WhitelistedNumbersController(), new ActivityLogController(), new ReportsController()] as $controller) {
            $request = $this->request('/page');
            $request->headers->set('X-Inertia', 'true');
            $response = $controller->index($request)->toResponse($request)->getData(true);
            $this->assertArrayNotHasKey('data', $response['props']);
            $this->assertStringContainsString('/api/', $response['props']['routes']['data_route']);
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_whitelist_pagination_selection_and_delete_urls_stay_in_the_current_account(): void
    {
        $this->whitelist();
        $controller = new WhitelistedNumbersController();
        $data = $controller->getData($this->request('/api/whitelisted-numbers/data', ['page' => 2, 'per_page' => 50]))->toArray();
        $this->assertSame(51, $data['total']);
        $this->assertSame(['number-51'], array_column($data['data'], 'uuid'));
        $this->assertStringContainsString('/api/whitelisted-numbers/number-51', $data['data'][0]['destroy_route']);
        $selection = $controller->selectAll($this->request('/api/whitelisted-numbers/select-all'))->getData(true);
        $this->assertCount(51, $selection['items']);
        $this->assertNotContains('hidden', $selection['items']);
    }

    public function test_whitelist_single_deletion_returns_json_and_rejects_other_accounts(): void
    {
        $this->whitelist();
        $controller = new WhitelistedNumbersController();
        $response = $controller->destroy(WhitelistedNumbers::findOrFail('number-1'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('messages', $response->getData(true));
        $this->assertFalse(DB::table('whitelisted_numbers')->where('uuid', 'number-1')->exists());
        try {
            $controller->destroy(WhitelistedNumbers::findOrFail('hidden'));
            $this->fail('Expected a foreign account number to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertTrue(DB::table('whitelisted_numbers')->where('uuid', 'hidden')->exists());
    }

    public function test_activity_log_preserves_local_accessible_global_and_system_scope(): void
    {
        $this->activities();
        $controller = new ActivityLogController();
        $data = $controller->getData($this->request('/api/activities/data', ['filter' => ['showGlobal' => 'false']]))->toArray();
        $this->assertSame(['local'], array_column($data['data'], 'id'));
        $data = $controller->getData($this->request('/api/activities/data', ['filter' => ['showGlobal' => true]]))->toArray();
        $this->assertSame(['system', 'allowed', 'local'], array_column($data['data'], 'id'));
        $this->assertSame('Account B', $data['data'][1]['domain']['domain_description']);
        $data = $controller->getData($this->request('/api/activities/data', ['filter' => ['showGlobal' => true], 'sort' => 'invalid']))->toArray();
        $this->assertSame(['system', 'allowed', 'local'], array_column($data['data'], 'id'));
    }

    public function test_activity_global_scope_requires_permission(): void
    {
        session(['permissions' => []]);
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        try {
            (new ActivityLogController())->getData($this->request('/api/activities/data', ['filter' => ['showGlobal' => 'true']]));
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_search_targets_real_columns_and_cannot_bypass_account_scope(): void
    {
        $grammar = new \Illuminate\Database\Query\Grammars\PostgresGrammar();
        $grammar->setConnection(DB::connection());
        DB::connection()->setQueryGrammar($grammar);
        $whitelist = new WhitelistedNumbersController();
        $queries = DB::pretend(fn () => $whitelist->selectAll($this->request('/api/whitelisted-numbers/select-all', ['filter' => ['search' => 'Alice']])));
        $this->assertContains('account-a', $queries[0]['bindings']);
        $this->assertContains('%Alice%', $queries[0]['bindings']);
        $this->assertStringContainsString('"number"::text ilike', $queries[0]['query']);
        $queries = DB::pretend(fn () => (new ActivityLogController())->getData($this->request('/api/activities/data', ['filter' => ['search' => 'Alice', 'showGlobal' => true]])));
        $sql = $queries[0]['query'];
        $this->assertStringContainsString('or "domain_uuid" is null) and (', $sql);
        $this->assertStringContainsString('"log_name"::text ilike', $sql);
        $this->assertStringContainsString('CAST(properties AS TEXT) ILIKE', $sql);
        $this->assertStringContainsString('"username"::text ilike', $sql);
        $this->assertStringNotContainsString('"destination"', $sql);
        $this->assertContains('%Alice%', $queries[0]['bindings']);
        $this->assertContains('account-b', $queries[0]['bindings']);
    }

    public function test_report_labels_and_search_are_localized_while_ids_stay_stable(): void
    {
        $controller = new ReportsController();
        foreach (['es-419', 'fr', 'pt-br'] as $locale) {
            app()->setLocale($locale);
            $data = $controller->getData($this->request('/api/reports/data'))->getData(true);
            $this->assertSame(['active-extensions', 'stale-ringotel-users'], array_column($data, 'id'));
            $this->assertNotSame('Active and suspended extensions per domain', $data[0]['reportName']);
            $filtered = $controller->getData($this->request('/api/reports/data', ['filter' => ['search' => $data[0]['reportName']]]))->getData(true);
            $this->assertSame(['active-extensions'], array_column($filtered, 'id'));
        }
    }

    public function test_report_generation_dispatches_by_id_and_rejects_unknown_reports(): void
    {
        Queue::fake();
        app()->setLocale('fr');
        $controller = new ReportsController();
        $response = $controller->store($this->request('/api/reports/generate', ['reportId' => 'stale-ringotel-users']));
        $this->assertSame(200, $response->getStatusCode());
        Queue::assertPushed(AuditStaleRingotelUsers::class, 1);
        try {
            $controller->store($this->request('/api/reports/generate', ['reportId' => 'Unknown report']));
            $this->fail('Expected validation to reject an unknown report.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reportId', $e->errors());
        }
        Queue::assertPushed(AuditStaleRingotelUsers::class, 1);
    }

    public function test_final_pages_use_authenticated_api_routes_and_retired_routes_are_removed(): void
    {
        $routes = $this->app['router']->getRoutes();
        foreach (['activities.data', 'whitelisted-numbers.data', 'whitelisted-numbers.store', 'whitelisted-numbers.destroy', 'whitelisted-numbers.bulk.delete', 'whitelisted-numbers.select.all', 'reports.data', 'reports.generate'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertStringStartsWith('api/', $route->uri());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('api.cookie.auth', $route->gatherMiddleware());
        }
        foreach (['faxqueue.index', 'faxqueue.retry', 'faxqueue.select.all', 'activities.destroy', 'activities.bulk.delete', 'activities.select.all'] as $name) {
            $this->assertNull($routes->getByName($name), $name);
        }
        $this->assertFileDoesNotExist(resource_path('js/Pages/FaxQueue.vue'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/FaxQueueController.php'));
        $this->assertTrue(class_exists(\App\Models\FaxQueues::class));
        $this->assertNotNull($routes->getByName('faxes.index'));
    }

    private function whitelist(): void
    {
        Schema::create('whitelisted_numbers', function (Blueprint $table) {
            foreach (['uuid', 'domain_uuid', 'number', 'description'] as $field) $table->string($field)->nullable();
            $table->timestamps();
        });
        for ($i = 1; $i <= 51; $i++) {
            DB::table('whitelisted_numbers')->insert(['uuid' => 'number-'.$i, 'domain_uuid' => 'account-a', 'number' => sprintf('%03d', $i), 'created_at' => '2026-09-21 12:00:00']);
        }
        DB::table('whitelisted_numbers')->insert(['uuid' => 'hidden', 'domain_uuid' => 'account-b', 'number' => '999']);
    }

    private function activities(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            foreach (['id', 'domain_uuid', 'log_name', 'description', 'causer_type', 'causer_id', 'subject_type', 'subject_id', 'properties', 'created_at'] as $field) $table->string($field)->nullable();
        });
        Schema::create('v_domains', function (Blueprint $table) {
            foreach (['domain_uuid', 'domain_name', 'domain_description', 'domain_enabled'] as $field) $table->string($field)->nullable();
        });
        DB::table('v_domains')->insert(['domain_uuid' => 'account-b', 'domain_name' => 'b.test', 'domain_description' => 'Account B']);
        foreach ([['local', 'account-a', 10], ['allowed', 'account-b', 11], ['system', null, 12], ['hidden', 'account-c', 13]] as [$id, $domain, $hour]) {
            DB::table('activity_log')->insert(['id' => $id, 'domain_uuid' => $domain, 'log_name' => 'extension', 'description' => 'updated', 'created_at' => '2026-09-21 '.$hour.':00:00', 'properties' => '{}']);
        }
    }

    private function request(string $url, array $data = []): Request
    {
        $request = Request::create($url, 'GET', $data);
        $this->app->instance('request', $request);
        return $request;
    }
}
