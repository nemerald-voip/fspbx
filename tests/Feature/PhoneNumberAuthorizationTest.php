<?php

namespace Tests\Feature;

use App\Jobs\BuildDialplanForPhoneNumber;
use App\Models\{Destinations, Dialplans, DialplanDetails, Extensions, User};
use App\Services\{DialplanBuilderService, DialplanService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Event, Http, Process, Redis, Schema};
use Mockery;
use Tests\TestCase;

class PhoneNumberAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const MISSING = '33333333-3333-4333-8333-333333333333';
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'phone_authorization',
            'database.connections.phone_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('phone_authorization');
        Bus::fake();
        Http::preventStrayRequests();
        Process::fake();
        $this->cache = Mockery::mock(DialplanService::class);
        $this->cache->shouldReceive('clearDialplanCache')->andReturnNull();
        $this->instance(DialplanService::class, $this->cache);
        foreach ([new Destinations, new Dialplans, new DialplanDetails, new Extensions] as $model) {
            $this->table($model->getTable(), array_unique([$model->getKeyName(), ...$model->getFillable(), 'update_date', 'update_user']));
        }
        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name', 'domain_description'],
            'v_domain_settings' => ['domain_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled'],
            'v_default_settings' => ['default_setting_category', 'default_setting_subcategory', 'default_setting_value', 'default_setting_enabled'],
            'v_fax' => ['fax_uuid', 'domain_uuid', 'fax_name', 'fax_extension'],
            'v_bridges' => ['bridge_uuid', 'domain_uuid', 'bridge_name', 'bridge_destination', 'bridge_enabled'],
            'v_recordings' => ['recording_uuid', 'domain_uuid', 'recording_filename', 'recording_name'],
            'v_music_on_hold' => ['music_on_hold_uuid', 'domain_uuid', 'music_on_hold_name', 'music_on_hold_path', 'music_on_hold_rate', 'music_on_hold_enabled'],
            'v_streams' => ['stream_uuid', 'domain_uuid', 'stream_name', 'stream_location', 'stream_enabled'],
            'extension_advanced_settings' => ['extension_advanced_setting_uuid', 'extension_uuid'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid', 'group_name'],
            'v_groups' => ['group_uuid', 'group_name', 'group_level'],
        ] as $table => $columns) {
            $this->table($table, $columns);
        }
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B] as $id => $domain) {
            DB::table('v_domains')->insert(['domain_uuid' => $domain, 'domain_name' => $domain.'.invalid', 'domain_description' => $domain]);
            DB::table('v_destinations')->insert([
                'destination_uuid' => $id, 'domain_uuid' => $domain, 'dialplan_uuid' => $id,
                'destination_number' => $id === self::LOCAL ? '2135550100' : '2135550101', 'destination_prefix' => '1',
                'destination_description' => 'original', 'destination_actions' => '[]', 'destination_context' => 'public',
            ]);
            DB::table('v_dialplans')->insert(['dialplan_uuid' => $id, 'domain_uuid' => $domain]);
            DB::table('v_dialplan_details')->insert(['dialplan_detail_uuid' => $id, 'dialplan_uuid' => $id, 'domain_uuid' => $domain]);
            DB::table('v_extensions')->insert(['extension_uuid' => $id, 'domain_uuid' => $domain, 'extension' => '100', 'effective_caller_id_name' => $domain]);
            DB::table('v_fax')->insert(['fax_uuid' => $id, 'domain_uuid' => $domain, 'fax_extension' => '200', 'fax_name' => $domain]);
            DB::table('v_bridges')->insert(['bridge_uuid' => $id, 'domain_uuid' => $domain, 'bridge_name' => $domain, 'bridge_destination' => 'sofia/example/100', 'bridge_enabled' => 'true']);
            DB::table('v_recordings')->insert(['recording_uuid' => $id, 'domain_uuid' => $domain, 'recording_filename' => $id.'.wav', 'recording_name' => $domain]);
        }
        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::A, 'domain_name' => self::A.'.invalid', 'domain.language.code' => 'en-us', 'domains' => [], 'permissions' => []]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('phone_authorization');
        parent::tearDown();
    }

    public static function endpoints(): array
    {
        return [
            ['GET', '/api/phone-numbers/data', 'destination_view', []],
            ['POST', '/api/phone-numbers/select-all', 'destination_view', []],
            ['POST', '/api/phone-numbers', 'destination_add', []],
            ['PUT', '/api/phone-numbers/'.self::LOCAL, 'destination_edit', []],
            ['POST', '/api/phone-numbers/bulk-update', 'destination_edit', []],
            ['POST', '/api/phone-numbers/bulk-delete', 'destination_delete', []],
            ['POST', '/api/phone-numbers/item-options', 'destination_edit', ['item_uuid' => self::LOCAL]],
            ['POST', '/api/phone-numbers/item-options', 'destination_add', ['mode' => 'create']],
            ['POST', '/api/phone-numbers/routing-options', 'destination_edit', []],
        ];
    }

    /** @dataProvider endpoints */
    public function test_action_permissions_are_required_even_with_server_wide_access(string $method, string $url, string $permission, array $data): void
    {
        $this->permissions('destination_all', 'destination_domain');
        $this->json($method, $url, $data)->assertForbidden();
        $this->unchanged();
    }

    /** @dataProvider endpoints */
    public function test_missing_account_context_fails_closed(string $method, string $url, string $permission, array $data): void
    {
        $this->permissions($permission, 'destination_all');
        $this->withSession(['domain_uuid' => null]);
        $this->json($method, $url, $data)->assertForbidden();
        $this->unchanged();
    }

    public function test_listing_requires_explicit_global_permission(): void
    {
        $this->permissions('destination_view');
        $this->postJson('/api/phone-numbers/select-all', ['filter' => ['showGlobal' => true]])->assertOk()->assertJsonPath('items', [self::LOCAL]);
        $this->getJson('/api/phone-numbers/data?filter[showGlobal]=true')->assertOk()->assertJsonCount(1, 'data');
        $this->permissions('destination_view', 'destination_all');
        $this->postJson('/api/phone-numbers/select-all', ['filter' => ['showGlobal' => true]])->assertOk()->assertJsonPath('items', [self::LOCAL, self::FOREIGN]);
        $this->getJson('/api/phone-numbers/data?filter[showGlobal]=true')->assertOk()->assertJsonCount(2, 'data');
    }

    public static function bulkActions(): array
    {
        return [['bulk-delete', 'destination_delete', []], ['bulk-update', 'destination_edit', ['destination_description' => 'changed']]];
    }

    /** @dataProvider bulkActions */
    public function test_bulk_requests_are_all_or_nothing(string $action, string $permission, array $data): void
    {
        $this->permissions($permission);
        foreach ([self::FOREIGN, self::MISSING] as $id) {
            $this->postJson('/api/phone-numbers/'.$action, ['items' => [self::LOCAL, $id]] + $data)->assertNotFound();
            $this->unchanged();
        }
        foreach ([[], 'invalid', ['invalid'], [self::LOCAL, self::LOCAL]] as $ids) {
            $this->postJson('/api/phone-numbers/'.$action, ['items' => $ids] + $data)->assertUnprocessable();
            $this->unchanged();
        }
    }

    public function test_single_edit_and_options_are_scoped_before_returning_data(): void
    {
        $this->permissions('destination_edit');
        $this->putJson('/api/phone-numbers/'.self::FOREIGN, ['destination_type_fax' => null])->assertNotFound();
        foreach (['itemUuid', 'item_uuid'] as $alias) {
            $this->postJson('/api/phone-numbers/item-options', [$alias => self::FOREIGN])->assertNotFound();
        }
        $this->postJson('/api/phone-numbers/item-options', ['item_uuid' => self::LOCAL, 'itemUuid' => self::FOREIGN])->assertUnprocessable();
        $this->permissions('destination_add');
        $this->postJson('/api/phone-numbers/item-options', ['mode' => 'create', 'item_uuid' => self::FOREIGN])->assertUnprocessable();
        $this->unchanged();
    }

    public function test_global_edit_keeps_owner_and_uses_that_accounts_options_and_routing(): void
    {
        $this->permissions('destination_edit', 'destination_all');
        $response = $this->postJson('/api/phone-numbers/item-options', ['itemUuid' => self::FOREIGN])->assertOk()
            ->assertJsonPath('item.domain_uuid', self::B)->assertJsonPath('faxes.0.value', self::FOREIGN);
        $this->postJson($response->json('routes.get_routing_options'), ['category' => 'extensions'])->assertOk()
            ->assertJsonPath('options.0.value', self::FOREIGN);
        $this->putJson('/api/phone-numbers/'.self::FOREIGN, [
            'destination_type_fax' => null, 'routing_options' => [['type' => 'extensions', 'extension' => '100']],
        ])->assertOk();
        $this->assertDatabaseHas('v_destinations', ['destination_uuid' => self::FOREIGN, 'domain_uuid' => self::B,
            'destination_actions' => json_encode([['destination_app' => 'transfer', 'destination_data' => '100 XML '.self::B.'.invalid']])]);
        Bus::assertDispatched(BuildDialplanForPhoneNumber::class, fn ($job) => (new \ReflectionProperty($job, 'domainName'))->getValue($job) === self::B.'.invalid');
    }

    public function test_authorized_deletion_removes_number_dialplan_and_details_only_in_scope(): void
    {
        $this->permissions('destination_delete');
        $this->postJson('/api/phone-numbers/bulk-delete', ['items' => [self::LOCAL]])->assertOk();
        foreach (['v_destinations', 'v_dialplans', 'v_dialplan_details'] as $table) {
            $this->assertDatabaseMissing($table, ['domain_uuid' => self::A]);
            $this->assertDatabaseHas($table, ['domain_uuid' => self::B]);
        }
        $this->permissions('destination_delete', 'destination_all');
        $this->postJson('/api/phone-numbers/bulk-delete', ['items' => [self::FOREIGN]])->assertOk();
    }

    public function test_transfers_require_explicit_permissions_and_rebuild_account_routing(): void
    {
        foreach ([['destination_edit'], ['destination_edit', 'destination_all'], ['destination_edit', 'destination_domain']] as $permissions) {
            $this->permissions(...$permissions);
            $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::B])->assertForbidden();
            $this->unchanged();
        }
        $this->permissions('destination_edit', 'destination_all', 'destination_domain');
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::MISSING])->assertUnprocessable();
        DB::table('v_destinations')->where('destination_uuid', self::LOCAL)->update([
            'destination_actions' => json_encode([['destination_app' => 'transfer', 'destination_data' => '100 XML '.self::A.'.invalid']]),
        ]);
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::B])->assertOk();
        foreach (['v_destinations', 'v_dialplans', 'v_dialplan_details'] as $table) {
            $this->assertDatabaseMissing($table, ['domain_uuid' => self::A]);
        }
        $this->assertStringContainsString(self::B.'.invalid', DB::table('v_destinations')->where('destination_uuid', self::LOCAL)->value('destination_actions'));
    }

    public function test_foreign_references_and_raw_actions_are_rejected_before_saves(): void
    {
        $this->permissions('destination_edit', 'destination_all');
        foreach ([
            ['fax_uuid' => self::FOREIGN],
            ['routing_options' => [['type' => 'bridges', 'extension' => self::FOREIGN, 'bridge_uuid' => self::FOREIGN]]],
            ['routing_options' => [['type' => 'recordings', 'extension' => self::FOREIGN.'.wav']]],
            ['routing_options' => [['type' => 'extensions', 'extension' => '999']]],
            ['destination_actions' => [['destination_app' => 'transfer', 'destination_data' => '100 XML '.self::B.'.invalid']]],
        ] as $data) {
            $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL]] + $data)->assertUnprocessable();
            $this->unchanged();
        }
        $saved = 0;
        Event::listen('eloquent.updated: '.Destinations::class, function () use (&$saved) { $saved++; });
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL, self::FOREIGN], 'fax_uuid' => self::LOCAL])->assertUnprocessable();
        $this->assertSame(0, $saved);
        $this->unchanged();
    }

    public function test_corrupt_dialplan_link_cannot_delete_or_update_another_accounts_plan(): void
    {
        DB::table('v_destinations')->where('destination_uuid', self::LOCAL)->update(['dialplan_uuid' => self::FOREIGN]);
        $this->permissions('destination_edit', 'destination_delete', 'destination_all');
        $this->postJson('/api/phone-numbers/bulk-delete', ['items' => [self::LOCAL]])->assertNotFound();
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL], 'destination_description' => 'changed'])->assertNotFound();
        $this->unchanged();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DialplanBuilderService::class)->buildDialplanForPhoneNumber(Destinations::find(self::LOCAL), self::A.'.invalid');
    }

    public function test_create_requires_authorized_account_and_valid_routing(): void
    {
        $this->permissions('destination_add');
        $data = ['destination_number' => '2135550109', 'destination_prefix' => '1', 'destination_type_fax' => null];
        $this->postJson('/api/phone-numbers', $data + ['domain_uuid' => self::B])->assertForbidden();
        $this->postJson('/api/phone-numbers', $data + ['fax_uuid' => self::FOREIGN])->assertUnprocessable();
        $this->permissions('destination_add', 'destination_all', 'destination_domain');
        $this->postJson('/api/phone-numbers', $data + ['domain_uuid' => self::B, 'routing_options' => [['type' => 'hangup']]])->assertCreated();
        $this->assertDatabaseHas('v_destinations', ['destination_number' => '2135550109', 'domain_uuid' => self::B]);
    }

    public function test_queued_build_uses_current_owner_instead_of_stale_session_payload(): void
    {
        $limiter = Mockery::mock();
        Redis::shouldReceive('throttle')->with('dialplan')->once()->andReturn($limiter);
        $limiter->shouldReceive('allow')->with(2)->andReturnSelf();
        $limiter->shouldReceive('every')->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->once()->andReturnUsing(fn ($callback) => $callback());
        $builder = Mockery::mock(DialplanBuilderService::class);
        $builder->shouldReceive('buildDialplanForPhoneNumber')->once()
            ->with(Mockery::on(fn ($number) => $number->domain_uuid === self::B), self::B.'.invalid');
        (new BuildDialplanForPhoneNumber(self::FOREIGN, self::A.'.invalid'))->handle($builder);
    }

    public function test_single_transfer_requires_permission_and_rejects_old_account_fax(): void
    {
        $this->permissions('destination_edit', 'destination_all');
        $payload = ['destination_type_fax' => null, 'domain_uuid' => self::B];
        $this->putJson('/api/phone-numbers/'.self::LOCAL, $payload)->assertForbidden();
        $this->permissions('destination_edit', 'destination_all', 'destination_domain');
        DB::table('v_destinations')->where('destination_uuid', self::LOCAL)->update(['fax_uuid' => self::LOCAL]);
        $this->putJson('/api/phone-numbers/'.self::LOCAL, $payload)->assertUnprocessable()->assertJsonValidationErrors('fax_uuid');
        $this->unchanged();
        $this->putJson('/api/phone-numbers/'.self::LOCAL, $payload + ['fax_uuid' => null])->assertOk();
        $this->assertDatabaseHas('v_destinations', ['destination_uuid' => self::LOCAL, 'domain_uuid' => self::B]);
        $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => self::LOCAL, 'domain_uuid' => self::B]);
    }

    public function test_caller_conditions_use_only_valid_routing_in_the_numbers_account(): void
    {
        $this->permissions('destination_edit');
        $condition = ['condition_field' => 'caller_id_number', 'condition_expression' => '+12135550100'];
        $payload = ['destination_type_fax' => null, 'destination_conditions' => [$condition + [
            'condition_target' => ['targetValue' => 'lua:bridge.lua '.self::FOREIGN],
        ]]];
        $this->putJson('/api/phone-numbers/'.self::LOCAL, $payload)->assertUnprocessable();
        $this->unchanged();
        $payload['destination_conditions'][0]['condition_target']['targetValue'] = 'transfer:100 XML '.self::B.'.invalid';
        $this->putJson('/api/phone-numbers/'.self::LOCAL, $payload)->assertOk();
        $saved = json_decode(DB::table('v_destinations')->where('destination_uuid', self::LOCAL)->value('destination_conditions'), true);
        $this->assertSame('100 XML '.self::A.'.invalid', $saved[0]['condition_data']);
    }

    public function test_import_preflights_all_routing_and_keeps_the_session_account(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\CheckFusionPBXLogin::class, \App\Http\Middleware\NoStoreByDefault::class]);
        $this->permissions('destination_import');
        $items = [
            ['destination_number' => '2135550110', 'routing_type' => 'extensions', 'routing_extension' => '100'],
            ['destination_number' => '2135550111', 'routing_type' => 'bridges', 'routing_extension' => self::FOREIGN],
        ];
        $this->postJson('/phone-numbers/import/commit', ['items' => $items])->assertUnprocessable();
        $this->assertDatabaseCount('v_destinations', 2);
        $this->unchanged();
        $items[1]['routing_extension'] = self::LOCAL;
        $this->postJson('/phone-numbers/import/commit', ['domain_uuid' => self::B, 'items' => $items])->assertOk();
        $this->assertDatabaseHas('v_destinations', ['destination_number' => '2135550110', 'domain_uuid' => self::A]);
        $this->assertDatabaseHas('v_destinations', ['destination_number' => '2135550111', 'domain_uuid' => self::A]);
    }

    public function test_native_endpoints_reject_bearer_and_unauthenticated_requests(): void
    {
        $this->permissions('destination_delete');
        $this->withToken('fixture-token')->postJson('/api/phone-numbers/bulk-delete', ['items' => [self::LOCAL]])->assertUnauthorized();
        $this->flushHeaders();
        auth()->forgetGuards();
        $this->app['session']->flush();
        $this->postJson('/api/phone-numbers/bulk-delete', ['items' => [self::LOCAL]])->assertRedirect(route('login'));
        $this->unchanged();
    }

    public function test_item_options_modes_and_routing_parameters_are_validated(): void
    {
        $this->permissions('destination_add', 'destination_edit', 'destination_all', 'destination_domain');
        foreach ([
            ['mode' => 'update'], ['mode' => 'invalid'], ['item_uuid' => ['invalid']],
            ['mode' => 'bulk_update', 'item_uuid' => self::LOCAL],
            ['item_uuid' => self::LOCAL, 'domain_uuid' => 'invalid'],
            ['item_uuid' => self::LOCAL, 'domain_uuid' => self::MISSING],
        ] as $payload) {
            $this->postJson('/api/phone-numbers/item-options', $payload)->assertUnprocessable();
        }
        foreach ([['item_uuid' => 'invalid'], ['domain_uuid' => self::MISSING], ['category' => ['extensions']]] as $payload) {
            $this->postJson('/api/phone-numbers/routing-options', $payload)->assertUnprocessable();
        }
        foreach (['create', 'bulk_update'] as $mode) {
            $this->postJson('/api/phone-numbers/item-options', ['mode' => $mode])
                ->assertOk()->assertJsonPath('routes.update_route', null);
        }
        $this->permissions('destination_add');
        $this->postJson('/api/phone-numbers/item-options', ['mode' => 'bulk_update'])->assertForbidden();
        $this->unchanged();
    }

    public function test_edit_options_fetches_the_number_once(): void
    {
        $this->permissions('destination_edit');
        DB::connection()->enableQueryLog();
        $this->postJson('/api/phone-numbers/item-options', ['item_uuid' => self::LOCAL])
            ->assertOk()->assertJsonPath('item.destination_uuid', self::LOCAL);
        $reads = collect(DB::connection()->getQueryLog())->filter(
            fn ($query) => str_contains($query['query'], 'from "v_destinations"')
        );
        DB::connection()->disableQueryLog();
        $this->assertCount(1, $reads);
    }

    public function test_write_requests_reject_malformed_routing_before_any_changes(): void
    {
        $this->permissions('destination_add', 'destination_edit');
        $create = ['destination_number' => '2135550110', 'destination_prefix' => '1', 'destination_type_fax' => null];
        foreach ([
            ['invalid'], [['extension' => '100']], [['type' => 'unsupported']], [['type' => ['extensions']]],
            [['type' => 'extensions', 'extension' => ['100']]],
            [['type' => 'bridges', 'bridge_uuid' => 'invalid']], [['type' => 'extensions', 'option' => 'invalid']],
        ] as $options) {
            $data = ['routing_options' => $options];
            $this->postJson('/api/phone-numbers', $create + $data)->assertUnprocessable();
            $this->putJson('/api/phone-numbers/'.self::LOCAL, ['destination_type_fax' => null] + $data)->assertUnprocessable();
            $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL]] + $data)->assertUnprocessable();
            $this->unchanged();
        }
        $raw = ['destination_actions' => [['destination_app' => 'transfer', 'destination_data' => '100 XML '.self::B.'.invalid']]];
        $this->postJson('/api/phone-numbers', $create + $raw)->assertUnprocessable()->assertJsonValidationErrors('destination_actions');
        $this->putJson('/api/phone-numbers/'.self::LOCAL, ['destination_type_fax' => null] + $raw)
            ->assertUnprocessable()->assertJsonValidationErrors('destination_actions');
        $this->unchanged();
    }

    public function test_numeric_routing_extensions_remain_supported_by_all_write_requests(): void
    {
        $this->permissions('destination_add', 'destination_edit');
        $routing = ['routing_options' => [['type' => 'extensions', 'extension' => 100]]];
        $this->postJson('/api/phone-numbers', [
            'destination_number' => '2135550110', 'destination_prefix' => '1', 'destination_type_fax' => null,
        ] + $routing)->assertCreated();
        $this->putJson('/api/phone-numbers/'.self::LOCAL, ['destination_type_fax' => null] + $routing)->assertOk();
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL]] + $routing)->assertOk();
        $actions = json_encode([['destination_app' => 'transfer', 'destination_data' => '100 XML '.self::A.'.invalid']]);
        foreach (['2135550100', '2135550110'] as $number) {
            $this->assertDatabaseHas('v_destinations', ['destination_number' => $number, 'destination_actions' => $actions]);
        }
    }

    public function test_bulk_update_requires_a_supported_field_and_preflights_every_dialplan(): void
    {
        $this->permissions('destination_edit', 'destination_delete', 'destination_all');
        foreach ([[], ['unknown_field' => 'value']] as $data) {
            $this->postJson('/api/phone-numbers/bulk-update', ['items' => [self::LOCAL]] + $data)
                ->assertUnprocessable()->assertJsonValidationErrors('input');
        }
        DB::table('v_destinations')->where('destination_uuid', self::FOREIGN)->update(['dialplan_uuid' => self::LOCAL]);
        $saved = 0;
        Event::listen('eloquent.updated: '.Destinations::class, function () use (&$saved) { $saved++; });
        $items = [self::LOCAL, self::FOREIGN];
        $this->postJson('/api/phone-numbers/bulk-update', ['items' => $items, 'destination_description' => 'changed'])->assertNotFound();
        $this->postJson('/api/phone-numbers/bulk-delete', ['items' => $items])->assertNotFound();
        $this->assertSame(0, $saved);
        $this->unchanged();
    }

    public function test_import_requests_reject_invalid_rows_duplicates_and_missing_uploads(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\CheckFusionPBXLogin::class, \App\Http\Middleware\NoStoreByDefault::class]);
        $this->permissions('destination_import');
        $this->postJson('/phone-numbers/import')->assertUnprocessable()->assertJsonValidationErrors('file');
        foreach ([
            [], ['invalid'], [['destination_description' => 'missing number']],
            [['destination_number' => '2135550110', 'routing_type' => 'unsupported']],
            [['destination_number' => '2135550110', 'routing_extension' => ['100']]],
        ] as $items) {
            $this->postJson('/phone-numbers/import/commit', ['items' => $items])->assertUnprocessable();
        }
        $row = ['destination_number' => '2135550110'];
        $this->postJson('/phone-numbers/import/commit', ['items' => [$row, $row]])
            ->assertUnprocessable()->assertJsonValidationErrors('server');
        $this->postJson('/phone-numbers/import/commit', ['items' => [['destination_number' => '2135550100']]])
            ->assertUnprocessable()->assertJsonValidationErrors('server');
        $this->unchanged();
    }

    private function permissions(string ...$permissions): void
    {
        $this->withSession(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $permissions)]);
    }

    private function table(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    private function unchanged(): void
    {
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B] as $id => $domain) {
            $this->assertDatabaseHas('v_destinations', ['destination_uuid' => $id, 'domain_uuid' => $domain, 'destination_description' => 'original']);
            $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => $id, 'domain_uuid' => $domain]);
            $this->assertDatabaseHas('v_dialplan_details', ['dialplan_uuid' => $id, 'domain_uuid' => $domain]);
        }
        Bus::assertNothingDispatched();
        $this->cache->shouldNotHaveReceived('clearDialplanCache');
    }
}
