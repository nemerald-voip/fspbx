<?php

namespace Tests\Unit;

use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\UserController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AxiosPageDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('data.structure_caching.cache.store', 'array');
        config()->set('database.connections.axios_pages_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'axios_pages_test');
        DB::purge('axios_pages_test');
        session(['domain_uuid' => 'account-a', 'user.groups' => [], 'user.group_level' => 40]);
        $this->permissions('user_view', 'user_edit', 'user_delete', 'business_hours_list_view');
    }

    protected function tearDown(): void
    {
        DB::disconnect('axios_pages_test');
        parent::tearDown();
    }

    public function test_page_shells_do_not_query_records_and_expose_api_routes(): void
    {
        DB::enableQueryLog();
        foreach ([new UserController(), new BusinessHoursController()] as $controller) {
            $request = Request::create('/page');
            $request->headers->set('X-Inertia', 'true');
            $response = $controller->index($request)->toResponse($request)->getData(true);
            $this->assertArrayNotHasKey('data', $response['props']);
            $this->assertStringContainsString('/api/', $response['props']['routes']['data_route']);
            $this->assertArrayHasKey('permissions', $response['props']);
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_data_and_business_hours_selection_require_view_permission(): void
    {
        $this->permissions();
        foreach ([[new UserController(), 'getData'], [new BusinessHoursController(), 'getData'], [new BusinessHoursController(), 'selectAll']] as [$controller, $method]) {
            try {
                $controller->$method(Request::create('/api/data'));
                $this->fail('Expected a forbidden response before querying records.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_users_json_retains_directory_metadata_and_management_restrictions(): void
    {
        $this->createUsers();
        $controller = new UserController();
        $request = $this->request('/api/users/data');

        $data = $controller->getData($request)->getData(true);

        $this->assertSame(3, $data['total']);
        $this->assertTrue($data['has_directories']);
        $this->assertSame(1, $data['selectable_total']);
        $rows = collect($data['data'])->keyBy('user_uuid');
        foreach ($rows as $row) {
            $this->assertArrayHasKey('language', $row);
            $this->assertArrayNotHasKey('username', $row);
        }
        $this->assertSame('Directory A', $rows['ldap']['ldap_directory_name']);
        $this->assertFalse($rows['ldap']['can_delete_target']);
        $this->assertFalse($rows['admin']['can_manage_target']);
        $this->assertFalse($rows['admin']['can_delete_target']);
        $this->assertTrue($rows['local']['can_delete_target']);

        $request = $this->request('/api/users/data', ['filter' => ['source' => 'directory']]);
        $data = $controller->getData($request)->getData(true);
        $this->assertSame(['ldap'], array_column($data['data'], 'user_uuid'));
        $this->assertSame(0, $data['selectable_total']);
    }

    public function test_users_directory_metadata_and_selection_keep_priority_and_domain_boundaries(): void
    {
        $this->createUsers();
        DB::table('v_groups')->where('group_uuid', 'superadmin')->update(['group_name' => 'SuPeRaDmIn', 'group_level' => 20]);
        foreach ([['beta', 'Beta', 'account-a', 0], ['alpha', 'Alpha', 'account-a', 0], ['foreign-directory', 'Foreign', 'account-b', -1]] as [$uuid, $name, $domain, $priority]) {
            DB::table('ldap_directories')->insert(['directory_uuid' => $uuid, 'domain_uuid' => $domain, 'name' => $name, 'priority' => $priority]);
            DB::table('ldap_directory_users')->insert(['directory_uuid' => $uuid, 'domain_uuid' => $domain, 'user_uuid' => 'ldap']);
        }

        $controller = new UserController();
        $data = $controller->getData($this->request('/api/users/data'))->getData(true);
        $rows = collect($data['data'])->keyBy('user_uuid');
        $this->assertSame('Alpha', $rows['ldap']['ldap_directory_name']);
        $this->assertNull($rows['local']['ldap_directory_name']);
        $this->assertFalse($rows['admin']['can_manage_target']);
        $this->assertFalse($rows['admin']['can_delete_target']);
        $this->assertSame(1, $data['selectable_total']);
        $this->assertSame(['local'], $controller->selectAll($this->request('/api/users/select-all'))->getData(true)['items']);

        $filtered = $controller->getData($this->request('/api/users/data', ['filter' => ['search' => 'LDAP@TEST']]))->getData(true);
        $this->assertSame(['ldap'], array_column($filtered['data'], 'user_uuid'));
        $this->assertSame(0, $filtered['selectable_total']);
    }

    public function test_users_without_directories_keep_the_plain_list(): void
    {
        $this->createUsers();
        DB::table('ldap_directories')->delete();
        $this->permissions('user_view');

        $data = (new UserController())->getData($this->request('/api/users/data'))->getData(true);

        $this->assertFalse($data['has_directories']);
        $this->assertSame(0, $data['selectable_total']);
        $this->assertNull($data['data'][0]['ldap_directory_name']);
    }

    public function test_business_hours_json_is_tenant_scoped_sorted_and_paginated(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            foreach (['uuid', 'domain_uuid', 'name', 'extension', 'description'] as $column) {
                $table->string($column)->nullable();
            }
        });
        foreach (range(1, 55) as $i) {
            DB::table('business_hours')->insert([
                'uuid' => 'hours-'.$i, 'domain_uuid' => 'account-a', 'extension' => (string) (9200 + $i), 'name' => 'Hours '.$i,
            ]);
        }
        DB::table('business_hours')->insert(['uuid' => 'foreign', 'domain_uuid' => 'account-b', 'extension' => '9999']);
        $controller = new BusinessHoursController();

        $data = $controller->getData($this->request('/api/business-hours/data', ['page' => 2, 'sort' => '-extension']))->toArray();

        $this->assertSame(55, $data['total']);
        $this->assertSame(['hours-5', 'hours-4', 'hours-3', 'hours-2', 'hours-1'], array_column($data['data'], 'uuid'));
        $selection = $controller->selectAll($this->request('/api/business-hours/select-all', ['showGlobal' => true]))->getData(true);
        $this->assertCount(55, $selection['items']);
        $this->assertNotContains('foreign', $selection['items']);

        // PostgreSQL's ILIKE is unavailable in SQLite; inspect the real query and bindings.
        $grammar = new \Illuminate\Database\Query\Grammars\PostgresGrammar();
        $grammar->setConnection(DB::connection());
        DB::connection()->setQueryGrammar($grammar);
        $query = $controller->builder(['search' => 'Office'], '-name');
        $this->assertStringContainsString('"business_hours"."domain_uuid" = ?', $query->toSql());
        $this->assertStringContainsString('"name"::text ilike ?', $query->toSql());
        $this->assertSame(['account-a', '%Office%', '%Office%', '%Office%'], $query->getBindings());
        $queries = DB::pretend(fn () => $controller->selectAll(
            $this->request('/api/business-hours/select-all', ['filter' => ['search' => 'Office']])
        ));
        $this->assertSame(['account-a', '%Office%', '%Office%', '%Office%'], $queries[0]['bindings']);
    }

    public function test_new_data_and_sansay_action_routes_keep_api_authentication(): void
    {
        foreach (['users.data', 'business-hours.data', 'sansay.active-calls.data', 'sansay.active-calls.select.all', 'sansay.active-calls.delete'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertStringStartsWith('api/', $route->uri());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('api.cookie.auth', $route->gatherMiddleware());
        }
    }

    private function createUsers(): void
    {
        foreach ([
            'v_users' => ['user_uuid', 'domain_uuid', 'username', 'user_email', 'user_enabled', 'extension_uuid'],
            'users_adv_fields' => ['user_uuid', 'first_name', 'last_name'],
            'v_user_settings' => ['user_uuid', 'user_setting_category', 'user_setting_subcategory', 'user_setting_value'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid', 'group_name'],
            'v_groups' => ['group_uuid', 'group_name', 'group_level'],
            'ldap_directories' => ['directory_uuid', 'domain_uuid', 'name', 'priority'],
            'ldap_directory_users' => ['directory_uuid', 'domain_uuid', 'user_uuid'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) $table->string($column)->nullable();
            });
        }
        foreach (['local', 'ldap', 'admin', 'foreign'] as $id) {
            DB::table('v_users')->insert([
                'user_uuid' => $id, 'username' => $id, 'user_email' => $id.'@test.invalid',
                'user_enabled' => 'true', 'domain_uuid' => $id === 'foreign' ? 'account-b' : 'account-a',
            ]);
        }
        DB::table('v_groups')->insert(['group_uuid' => 'superadmin', 'group_name' => 'superadmin', 'group_level' => 80]);
        $this->actingAs((new \App\Models\User())->forceFill(['user_uuid' => 'actor', 'domain_uuid' => 'account-a']));
        DB::table('v_groups')->insert(['group_uuid' => 'actor-role', 'group_name' => 'admin', 'group_level' => 40]);
        DB::table('v_user_groups')->insert(['user_uuid' => 'actor', 'domain_uuid' => 'account-a', 'group_uuid' => 'actor-role']);
        DB::table('v_user_groups')->insert(['user_group_uuid' => 'membership', 'user_uuid' => 'admin', 'group_uuid' => 'superadmin', 'group_name' => 'superadmin']);
        DB::table('ldap_directories')->insert(['directory_uuid' => 'directory', 'domain_uuid' => 'account-a', 'name' => 'Directory A', 'priority' => 1]);
        DB::table('ldap_directory_users')->insert(['directory_uuid' => 'directory', 'domain_uuid' => 'account-a', 'user_uuid' => 'ldap']);
    }

    private function permissions(string ...$names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function request(string $path, array $params = []): Request
    {
        $request = Request::create($path, 'GET', $params);
        $this->app->instance('request', $request);

        return $request;
    }
}
