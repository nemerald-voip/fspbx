<?php

namespace Tests\Unit;

use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\ProFeaturesController;
use App\Http\Controllers\SpeedDialController;
use App\Services\KeygenAPIService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RemainingAxiosPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.connections.remaining_pages_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.default', 'remaining_pages_test');
        DB::purge('remaining_pages_test');
        session([
            'domain_uuid' => 'account-a',
            'permissions' => collect(['domain_groups_list_view', 'contact_view', 'contact_edit'])
                ->map(fn ($name) => (object) ['permission_name' => $name])->all(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('remaining_pages_test');
        parent::tearDown();
    }

    public function test_shells_do_not_query_records_or_validate_licenses(): void
    {
        $keygen = Mockery::mock(KeygenAPIService::class);
        $keygen->shouldNotReceive('validateLicenseKey');
        $this->app->instance(KeygenAPIService::class, $keygen);
        DB::enableQueryLog();
        foreach ([new ProFeaturesController(), new DomainGroupsController(), new SpeedDialController()] as $controller) {
            $request = $this->request('/page');
            $request->headers->set('X-Inertia', 'true');
            $response = $controller->index($request)->toResponse($request)->getData(true);
            $this->assertArrayNotHasKey('data', $response['props']);
            $this->assertStringContainsString('/api/', $response['props']['routes']['data_route']);
            $this->assertArrayHasKey('per_page', $response['props']['pagination']);
            if ($controller instanceof SpeedDialController) {
                $this->assertTrue($response['props']['permissions']['update']);
                $this->assertFalse($response['props']['permissions']['create']);
            }
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_data_and_selection_require_the_page_permission(): void
    {
        session(['permissions' => []]);
        foreach ([new DomainGroupsController(), new SpeedDialController()] as $controller) {
            foreach (['getData', 'selectAll'] as $method) {
                try {
                    $controller->$method($this->request('/api/data'));
                    $this->fail('Expected a forbidden response.');
                } catch (HttpException $e) {
                    $this->assertSame(403, $e->getStatusCode());
                }
            }
        }
    }

    public function test_domain_groups_paginate_and_select_without_a_nonexistent_domain_column(): void
    {
        Schema::create('domain_groups', function (Blueprint $table) {
            $table->string('domain_group_uuid')->primary();
            $table->string('group_name');
        });
        for ($i = 1; $i <= 51; $i++) {
            DB::table('domain_groups')->insert(['domain_group_uuid' => 'group-'.$i, 'group_name' => sprintf('Group %02d', $i)]);
        }
        $controller = new DomainGroupsController();
        $data = $controller->getData($this->request('/api/domain-groups/data', ['per_page' => 50, 'page' => 2]))->toArray();
        $this->assertSame(51, $data['total']);
        $this->assertSame(2, $data['current_page']);
        $this->assertSame(['group-51'], array_column($data['data'], 'domain_group_uuid'));
        $selection = $controller->selectAll($this->request('/api/domain-groups/select-all'))->getData(true);
        $this->assertCount(51, $selection['items']);
        $data = $controller->getData($this->request('/api/domain-groups/data', ['sort' => '-group_name']))->toArray();
        $this->assertSame('group-51', $data['data'][0]['domain_group_uuid']);
        $data = $controller->getData($this->request('/api/domain-groups/data', ['sort' => '-missing_column']))->toArray();
        $this->assertSame('group-1', $data['data'][0]['domain_group_uuid']);
    }

    public function test_speed_dial_data_and_selection_remain_in_the_current_account(): void
    {
        Schema::create('v_contacts', function (Blueprint $table) {
            foreach (['contact_uuid', 'domain_uuid', 'contact_organization'] as $field) $table->string($field);
        });
        Schema::create('v_contact_phones', function (Blueprint $table) {
            foreach (['contact_phone_uuid', 'contact_uuid', 'phone_number', 'phone_speed_dial', 'insert_date'] as $field) $table->string($field)->nullable();
        });
        Schema::create('v_contact_users', function (Blueprint $table) {
            foreach (['contact_user_uuid', 'contact_uuid', 'user_uuid'] as $field) $table->string($field);
        });
        foreach ([['first', 'account-a', 'Alpha'], ['second', 'account-a', 'Beta'], ['hidden', 'account-b', 'Gamma']] as [$id, $account, $name]) {
            DB::table('v_contacts')->insert(['contact_uuid' => $id, 'domain_uuid' => $account, 'contact_organization' => $name]);
        }
        DB::table('v_contact_phones')->insert(['contact_phone_uuid' => 'phone', 'contact_uuid' => 'first', 'phone_number' => '2125550100', 'phone_speed_dial' => '007']);
        $controller = new SpeedDialController();
        $data = $controller->getData($this->request('/api/speed-dial/data'))->toArray();
        $this->assertSame(['first', 'second'], array_column($data['data'], 'contact_uuid'));
        $this->assertSame('007', $data['data'][0]['primary_phone']['phone_speed_dial']);
        $selection = $controller->selectAll($this->request('/api/speed-dial/select-all'))->getData(true);
        $this->assertSame(['first', 'second'], $selection['items']);
    }

    public function test_pro_features_data_validates_licenses_but_selection_does_not(): void
    {
        Schema::create('pro_features', function (Blueprint $table) {
            foreach (['uuid', 'name', 'slug', 'license', 'created_at'] as $field) $table->string($field)->nullable();
        });
        DB::table('pro_features')->insert([
            ['uuid' => 'licensed', 'name' => 'Pro', 'slug' => 'fspbx', 'license' => 'test-license', 'created_at' => '2026-01-01'],
            ['uuid' => 'unlicensed', 'name' => 'Other', 'slug' => 'other', 'license' => null, 'created_at' => '2026-02-01'],
        ]);
        $keygen = Mockery::mock(KeygenAPIService::class);
        $details = ['meta' => ['valid' => true, 'code' => 'VALID']];
        $keygen->shouldReceive('validateLicenseKey')->once()->with('test-license')->andReturn($details);
        $controller = new ProFeaturesController();
        $data = $controller->getData($this->request('/api/pro-features/data'), $keygen)->toArray();
        $this->assertSame(2, $data['total']);
        $this->assertSame('VALID', $data['data'][0]['license_valid']);
        $this->assertSame($details, $data['data'][0]['license_details']);
        $selection = $controller->selectAll($this->request('/api/pro-features/select-all'))->getData(true);
        $this->assertSame(['licensed', 'unlicensed'], $selection['items']);
    }

    public function test_search_and_selection_use_the_same_filters_including_speed_dial_phone_fields(): void
    {
        $grammar = new \Illuminate\Database\Query\Grammars\PostgresGrammar();
        $grammar->setConnection(DB::connection());
        DB::connection()->setQueryGrammar($grammar);
        foreach ([new ProFeaturesController(), new DomainGroupsController(), new SpeedDialController()] as $controller) {
            $queries = DB::pretend(fn () => $controller->selectAll($this->request('/api/select-all', ['filter' => ['search' => 'Alice']])));
            $this->assertContains('%Alice%', $queries[0]['bindings']);
            $this->assertStringContainsString('ilike', $queries[0]['query']);
            if ($controller instanceof SpeedDialController) {
                $this->assertContains('account-a', $queries[0]['bindings']);
                $this->assertStringContainsString('phone_number', $queries[0]['query']);
                $this->assertStringContainsString('phone_speed_dial', $queries[0]['query']);
            }
        }
    }

    public function test_all_data_routes_and_pro_feature_actions_use_authenticated_api_routes(): void
    {
        $routes = $this->app['router']->getRoutes();
        foreach (['pro-features.data', 'pro-features.select.all', 'pro-features.item.options', 'pro-features.update', 'pro-features.destroy', 'pro-features.install', 'pro-features.uninstall', 'domain-groups.data', 'speed-dial.data'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertStringStartsWith('api/', $route->uri());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('api.cookie.auth', $route->gatherMiddleware());
        }
    }

    private function request(string $url, array $data = []): Request
    {
        $request = Request::create($url, 'GET', $data);
        $this->app->instance('request', $request);
        return $request;
    }
}
