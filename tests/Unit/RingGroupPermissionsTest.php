<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\RingGroupController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Models\RingGroups;
use App\Models\RingGroupsDestinations;
use App\Models\User;
use App\Observers\RingGroupObserver;
use App\Services\FreeswitchEslService;
use App\Services\OpenAIService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RingGroupPermissionsTest extends TestCase
{
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const MISSING = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.ring_group_permissions_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'ring_group_permissions_test');
        config()->set('cache.default', 'array');
        DB::purge('ring_group_permissions_test');
        session(['domain_uuid' => 'account-a', 'domain_name' => 'a.test', 'user.groups' => []]);
        $this->actingAs((new User())->forceFill(['user_uuid' => 'operator', 'domain_uuid' => 'account-a']));
        $this->permissions();

        // Keep model writes real while replacing dialplan/runtime side effects.
        $observer = Mockery::mock(RingGroupObserver::class);
        $observer->shouldReceive('created', 'updated', 'deleted')->andReturnNull();
        $this->app->instance(RingGroupObserver::class, $observer);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')->with('bgapi reloadxml')->andReturn('+OK');
        $this->app->instance(FreeswitchEslService::class, $esl);
        $speech = Mockery::mock(OpenAIService::class);
        $speech->shouldReceive('getVoices', 'getSpeeds')->andReturn([]);
        $speech->shouldReceive('getDefaultVoice')->andReturn('alloy');
        $this->app->instance(OpenAIService::class, $speech);

        $this->schema('v_ring_groups', ['ring_group_uuid', ...(new RingGroups())->getFillable()]);
        $this->schema('v_ring_group_destinations', (new RingGroupsDestinations())->getFillable());
        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_dialplans' => ['dialplan_uuid', 'domain_uuid', 'dialplan_number', 'dialplan_xml'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name'],
            'extension_advanced_settings' => ['extension_uuid', 'suspended'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id'],
            'v_call_center_queues' => ['call_center_queue_uuid', 'domain_uuid', 'queue_extension'],
            'v_fax' => ['fax_uuid', 'domain_uuid', 'fax_extension'],
            'v_ivr_menus' => ['ivr_menu_uuid', 'domain_uuid', 'ivr_menu_extension'],
            'v_call_flows' => ['call_flow_uuid', 'domain_uuid', 'call_flow_extension'],
            'v_conference_centers' => ['conference_center_uuid', 'domain_uuid', 'conference_center_extension'],
            'v_conferences' => ['conference_uuid', 'domain_uuid', 'conference_extension'],
            'business_hours' => ['uuid', 'domain_uuid', 'extension'],
            'ai_agents' => ['ai_agent_uuid', 'domain_uuid', 'extension'],
            'dynamic_routes' => ['dynamic_route_uuid', 'domain_uuid', 'extension'],
            'v_recordings' => ['recording_uuid', 'domain_uuid', 'recording_name'],
            'v_music_on_hold' => ['music_on_hold_uuid', 'domain_uuid', 'music_on_hold_name'],
            'v_vars' => ['var_uuid', 'var_category', 'var_enabled', 'var_name'],
            'v_streams' => ['stream_uuid', 'domain_uuid', 'stream_name', 'stream_location', 'stream_enabled'],
            'v_domain_settings' => ['domain_setting_uuid', 'domain_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_enabled', 'domain_setting_value'],
            'v_default_settings' => ['default_setting_uuid', 'default_setting_category', 'default_setting_subcategory', 'default_setting_enabled', 'default_setting_value'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid'],
        ] as $table => $columns) {
            $this->schema($table, $columns);
        }
        foreach ([[self::LOCAL, 'account-a', '9000'], [self::FOREIGN, 'account-b', '9001']] as [$uuid, $account, $extension]) {
            DB::table('v_ring_groups')->insert([
                'ring_group_uuid' => $uuid, 'domain_uuid' => $account, 'ring_group_name' => $account,
                'ring_group_extension' => $extension, 'ring_group_strategy' => 'enterprise',
                'ring_group_context' => $account.'.test', 'dialplan_uuid' => $uuid,
            ]);
            DB::table('v_ring_group_destinations')->insert([
                'ring_group_destination_uuid' => $uuid, 'domain_uuid' => $account,
                'ring_group_uuid' => $uuid, 'destination_number' => '100', 'destination_delay' => '0',
            ]);
            DB::table('v_dialplans')->insert([
                'dialplan_uuid' => $uuid, 'domain_uuid' => $account, 'dialplan_xml' => 'unchanged',
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('ring_group_permissions_test');
        Mockery::close();
        parent::tearDown();
    }

    public static function protectedReads(): array
    {
        return [
            ['getData', [], 'ring_group_view'],
            ['selectAll', [], 'ring_group_view'],
            ['getItemOptions', [], 'ring_group_add'],
            ['getItemOptions', ['item_uuid' => self::LOCAL], 'ring_group_edit'],
        ];
    }

    /** @dataProvider protectedReads */
    public function test_reads_deny_missing_action_permission_before_queries(string $method, array $params, string $permission): void
    {
        $this->permissions(...array_diff(['ring_group_view', 'ring_group_add', 'ring_group_edit', 'ring_group_all', 'ring_group_domain'], [$permission]));
        $this->request($params);
        DB::enableQueryLog();
        $this->assertDenied(fn () => (new RingGroupsController())->$method(), 403);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @dataProvider protectedReads */
    public function test_reads_require_account_context(string $method, array $params, string $permission): void
    {
        $this->permissions($permission);
        session()->forget('domain_uuid');
        $this->request($params);
        DB::enableQueryLog();
        $this->assertDenied(fn () => (new RingGroupsController())->$method(), 403);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_data_is_limited_to_the_current_account(): void
    {
        $this->permissions('ring_group_view', 'ring_group_all');
        $this->request(['showGlobal' => true, 'domain_uuid' => 'account-b']);
        $data = (new RingGroupsController())->getData();
        $this->assertSame(1, $data->total());
        $this->assertSame([self::LOCAL], $data->getCollection()->pluck('ring_group_uuid')->all());
    }

    public function test_page_and_existing_mutations_require_account_context(): void
    {
        $this->permissions('ring_group_view', 'ring_group_add', 'ring_group_delete');
        session()->forget('domain_uuid');
        $request = $this->request(['uuid' => self::LOCAL, 'items' => [self::LOCAL]]);
        DB::enableQueryLog();
        foreach (['index', 'duplicate', 'bulkDelete'] as $method) {
            $this->assertDenied(fn () => (new RingGroupsController())->$method($request), 403);
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public static function selectionFilters(): iterable
    {
        foreach ([false, true] as $globalPermission) {
            foreach ([[], ['showGlobal' => true], ['showGlobal' => false], ['showGlobal' => 'false'], ['showGlobal' => 'true'], ['showGlobal' => ['invalid']], ['filter' => ['showGlobal' => true]]] as $params) {
                yield [$globalPermission, $params];
            }
        }
    }

    /** @dataProvider selectionFilters */
    public function test_selection_never_leaves_current_account(bool $globalPermission, array $params): void
    {
        $this->permissions(...($globalPermission ? ['ring_group_view', 'ring_group_all'] : ['ring_group_view']));
        $this->request($params);
        $response = (new RingGroupsController())->selectAll();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([self::LOCAL], $response->getData(true)['items']);
    }

    public function test_editor_rejects_account_overrides_before_queries(): void
    {
        $this->permissions('ring_group_add', 'ring_group_edit', 'ring_group_all');
        DB::enableQueryLog();
        foreach ([[], ['item_uuid' => self::LOCAL]] as $params) {
            $this->request($params + ['domain_uuid' => 'account-b']);
            $this->assertDenied(fn () => (new RingGroupsController())->getItemOptions(), 403);
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_editor_returns_not_found_for_foreign_and_missing_records(): void
    {
        $this->permissions('ring_group_edit', 'ring_group_all');
        foreach ([self::FOREIGN, self::MISSING] as $uuid) {
            $this->request(['item_uuid' => $uuid]);
            $this->assertDenied(fn () => (new RingGroupsController())->getItemOptions(), 404);
        }
    }

    public function test_authorized_create_and_edit_options_still_load(): void
    {
        $this->permissions('ring_group_add');
        $this->request();
        $options = (new RingGroupsController())->getItemOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('store_route', $options['routes']);
        $this->assertSame('9001', $options['ring_group']->ring_group_extension);
        $this->assertSame([self::LOCAL], array_column($options['member_options'][1]['groupOptions'], 'value'));

        $this->permissions('ring_group_edit');
        $this->request(['item_uuid' => self::LOCAL, 'domain_uuid' => 'account-a']);
        $options = (new RingGroupsController())->getItemOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('update_route', $options['routes']);
        $this->assertSame(self::LOCAL, $options['ring_group']->getKey());
        $this->assertSame([], $options['member_options'][1]['groupOptions']);
    }

    public function test_create_requires_add_and_account_before_validation(): void
    {
        $this->permissions('ring_group_edit');
        $request = $this->formRequest(StoreRingGroupRequest::class, []);
        DB::enableQueryLog();
        $this->assertDenied(fn () => $request->validateResolved(), 403);
        $this->permissions('ring_group_add');
        session()->forget('domain_uuid');
        $this->assertDenied(fn () => $request->validateResolved(), 403);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_authorized_creation_saves_only_in_current_account(): void
    {
        $this->permissions('ring_group_add');
        $request = $this->formRequest(StoreRingGroupRequest::class, [
            'ring_group_name' => 'Created', 'ring_group_extension' => '9002', 'domain_uuid' => 'account-b',
        ]);
        $request->validateResolved();
        $response = (new RingGroupsController())->store($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('account-a', RingGroups::findOrFail($response->getData(true)['ring_group_uuid'])->domain_uuid);
    }

    public function test_update_checks_permission_and_ownership_before_validation(): void
    {
        $before = $this->snapshot();
        $request = $this->formRequest(UpdateRingGroupRequest::class, ['ring_group_name' => []], self::LOCAL);
        DB::enableQueryLog();
        $this->permissions('ring_group_view');
        $this->assertDenied(fn () => $request->validateResolved(), 403);
        $this->permissions('ring_group_edit');
        session()->forget('domain_uuid');
        $this->assertDenied(fn () => $request->validateResolved(), 403);
        $this->assertSame([], DB::getQueryLog());

        session(['domain_uuid' => 'account-a']);
        $request = $this->formRequest(UpdateRingGroupRequest::class, ['ring_group_name' => []], self::FOREIGN);
        DB::flushQueryLog();
        $this->assertDenied(fn () => $request->validateResolved(), 404);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($before, $this->snapshot());
        $this->assertDenied(fn () => $this->formRequest(UpdateRingGroupRequest::class, [], self::MISSING), 404);
    }

    public function test_update_uses_route_identity_without_requiring_body_uuid(): void
    {
        $this->permissions('ring_group_edit');
        $request = $this->formRequest(UpdateRingGroupRequest::class, [
            'ring_group_name' => 'Updated', 'ring_group_extension' => '9000', 'domain_uuid' => 'account-b',
        ], self::LOCAL);
        $request->validateResolved();
        $response = (new RingGroupsController())->update($request, $request->route('ring_group'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Updated', RingGroups::findOrFail(self::LOCAL)->ring_group_name);
        $this->assertSame('account-a', RingGroups::findOrFail(self::LOCAL)->domain_uuid);
        $this->assertSame('account-b', RingGroups::findOrFail(self::FOREIGN)->ring_group_name);
    }

    public function test_mismatched_body_uuid_cannot_override_update_identity(): void
    {
        $this->permissions('ring_group_edit');
        $request = $this->formRequest(UpdateRingGroupRequest::class, ['ring_group_uuid' => self::FOREIGN], self::LOCAL);
        $before = $this->snapshot();
        try {
            $request->validateResolved();
            $this->fail('Expected mismatched record identity to fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ring_group_uuid', $e->errors());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_duplication_and_bulk_deletion_retain_permissions_and_account_scope(): void
    {
        $controller = new RingGroupsController();
        $this->assertSame(403, $controller->duplicate($this->request(['uuid' => self::LOCAL]))->getStatusCode());
        $this->assertSame(403, $controller->bulkDelete($this->request(['items' => [self::LOCAL]]))->getStatusCode());
        $this->permissions('ring_group_add');
        $response = $controller->duplicate($this->request(['uuid' => self::LOCAL]));
        $this->assertSame(201, $response->getStatusCode());
        $copy = RingGroups::findOrFail($response->getData(true)['ring_group_uuid']);
        $this->assertSame('account-a', $copy->domain_uuid);
        $this->assertSame(1, $copy->destinations()->count());

        $this->permissions('ring_group_delete');
        $response = $controller->bulkDelete($this->request(['items' => [self::LOCAL, self::FOREIGN]]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(RingGroups::find(self::LOCAL));
        $this->assertNotNull(RingGroups::find(self::FOREIGN));
        $this->assertTrue(DB::table('v_ring_group_destinations')->where('ring_group_uuid', self::FOREIGN)->exists());
        $this->assertTrue(DB::table('v_dialplans')->where('dialplan_uuid', self::FOREIGN)->exists());
    }

    public static function apiRoutes(): array
    {
        return [
            ['GET', '', 'index', 'ring_group_view'],
            ['GET', '/group', 'show', 'ring_group_view'],
            ['POST', '', 'store', 'ring_group_add'],
            ['PATCH', '/group', 'update', 'ring_group_edit'],
            ['DELETE', '/group', 'destroy', 'ring_group_delete'],
        ];
    }

    /** @dataProvider apiRoutes */
    public function test_api_routes_keep_expected_permissions(string $method, string $suffix, string $action, string $permission): void
    {
        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
        $route = $router->getRoutes()->match(Request::create('/api/v1/domains/account-a/ring-groups'.$suffix, $method));
        $this->assertSame(RingGroupController::class.'@'.$action, $route->getActionName());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('api.token.auth', $middleware);
        $this->assertSame(['user.authorize:'.$permission], array_values(array_filter(
            $middleware, fn ($name) => str_starts_with($name, 'user.authorize:')
        )));
    }

    private function schema(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            foreach (array_unique($columns) as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    private function permissions(string ...$names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function request(array $params = []): Request
    {
        $request = Request::create('/api/ring-groups/data', 'POST', $params);
        $this->app->instance('request', $request);
        return $request;
    }

    private function formRequest(string $class, array $payload, ?string $uuid = null): FormRequest
    {
        $request = $class::create('/api/ring-groups'.($uuid ? '/'.$uuid : ''), $uuid ? 'PUT' : 'POST', $payload);
        $request->setContainer($this->app)->setRedirector(app('redirect'));
        $route = clone app('router')->getRoutes()->getByName($uuid ? 'ring-groups.update' : 'ring-groups.store');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        app('router')->substituteImplicitBindings($route);
        return $request;
    }

    private function assertDenied(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Expected HTTP '.$status.'.');
        } catch (AuthorizationException $e) {
            $this->assertSame(403, $status);
        } catch (ModelNotFoundException $e) {
            $this->assertSame(404, $status);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function snapshot(): array
    {
        return array_map(fn ($table) => DB::table($table)->get()->toJson(), [
            'v_ring_groups', 'v_ring_group_destinations', 'v_dialplans',
        ]);
    }
}
