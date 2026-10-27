<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\MessageSettingsController;
use App\Http\Controllers\MessageSettingsController as WebMessageSettingsController;
use App\Models\MessageSetting;
use App\Models\User;
use App\Services\Messaging\MessageSettingsService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageSettingsCrudTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const ACTOR = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const ROLE = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const EXTENSION = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const SECOND = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const FOREIGN_EXTENSION = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.message_settings_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.default', 'message_settings_test');
        config()->set('cache.default', 'array');
        config()->set('data.structure_caching.cache.store', 'array');
        DB::purge('message_settings_test');
        Log::spy()->shouldReceive('channel')->andReturnSelf();
        $limiter = new RateLimiter(Cache::store('array'));
        $limiter->for('api', app(RateLimiter::class)->limiter('api'));
        $this->app->instance(RateLimiter::class, $limiter);

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_domain_settings' => ['domain_uuid', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled'],
            'v_default_settings' => ['default_setting_subcategory', 'default_setting_value', 'default_setting_enabled'],
            'v_users' => ['user_uuid', 'domain_uuid'],
            'v_user_groups' => ['user_uuid', 'group_uuid'],
            'v_group_permissions' => ['domain_uuid', 'group_uuid', 'permission_name', 'permission_assigned'],
            'user_domain_permission' => ['user_uuid', 'domain_uuid'],
            'user_domain_group_permissions' => ['user_uuid', 'domain_group_uuid'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name'],
            'v_sms_destinations' => ['sms_destination_uuid', 'domain_uuid', 'destination', 'carrier', 'chatplan_detail_data', 'email', 'description', 'enabled'],
            'sms_destination_members' => ['sms_destination_member_uuid', 'domain_uuid', 'sms_destination_uuid', 'extension_uuid'],
            'messages' => ['message_uuid', 'domain_uuid', 'destination', 'message'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns, $name) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                if ($name === 'v_sms_destinations') {
                    $table->primary('sms_destination_uuid');
                }
                if ($name === 'sms_destination_members') {
                    $table->primary('sms_destination_member_uuid');
                    $table->unique(['sms_destination_uuid', 'extension_uuid']);
                }
            });
        }
        DB::table('v_domains')->insert([
            ['domain_uuid' => self::DOMAIN, 'domain_name' => 'local.example.com'],
            ['domain_uuid' => self::FOREIGN, 'domain_name' => 'foreign.example.com'],
        ]);
        DB::table('v_user_groups')->insert(['user_uuid' => self::ACTOR, 'group_uuid' => self::ROLE]);
        DB::table('v_extensions')->insert([
            ['extension_uuid' => self::EXTENSION, 'domain_uuid' => self::DOMAIN, 'extension' => '101'],
            ['extension_uuid' => self::SECOND, 'domain_uuid' => self::DOMAIN, 'extension' => '102'],
            ['extension_uuid' => self::FOREIGN_EXTENSION, 'domain_uuid' => self::FOREIGN, 'extension' => '101'],
        ]);
        $this->grant('message_settings_list_view', 'message_settings_manage');
        Sanctum::actingAs((new User())->forceFill(['user_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN]));
        $this->withToken('test-token');

        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
    }

    protected function tearDown(): void
    {
        DB::disconnect('message_settings_test');
        parent::tearDown();
    }

    public static function routes(): array
    {
        return [
            ['GET', '', 'index', 'message_settings_list_view'],
            ['GET', '/'.self::ACTOR, 'show', 'message_settings_list_view'],
            ['POST', '', 'store', 'message_settings_manage'],
            ['PATCH', '/'.self::ACTOR, 'update', 'message_settings_manage'],
            ['DELETE', '/'.self::ACTOR, 'destroy', 'message_settings_manage'],
        ];
    }

    /** @dataProvider routes */
    public function test_routes_require_token_and_action_permission(string $method, string $suffix, string $action, string $permission): void
    {
        $route = app('router')->getRoutes()->match(Request::create($this->endpoint().$suffix, $method));
        $this->assertSame(MessageSettingsController::class.'@'.$action, $route->getActionName());
        foreach (['auth:sanctum', 'api.token.auth', 'user.authorize:'.$permission] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->withHeader('Authorization', '')->json($method, $this->endpoint().$suffix)->assertUnauthorized();
        $this->withToken('test-token');
        DB::table('v_group_permissions')->where('permission_name', $permission)->delete();
        $this->json($method, $this->endpoint().$suffix)->assertForbidden()->assertJsonPath('error.permission', $permission);
    }

    public function test_create_stores_every_member_and_uses_only_the_account_in_the_url(): void
    {
        session(['domain_uuid' => self::FOREIGN]);
        $response = $this->postJson($this->endpoint(), [
            'destination' => '+12125550123', 'carrier' => 'voipms',
            'allowed_extension_uuids' => [self::EXTENSION, self::SECOND],
            'email' => 'messages@example.com', 'description' => 'Support',
            'domain_uuid' => self::FOREIGN, 'chatplan_detail_data' => '999', 'sms_destination_uuid' => self::ACTOR,
        ])->assertCreated()->assertJsonPath('domain_uuid', self::DOMAIN)
            ->assertJsonPath('allowed_extension_uuids', [self::EXTENSION, self::SECOND])->assertJsonPath('enabled', true);
        $uuid = $response->json('sms_destination_uuid');
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertNotSame(self::ACTOR, $uuid);
        $response->assertHeader('Location', $this->endpoint($uuid));
        $this->assertArrayNotHasKey('chatplan_detail_data', $response->json());
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'chatplan_detail_data' => '101', 'enabled' => 'true']);
        foreach ([self::EXTENSION, self::SECOND] as $extension) {
            $this->assertDatabaseHas('sms_destination_members', ['sms_destination_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'extension_uuid' => $extension]);
        }
    }

    public function test_create_defaults_and_single_member_assignments(): void
    {
        $this->postJson($this->endpoint(), ['destination' => '+442079460123'])
            ->assertCreated()->assertJsonPath('allowed_extension_uuids', [])->assertJsonPath('enabled', true)->assertJsonPath('carrier', null);
        $response = $this->postJson($this->endpoint(), [
            'destination' => '+12125550124', 'allowed_extension_uuids' => [self::SECOND], 'enabled' => false,
        ])->assertCreated()->assertJsonPath('enabled', false)->assertJsonPath('allowed_extension_uuids', [self::SECOND]);
        $this->assertDatabaseHas('sms_destination_members', ['sms_destination_uuid' => $response->json('sms_destination_uuid'), 'extension_uuid' => self::SECOND]);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $response->json('sms_destination_uuid'), 'enabled' => 'false']);
    }

    public static function phoneNumbers(): array
    {
        return [
            ['6505556500', '+16505556500'],
            ['(650) 555-6500', '+16505556500'],
            ['16505556500', '+16505556500'],
            ['+16505556500', '+16505556500'],
            ['+44 20 7946 0123', '+442079460123'],
            ['4165550123', '+14165550123'],
        ];
    }

    /** @dataProvider phoneNumbers */
    public function test_api_create_and_update_normalize_phone_numbers(string $input, string $expected): void
    {
        $uuid = $this->postJson($this->endpoint(), [
            'destination' => $input, 'carrier' => 'bandwidth', 'description' => 'API test',
        ])->assertCreated()->assertJsonPath('destination', $expected)->json('sms_destination_uuid');
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'destination' => $expected]);

        $other = $this->seedSetting(['destination' => '+12125550124']);
        $this->patchJson($this->endpoint($other), ['destination' => $input])
            ->assertOk()->assertJsonPath('destination', $expected);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $other, 'destination' => $expected]);
    }

    public function test_shared_service_normalizes_numbers_without_request_preparation(): void
    {
        $service = app(MessageSettingsService::class);
        $setting = $service->create(self::DOMAIN, ['destination' => '6505556500']);
        $this->assertSame('+16505556500', $setting->destination);
        $setting = $service->update($setting, ['destination' => '(212) 555-0123']);
        $this->assertSame('+12125550123', $setting->destination);
    }

    public static function configuredCountries(): array
    {
        return [
            ['gb', '020 7946 0123', '+442079460123'],
            ['DE', '030 123456', '+4930123456'],
            ['FR', '01 42 68 53 00', '+33142685300'],
            ['IT', '02 36618 300', '+390236618300'],
            ['AU', '02 9374 4000', '+61293744000'],
            ['GB', '+1 (650) 555-6500', '+16505556500'],
        ];
    }

    /** @dataProvider configuredCountries */
    public function test_api_create_and_update_use_default_country_independently_of_session(string $country, string $input, string $expected): void
    {
        $this->seedCountry($country);
        $this->seedCountry('US', self::FOREIGN);
        session(['domain_uuid' => self::FOREIGN]);

        $uuid = $this->postJson($this->endpoint(), ['destination' => $input, 'carrier' => 'bandwidth'])
            ->assertCreated()->assertJsonPath('destination', $expected)->json('sms_destination_uuid');
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'destination' => $expected]);

        $other = $this->seedSetting(['destination' => '+12125550124']);
        $this->patchJson($this->endpoint($other), ['destination' => $input])
            ->assertOk()->assertJsonPath('destination', $expected);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $other, 'destination' => $expected]);
    }

    public function test_api_uses_target_account_override_before_default_and_session_countries(): void
    {
        $this->seedCountry('US');
        $this->seedCountry('GB', self::DOMAIN);
        $this->seedCountry('DE', self::FOREIGN);
        session(['domain_uuid' => self::FOREIGN]);

        $uuid = $this->postJson($this->endpoint(), ['destination' => '020 7946 0123'])
            ->assertCreated()->assertJsonPath('destination', '+442079460123')->json('sms_destination_uuid');
        $this->patchJson($this->endpoint($uuid), ['destination' => '020 7946 0958'])
            ->assertOk()->assertJsonPath('destination', '+442079460958');
    }

    public function test_disabled_account_country_falls_back_to_default_country(): void
    {
        $this->seedCountry('GB');
        $this->seedCountry('US', self::DOMAIN, enabled: false);
        $this->postJson($this->endpoint(), ['destination' => '020 7946 0123'])
            ->assertCreated()->assertJsonPath('destination', '+442079460123');
    }

    public function test_disabled_default_country_uses_us_fallback(): void
    {
        $this->seedCountry('GB', enabled: false);
        $this->postJson($this->endpoint(), ['destination' => '6505556500'])
            ->assertCreated()->assertJsonPath('destination', '+16505556500');
    }

    public function test_web_create_uses_default_country_and_update_uses_records_account_override(): void
    {
        $this->seedCountry('GB');
        $this->seedCountry('IT', self::FOREIGN);
        session([
            'domain_uuid' => self::DOMAIN,
            'domains' => [['domain_uuid' => self::FOREIGN]],
            'user.groups' => [['group_uuid' => self::ROLE]],
        ]);
        Route::post('/test-message-settings', [WebMessageSettingsController::class, 'store']);
        Route::put('/test-message-settings/{setting}', [WebMessageSettingsController::class, 'update'])->middleware('api');

        $this->postJson('/test-message-settings', ['destination' => '020 7946 0123'])->assertCreated();
        $this->assertDatabaseHas('v_sms_destinations', ['domain_uuid' => self::DOMAIN, 'destination' => '+442079460123']);

        $uuid = $this->seedSetting(['domain_uuid' => self::FOREIGN]);
        $this->putJson('/test-message-settings/'.$uuid, ['destination' => '02 36618 300'])->assertOk();
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'domain_uuid' => self::FOREIGN, 'destination' => '+390236618300']);
    }

    public function test_partial_update_preserves_omitted_values_and_replaces_or_clears_access(): void
    {
        $uuid = $this->postJson($this->endpoint(), [
            'destination' => '+12125550123', 'carrier' => 'voipms', 'email' => 'messages@example.com',
            'allowed_extension_uuids' => [self::EXTENSION, self::SECOND],
        ])->assertCreated()->json('sms_destination_uuid');
        $this->patchJson($this->endpoint($uuid), ['description' => 'Updated', 'domain_uuid' => self::FOREIGN])
            ->assertOk()->assertJsonPath('destination', '+12125550123')->assertJsonPath('carrier', 'voipms')
            ->assertJsonPath('email', 'messages@example.com')->assertJsonPath('allowed_extension_uuids', [self::EXTENSION, self::SECOND])
            ->assertJsonPath('domain_uuid', self::DOMAIN);
        $this->patchJson($this->endpoint($uuid), ['allowed_extension_uuids' => [self::SECOND], 'enabled' => 'false'])
            ->assertOk()->assertJsonPath('allowed_extension_uuids', [self::SECOND])->assertJsonPath('enabled', false);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'chatplan_detail_data' => '102', 'enabled' => 'false']);
        $this->assertDatabaseMissing('sms_destination_members', ['sms_destination_uuid' => $uuid, 'extension_uuid' => self::EXTENSION]);
        $this->patchJson($this->endpoint($uuid), ['allowed_extension_uuids' => [], 'carrier' => null, 'email' => null, 'description' => null])
            ->assertOk()->assertJsonPath('allowed_extension_uuids', [])->assertJsonPath('carrier', null)->assertJsonPath('email', null)->assertJsonPath('description', null);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'chatplan_detail_data' => null]);
        $this->assertDatabaseMissing('sms_destination_members', ['sms_destination_uuid' => $uuid]);
    }

    public function test_update_can_add_one_extension_to_a_number_without_existing_assignments(): void
    {
        $uuid = $this->seedSetting();
        $this->patchJson($this->endpoint($uuid), ['allowed_extension_uuids' => [self::EXTENSION]])
            ->assertOk()->assertJsonPath('allowed_extension_uuids', [self::EXTENSION]);
        $this->assertDatabaseHas('sms_destination_members', [
            'sms_destination_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'extension_uuid' => self::EXTENSION,
        ]);
        $this->getJson($this->endpoint($uuid))->assertOk()->assertJsonPath('allowed_extension_uuids', [self::EXTENSION]);
    }

    public function test_malformed_json_is_rejected_instead_of_returning_a_successful_empty_update(): void
    {
        $uuid = $this->seedSetting();
        $body = '{"allowed_extension_uuids":["'.self::EXTENSION.'"],}';
        foreach (['POST' => $this->endpoint(), 'PATCH' => $this->endpoint($uuid)] as $method => $url) {
            $this->call($method, $url, [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
            ], $body)->assertStatus(400)->assertJsonPath('error.code', 'invalid_json');
        }
        $this->assertDatabaseCount('v_sms_destinations', 1);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'chatplan_detail_data' => null]);
        $this->assertDatabaseMissing('sms_destination_members', ['sms_destination_uuid' => $uuid]);
    }

    public function test_legacy_assignments_remain_visible_and_are_converted_when_submitted(): void
    {
        $uuid = $this->seedSetting(['chatplan_detail_data' => '101', 'enabled' => 'false']);
        $this->getJson($this->endpoint($uuid))->assertOk()->assertJsonPath('allowed_extension_uuids', [self::EXTENSION]);
        $this->patchJson($this->endpoint($uuid), ['description' => 'Old number'])
            ->assertOk()->assertJsonPath('allowed_extension_uuids', [self::EXTENSION]);
        $this->assertDatabaseCount('sms_destination_members', 0);
        $this->patchJson($this->endpoint($uuid), ['allowed_extension_uuids' => [self::EXTENSION, self::SECOND]])
            ->assertOk()->assertJsonPath('allowed_extension_uuids', [self::EXTENSION, self::SECOND]);
        $this->assertDatabaseCount('sms_destination_members', 2);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'chatplan_detail_data' => '101']);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['destination' => 'not-a-phone-number'], 'destination'],
            [['destination' => ['bad']], 'destination'],
            [['destination' => null], 'destination'],
            [['carrier' => 'unsupported'], 'carrier'],
            [['allowed_extension_uuids' => null], 'allowed_extension_uuids'],
            [['allowed_extension_uuids' => [self::EXTENSION, self::EXTENSION]], 'allowed_extension_uuids.0'],
            [['allowed_extension_uuids' => [self::FOREIGN_EXTENSION]], 'allowed_extension_uuids.0'],
            [['allowed_extension_uuids' => [self::ACTOR]], 'allowed_extension_uuids.0'],
            [['allowed_extension_uuids' => [null]], 'allowed_extension_uuids.0'],
            [['email' => 'bad-email'], 'email'],
            [['enabled' => null], 'enabled'],
            [['enabled' => 'invalid'], 'enabled'],
        ];
    }

    /** @dataProvider invalidPayloads */
    public function test_invalid_create_and_update_leave_data_unchanged(array $data, string $field): void
    {
        $uuid = $this->seedSetting(['chatplan_detail_data' => '101']);
        $this->postJson($this->endpoint(), array_replace(['destination' => '+12125550124'], $data))
            ->assertStatus(400)->assertJsonPath('error.code', 'invalid_parameter')->assertJsonPath('error.param', $field);
        $this->patchJson($this->endpoint($uuid), array_merge(['description' => 'Must not save'], $data))
            ->assertStatus(400)->assertJsonPath('error.param', $field);
        $this->assertDatabaseCount('v_sms_destinations', 1);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'description' => null, 'chatplan_detail_data' => '101']);
        $this->assertDatabaseCount('sms_destination_members', 0);
    }

    public function test_list_pagination_search_filters_and_account_isolation(): void
    {
        $uuids = [];
        for ($i = 0; $i < 3; $i++) {
            $uuids[] = $this->seedSetting(['description' => 'Support '.$i, 'carrier' => 'voipms']);
        }
        sort($uuids);
        $this->seedSetting(['enabled' => 'false', 'carrier' => 'twilio', 'description' => 'Disabled']);
        $this->seedSetting(['domain_uuid' => self::FOREIGN, 'description' => 'Support foreign', 'carrier' => 'voipms']);
        $first = $this->getJson($this->endpoint().'?limit=2&enabled=true&carrier=voipms&search=SUPPORT')
            ->assertOk()->assertJsonPath('object', 'list')->assertJsonPath('url', $this->endpoint())->assertJsonPath('has_more', true)->assertJsonCount(2, 'data');
        $this->assertSame(array_slice($uuids, 0, 2), array_column($first->json('data'), 'sms_destination_uuid'));
        $this->getJson($this->endpoint().'?limit=2&enabled=true&carrier=voipms&search=SUPPORT&starting_after='.$uuids[1])
            ->assertOk()->assertJsonPath('has_more', false)->assertJsonCount(1, 'data')->assertJsonPath('data.0.sms_destination_uuid', $uuids[2]);
        $this->getJson($this->endpoint().'?enabled=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'Disabled');
    }

    public function test_foreign_records_and_accounts_cannot_be_read_changed_or_deleted(): void
    {
        $uuid = $this->seedSetting(['domain_uuid' => self::FOREIGN]);
        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, $this->endpoint($uuid), ['description' => 'Changed'])->assertNotFound()->assertJsonPath('error.param', 'sms_destination_uuid');
            $this->json($method, str_replace(self::DOMAIN, self::FOREIGN, $this->endpoint($uuid)), ['description' => 'Changed'])
                ->assertForbidden()->assertJsonPath('error.code', 'forbidden_domain');
        }
        $this->postJson(str_replace(self::DOMAIN, self::FOREIGN, $this->endpoint()), ['destination' => '+12125550124'])->assertForbidden();
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'domain_uuid' => self::FOREIGN, 'description' => null]);
    }

    public function test_bad_ids_missing_resources_and_invalid_pagination_use_api_errors(): void
    {
        $this->grant('domain_all');
        $this->getJson('/api/v1/domains/bad/message-settings')->assertStatus(400)->assertJsonPath('error.param', 'domain_uuid');
        $this->postJson('/api/v1/domains/bad/message-settings', ['destination' => '6505556500'])
            ->assertStatus(400)->assertJsonPath('error.param', 'domain_uuid');
        $this->getJson('/api/v1/domains/'.Str::uuid().'/message-settings')->assertNotFound();
        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, $this->endpoint('bad'))->assertStatus(400)->assertJsonPath('error.param', 'sms_destination_uuid');
            $this->json($method, $this->endpoint((string) Str::uuid()))->assertNotFound();
        }
        foreach (['limit=0', 'limit=101', 'starting_after=bad', 'enabled=bad'] as $query) {
            $this->getJson($this->endpoint().'?'.$query)->assertStatus(400)->assertJsonPath('error.code', 'invalid_parameter');
        }
    }

    public function test_delete_removes_members_but_preserves_history_and_other_settings(): void
    {
        $uuid = $this->postJson($this->endpoint(), ['destination' => '+12125550123', 'allowed_extension_uuids' => [self::EXTENSION]])
            ->assertCreated()->json('sms_destination_uuid');
        $other = $this->seedSetting();
        DB::table('messages')->insert(['message_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN, 'destination' => '+12125550123', 'message' => 'Keep history']);
        $this->deleteJson($this->endpoint($uuid))->assertOk()->assertExactJson(['uuid' => $uuid, 'object' => 'message_setting', 'deleted' => true]);
        $this->assertDatabaseMissing('v_sms_destinations', ['sms_destination_uuid' => $uuid]);
        $this->assertDatabaseMissing('sms_destination_members', ['sms_destination_uuid' => $uuid]);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $other]);
        $this->assertDatabaseCount('messages', 1);
        $this->deleteJson($this->endpoint($uuid))->assertNotFound();
    }

    public function test_service_rolls_back_changes_when_members_belong_to_another_account(): void
    {
        $uuid = $this->seedSetting(['chatplan_detail_data' => '101']);
        try {
            app(MessageSettingsService::class)->update(MessageSetting::findOrFail($uuid), [
                'description' => 'Must roll back', 'allowed_extension_uuids' => [self::FOREIGN_EXTENSION],
            ]);
            $this->fail('Expected member validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('allowed_extension_uuids', $e->errors());
        }
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'description' => null, 'chatplan_detail_data' => '101']);
    }

    public function test_web_create_update_bulk_and_delete_share_the_service(): void
    {
        session(['domain_uuid' => self::DOMAIN, 'user.groups' => [['group_uuid' => self::ROLE]]]);
        Route::post('/test-message-settings', [WebMessageSettingsController::class, 'store']);
        Route::put('/test-message-settings/{setting}', [WebMessageSettingsController::class, 'update'])->middleware('api');
        Route::delete('/test-message-settings/{setting}', [WebMessageSettingsController::class, 'destroy'])->middleware('api');
        Route::post('/test-message-settings/bulk-update', [WebMessageSettingsController::class, 'bulkUpdate']);
        Route::post('/test-message-settings/bulk-delete', [WebMessageSettingsController::class, 'bulkDelete']);
        $this->postJson('/test-message-settings', ['destination' => '2125550123', 'allowed_extension_uuids' => [self::EXTENSION, self::SECOND]])->assertCreated();
        $uuid = DB::table('v_sms_destinations')->value('sms_destination_uuid');
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $uuid, 'destination' => '+12125550123']);
        $this->assertDatabaseCount('sms_destination_members', 2);
        $this->putJson('/test-message-settings/'.$uuid, ['destination' => '6505556500', 'chatplan_detail_data' => '102'])->assertOk();
        $this->getJson($this->endpoint($uuid))->assertOk()->assertJsonPath('allowed_extension_uuids', [self::SECOND])->assertJsonPath('destination', '+16505556500');
        $legacy = $this->seedSetting(['chatplan_detail_data' => '101']);
        $this->putJson('/test-message-settings/'.$legacy, ['destination' => '+12125550124', 'allowed_extension_uuids' => [self::EXTENSION]])->assertOk();
        $this->assertDatabaseHas('sms_destination_members', ['sms_destination_uuid' => $legacy, 'extension_uuid' => self::EXTENSION]);
        $this->postJson('/test-message-settings/bulk-update', ['items' => [$uuid, $legacy], 'allowed_extension_uuids' => []])->assertOk();
        $this->assertDatabaseCount('sms_destination_members', 0);
        $this->deleteJson('/test-message-settings/'.$uuid)->assertOk();
        $foreign = $this->seedSetting(['domain_uuid' => self::FOREIGN]);
        $this->postJson('/test-message-settings/bulk-delete', ['items' => [$legacy, $foreign]])->assertOk();
        $this->assertDatabaseCount('v_sms_destinations', 1);
        $this->assertDatabaseHas('v_sms_destinations', ['sms_destination_uuid' => $foreign]);
        $this->deleteJson('/test-message-settings/'.$foreign)->assertForbidden();
    }

    public function test_scribe_extracts_customer_facing_contract_without_response_calls(): void
    {
        config()->set('scribe.strategies.responses', [UseResponseTag::class]);
        foreach (self::routes() as [$method, $suffix, $action]) {
            $route = app('router')->getRoutes()->match(Request::create($this->endpoint().$suffix, $method));
            $endpoint = (new Extractor())->processRoute($route);
            $this->assertSame('Message Settings', $endpoint->metadata->groupName);
            $this->assertTrue($endpoint->metadata->authenticated);
            $this->assertNotEmpty($endpoint->responses);
            $this->assertStringNotContainsString('chatplan', $endpoint->metadata->description);
            $this->assertStringNotContainsString('sms_destination_members', $endpoint->metadata->description);
            $this->assertArrayNotHasKey('chatplan_detail_data', $endpoint->bodyParameters);
            $this->assertArrayNotHasKey('domain_uuid', $endpoint->bodyParameters);
            if (in_array($action, ['store', 'update'])) {
                $this->assertSame($action === 'store', $endpoint->bodyParameters['destination']->required);
                $this->assertStringContainsString('converted to E.164', $endpoint->bodyParameters['destination']->description);
                $this->assertStringContainsString('Default Settings', $endpoint->bodyParameters['destination']->description);
                $this->assertStringNotContainsString('US and Canadian', $endpoint->bodyParameters['destination']->description);
                $this->assertFalse($endpoint->bodyParameters['allowed_extension_uuids']->required);
                $this->assertSame('string[]', $endpoint->bodyParameters['allowed_extension_uuids']->type);
                $this->assertStringContainsString('omit to preserve', $endpoint->bodyParameters['allowed_extension_uuids']->description);
            }
        }
    }

    private function seedCountry(string $country, ?string $domainUuid = null, bool $enabled = true): void
    {
        if ($domainUuid === null) {
            DB::table('v_default_settings')->insert([
                'default_setting_subcategory' => 'country', 'default_setting_value' => $country,
                'default_setting_enabled' => $enabled ? 'true' : 'false',
            ]);
        } else {
            DB::table('v_domain_settings')->insert([
                'domain_uuid' => $domainUuid, 'domain_setting_subcategory' => 'country',
                'domain_setting_value' => $country, 'domain_setting_enabled' => $enabled ? 'true' : 'false',
            ]);
        }
    }

    private function seedSetting(array $attributes = []): string
    {
        $uuid = (string) Str::uuid();
        DB::table('v_sms_destinations')->insert(array_merge([
            'sms_destination_uuid' => $uuid, 'domain_uuid' => self::DOMAIN,
            'destination' => '+12125550123', 'enabled' => 'true',
        ], $attributes));

        return $uuid;
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('v_group_permissions')->insert(['group_uuid' => self::ROLE, 'permission_name' => $permission, 'permission_assigned' => 'true']);
        }
    }

    private function endpoint(string $uuid = ''): string
    {
        return '/api/v1/domains/'.self::DOMAIN.'/message-settings'.($uuid !== '' ? '/'.$uuid : '');
    }
}
