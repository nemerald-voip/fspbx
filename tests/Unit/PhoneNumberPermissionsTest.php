<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\PhoneNumberController;
use App\Http\Controllers\PhoneNumbersController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PhoneNumberPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.phone_number_permissions_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'phone_number_permissions_test');
        DB::purge('phone_number_permissions_test');
        session(['domain_uuid' => 'account-a']);

        Schema::create('v_destinations', function (Blueprint $table) {
            foreach ([
                'destination_uuid', 'domain_uuid', 'destination_number', 'destination_prefix',
                'destination_actions', 'destination_enabled', 'destination_description',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('v_domains', function (Blueprint $table) {
            $table->string('domain_uuid');
            $table->string('domain_name')->nullable();
            $table->string('domain_description')->nullable();
        });
        DB::table('v_destinations')->insert([
            ['destination_uuid' => 'local', 'domain_uuid' => 'account-a', 'destination_number' => '2135550100'],
            ['destination_uuid' => 'foreign', 'domain_uuid' => 'account-b', 'destination_number' => '2135550101'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('phone_number_permissions_test');
        parent::tearDown();
    }

    public static function readMethods(): array
    {
        return [['getData'], ['selectAll']];
    }

    /** @dataProvider readMethods */
    public function test_read_endpoints_require_destination_view_before_querying(string $method): void
    {
        $this->permissions('destination_all', 'ring_group_view', 'ring_group_domain');
        $this->request($method, ['showGlobal' => true]);
        DB::enableQueryLog();

        try {
            (new PhoneNumbersController())->$method();
            $this->fail('Expected a forbidden response.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame([], DB::getQueryLog());
        }
    }

    public static function visibilityCases(): iterable
    {
        $cases = [
            'default local' => [false, [], ['local']],
            'forged global boolean' => [false, ['showGlobal' => true], ['local']],
            'forged global string' => [false, ['showGlobal' => 'true'], ['local']],
            'forged global integer' => [false, ['showGlobal' => '1'], ['local']],
            'authorized global boolean' => [true, ['showGlobal' => true], ['local', 'foreign']],
            'authorized global string' => [true, ['showGlobal' => 'true'], ['local', 'foreign']],
            'authorized global integer' => [true, ['showGlobal' => '1'], ['local', 'foreign']],
        ];

        foreach ([false, true] as $canViewGlobal) {
            $cases['missing filter '.(int) $canViewGlobal] = [$canViewGlobal, [], ['local']];
            foreach ([false, 'false', '0', null, '', [], ['true'], 'invalid'] as $i => $value) {
                $cases['non-global filter '.(int) $canViewGlobal.' '.$i] = [$canViewGlobal, ['showGlobal' => $value], ['local']];
            }
        }

        foreach (['getData', 'selectAll'] as $method) {
            foreach ($cases as $name => $case) {
                yield $method.' '.$name => [$method, ...$case];
            }
        }
    }

    /** @dataProvider visibilityCases */
    public function test_read_endpoints_enforce_account_scope(
        string $method,
        bool $canViewGlobal,
        array $filters,
        array $expected
    ): void {
        $this->permissions(...($canViewGlobal ? ['destination_view', 'destination_all'] : ['destination_view']));
        $this->request($method, $filters);

        $result = (new PhoneNumbersController())->$method();
        if ($method === 'getData') {
            $this->assertSame(count($expected), $result->total());
            $items = $result->getCollection()->pluck('destination_uuid')->all();
        } else {
            $this->assertSame(200, $result->getStatusCode());
            $items = $result->getData(true)['items'];
        }

        $this->assertEqualsCanonicalizing($expected, $items);
    }

    public static function apiRoutes(): array
    {
        return [
            ['GET', '', 'index', 'destination_view'],
            ['GET', '/number', 'show', 'destination_view'],
            ['POST', '', 'store', 'destination_add'],
            ['PATCH', '/number', 'update', 'destination_edit'],
            ['DELETE', '/number', 'destroy', 'destination_delete'],
        ];
    }

    /** @dataProvider apiRoutes */
    public function test_api_routes_require_phone_number_permissions(
        string $method,
        string $suffix,
        string $action,
        string $permission
    ): void {
        // Verify source routes without changing the deployment's existing route cache.
        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));

        $request = Request::create('/api/v1/domains/account-a/phone-numbers'.$suffix, $method);
        $route = $router->getRoutes()->match($request);

        $this->assertSame(PhoneNumberController::class.'@'.$action, $route->getActionName());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('api.token.auth', $middleware);
        $this->assertSame(['user.authorize:'.$permission], array_values(array_filter(
            $middleware,
            fn ($name) => str_starts_with($name, 'user.authorize:')
        )));
    }

    private function permissions(string ...$names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function request(string $method, array $filters): void
    {
        $request = Request::create(
            '/api/phone-numbers/'.($method === 'getData' ? 'data' : 'select-all'),
            $method === 'getData' ? 'GET' : 'POST',
            $filters ? ['filter' => $filters] : []
        );
        $this->app->instance('request', $request);
    }
}
