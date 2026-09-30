<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\ActiveExtensionReportController;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActiveExtensionReportTest extends TestCase
{
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const MISSING = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.active_extension_report_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'active_extension_report_test');
        config()->set('cache.default', 'array');
        config()->set('data.structure_caching.cache.store', 'array');
        DB::purge('active_extension_report_test');
        Log::spy()->shouldReceive('channel')->andReturnSelf();

        // The limiter may already have captured the deployment's Redis store at boot.
        $limiter = new RateLimiter(Cache::store('array'));
        $limiter->for('api', app(RateLimiter::class)->limiter('api'));
        $this->app->instance(RateLimiter::class, $limiter);

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name', 'domain_description'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'enabled'],
            'mobile_app_users' => ['mobile_app_user_uuid', 'extension_uuid', 'domain_uuid', 'status'],
            'v_user_groups' => ['user_uuid', 'group_uuid'],
            'v_group_permissions' => ['group_uuid', 'permission_name', 'permission_assigned'],
            'user_domain_permission' => ['user_uuid', 'domain_uuid'],
            'user_domain_group_permissions' => ['user_uuid', 'domain_group_uuid'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        Schema::create('extension_advanced_settings', function (Blueprint $table) {
            $table->string('setting_uuid');
            $table->string('extension_uuid');
            $table->boolean('suspended')->nullable();
        });

        DB::table('v_domains')->insert([
            ['domain_uuid' => self::LOCAL, 'domain_name' => 'local.example.com', 'domain_description' => 'Local account'],
            ['domain_uuid' => self::FOREIGN, 'domain_name' => 'foreign.example.com', 'domain_description' => null],
        ]);
        DB::table('v_user_groups')->insert(['user_uuid' => 'operator', 'group_uuid' => 'operators']);
        $this->grant('extension_view');

        // Exercise source routes without rebuilding the deployment's route cache.
        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
    }

    protected function tearDown(): void
    {
        DB::disconnect('active_extension_report_test');
        parent::tearDown();
    }

    public function test_route_requires_bearer_authentication_and_extension_view(): void
    {
        $route = app('router')->getRoutes()->match(Request::create($this->endpoint()));

        $this->assertSame(ActiveExtensionReportController::class.'@show', $route->getActionName());
        foreach (['auth:sanctum', 'api.token.auth', 'throttle:api', 'user.authorize:extension_view'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_counts_match_report_definitions_and_remain_in_the_requested_domain(): void
    {
        $this->authenticate();

        foreach (['active', 'suspended', 'missing-settings', 'null-setting', 'disabled', 'inactive-app', 'wrong-domain-app'] as $id) {
            $this->extension($id, self::LOCAL, $id === 'disabled' ? 'false' : 'true');
        }
        $this->extension('foreign', self::FOREIGN);
        DB::table('extension_advanced_settings')->insert([
            ['setting_uuid' => 'a', 'extension_uuid' => 'active', 'suspended' => false],
            ['setting_uuid' => 'b', 'extension_uuid' => 'suspended', 'suspended' => true],
            ['setting_uuid' => 'c', 'extension_uuid' => 'disabled', 'suspended' => false],
            ['setting_uuid' => 'd', 'extension_uuid' => 'null-setting', 'suspended' => null],
            ['setting_uuid' => 'e', 'extension_uuid' => 'foreign', 'suspended' => true],
        ]);
        DB::table('mobile_app_users')->insert([
            ['extension_uuid' => 'active', 'domain_uuid' => self::LOCAL, 'status' => 1],
            ['extension_uuid' => 'active', 'domain_uuid' => self::LOCAL, 'status' => 1],
            ['extension_uuid' => 'suspended', 'domain_uuid' => self::LOCAL, 'status' => 1],
            ['extension_uuid' => 'disabled', 'domain_uuid' => self::LOCAL, 'status' => 1],
            ['extension_uuid' => 'inactive-app', 'domain_uuid' => self::LOCAL, 'status' => -1],
            ['extension_uuid' => 'null-setting', 'domain_uuid' => self::LOCAL, 'status' => null],
            ['extension_uuid' => 'missing-settings', 'domain_uuid' => self::LOCAL, 'status' => 0],
            ['extension_uuid' => 'orphan', 'domain_uuid' => self::LOCAL, 'status' => 1],
            ['extension_uuid' => 'wrong-domain-app', 'domain_uuid' => self::FOREIGN, 'status' => 1],
            ['extension_uuid' => 'foreign', 'domain_uuid' => self::FOREIGN, 'status' => 1],
        ]);

        // Neither query parameters nor the current UI account can override the URL scope.
        session(['domain_uuid' => self::FOREIGN]);
        $this->getJson($this->endpoint().'?domain_uuid='.self::FOREIGN.'&filter[showGlobal]=true')
            ->assertOk()
            ->assertExactJson([
                'domain_uuid' => self::LOCAL,
                'object' => 'active_extension_report',
                'domain_name' => 'local.example.com',
                'domain_description' => 'Local account',
                'total_extensions' => 7,
                'suspended_extensions' => 1,
                'active_extensions' => 6,
                'active_mobile_apps' => 3,
            ]);
    }

    public function test_empty_domain_returns_integer_zero_counts_and_nullable_description(): void
    {
        $this->authenticate();
        $this->grant('domain_all');

        $this->getJson($this->endpoint(self::FOREIGN))->assertOk()->assertExactJson([
            'domain_uuid' => self::FOREIGN,
            'object' => 'active_extension_report',
            'domain_name' => 'foreign.example.com',
            'domain_description' => null,
            'total_extensions' => 0,
            'suspended_extensions' => 0,
            'active_extensions' => 0,
            'active_mobile_apps' => 0,
        ]);
    }

    public function test_all_suspended_domain_has_no_active_extensions(): void
    {
        $this->authenticate();
        $this->extension('suspended', self::LOCAL);
        DB::table('extension_advanced_settings')->insert([
            'setting_uuid' => 'setting', 'extension_uuid' => 'suspended', 'suspended' => true,
        ]);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('total_extensions', 1)
            ->assertJsonPath('suspended_extensions', 1)
            ->assertJsonPath('active_extensions', 0);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_authenticated_request_still_requires_bearer_header(): void
    {
        $this->authenticate();
        $this->withHeaders(['Authorization' => '']);

        $this->getJson($this->endpoint())->assertUnauthorized()
            ->assertJsonPath('message', 'Bearer token required.');
    }

    public function test_domain_all_does_not_replace_extension_view_permission(): void
    {
        $this->authenticate();
        DB::table('v_group_permissions')->delete();
        $this->grant('domain_all');

        $this->getJson($this->endpoint())->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden_permission')
            ->assertJsonPath('error.permission', 'extension_view');
    }

    public function test_unassigned_foreign_domain_is_rejected(): void
    {
        $this->authenticate();

        $this->getJson($this->endpoint(self::FOREIGN))->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden_domain');
    }

    public static function assignments(): array
    {
        return [['individual'], ['group']];
    }

    /** @dataProvider assignments */
    public function test_explicit_assignments_allow_only_assigned_domains(string $assignment): void
    {
        $this->authenticate();
        if ($assignment === 'individual') {
            DB::table('user_domain_permission')->insert(['user_uuid' => 'operator', 'domain_uuid' => self::FOREIGN]);
        } else {
            DB::table('user_domain_group_permissions')->insert(['user_uuid' => 'operator', 'domain_group_uuid' => 'assigned']);
            DB::table('domain_group_relations')->insert(['domain_group_uuid' => 'assigned', 'domain_uuid' => self::FOREIGN]);
        }
        $this->extension('foreign', self::FOREIGN);
        $this->extension('local', self::LOCAL);

        $this->getJson($this->endpoint(self::FOREIGN))->assertOk()
            ->assertJsonPath('domain_uuid', self::FOREIGN)
            ->assertJsonPath('total_extensions', 1);
        $this->getJson($this->endpoint())->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden_domain');
    }

    public function test_domain_all_still_returns_only_the_requested_domain(): void
    {
        $this->authenticate();
        $this->grant('domain_all');
        $this->extension('local', self::LOCAL);
        $this->extension('foreign-a', self::FOREIGN);
        $this->extension('foreign-b', self::FOREIGN);

        $this->getJson($this->endpoint(self::FOREIGN))->assertOk()
            ->assertJsonPath('domain_uuid', self::FOREIGN)
            ->assertJsonPath('total_extensions', 2);
    }

    public function test_invalid_uuid_returns_a_parameter_error(): void
    {
        $this->authenticate();
        $this->grant('domain_all');

        foreach (['not-a-uuid', str_repeat('-', 36)] as $uuid) {
            $this->getJson($this->endpoint($uuid))->assertStatus(400)
                ->assertJsonPath('error.code', 'invalid_request')
                ->assertJsonPath('error.param', 'domain_uuid');
        }
    }

    public function test_missing_domain_returns_404(): void
    {
        $this->authenticate();
        $this->grant('domain_all');

        $this->getJson($this->endpoint(self::MISSING))->assertNotFound()
            ->assertJsonPath('error.code', 'resource_missing')
            ->assertJsonPath('error.param', 'domain_uuid');
    }

    public function test_scribe_extracts_the_report_contract_without_generating_files(): void
    {
        config()->set('scribe.strategies.responses', [UseResponseTag::class]);
        $route = app('router')->getRoutes()->match(Request::create($this->endpoint()));
        $endpoint = (new Extractor())->processRoute($route);

        $this->assertSame('Reports', $endpoint->metadata->groupName);
        $this->assertTrue($endpoint->metadata->authenticated);
        $this->assertTrue($endpoint->urlParameters['domain_uuid']->required);
        $this->assertSame([200, 400, 401, 403, 403, 404], $endpoint->responses->pluck('status')->all());

        $success = json_decode($endpoint->responses->first()->content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('active_extension_report', $success['object']);
        foreach (['total_extensions', 'suspended_extensions', 'active_extensions', 'active_mobile_apps'] as $field) {
            $this->assertIsInt($success[$field]);
            $this->assertSame('integer', $endpoint->responseFields[$field]->type);
        }
    }

    private function authenticate(): void
    {
        Sanctum::actingAs((new User())->forceFill(['user_uuid' => 'operator', 'domain_uuid' => self::LOCAL]));
        $this->withToken('test-token');
    }

    private function grant(string $permission): void
    {
        DB::table('v_group_permissions')->insert([
            'group_uuid' => 'operators', 'permission_name' => $permission, 'permission_assigned' => 'true',
        ]);
    }

    private function extension(string $uuid, string $domain, string $enabled = 'true'): void
    {
        DB::table('v_extensions')->insert([
            'extension_uuid' => $uuid, 'domain_uuid' => $domain, 'extension' => '1001', 'enabled' => $enabled,
        ]);
    }

    private function endpoint(string $domain = self::LOCAL): string
    {
        return "/api/v1/domains/{$domain}/reports/active-extensions";
    }
}
