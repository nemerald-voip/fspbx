<?php

namespace Tests\Feature;

use App\Http\Controllers\ProvisioningController;
use App\Models\{DeviceLines, Devices, User};
use App\Services\{CloudProviderSelector, DeviceActionService, DeviceCloudProvisioningService, FreeswitchEslService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\{Bus, DB, Event, File, Http, Process, Schema};
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DeviceAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const C = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const SHARED = '22222222-2222-4222-8222-222222222222';
    private const FOREIGN = '33333333-3333-4333-8333-333333333333';
    private const MISSING = '44444444-4444-4444-8444-444444444444';
    private const GLOBAL = '55555555-5555-4555-8555-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'device_authorization',
            'database.connections.device_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('device_authorization');
        Http::preventStrayRequests();
        Bus::fake();
        Process::fake();
        foreach ([FreeswitchEslService::class, DeviceActionService::class, DeviceCloudProvisioningService::class] as $service) {
            $this->app->bind($service, function () use ($service) {
                $this->fail('Unexpected external service resolution: '.$service);
            });
        }
        $selector = Mockery::mock(CloudProviderSelector::class);
        $selector->shouldReceive('getCloudProvider')->andReturn(null);
        $this->instance(CloudProviderSelector::class, $selector);

        $this->table('v_devices', array_unique(['device_uuid', ...(new Devices)->getFillable()]));
        $this->table('v_device_lines', array_unique(['device_line_uuid', ...(new DeviceLines)->getFillable()]));
        $schemas = [
            'v_domains' => ['domain_uuid', 'domain_name', 'domain_description', 'domain_enabled'],
            'v_domain_settings' => ['domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_name', 'domain_setting_order', 'domain_setting_enabled', 'domain_setting_value'],
            'v_default_settings' => ['default_setting_uuid', 'default_setting_category', 'default_setting_subcategory', 'default_setting_name', 'default_setting_order', 'default_setting_enabled', 'default_setting_value'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name', 'directory_first_name', 'directory_last_name', 'password'],
            'extension_advanced_settings' => ['extension_uuid', 'extension_advanced_setting_uuid'],
            'v_device_profiles' => ['device_profile_uuid', 'domain_uuid', 'device_profile_name', 'device_profile_description', 'device_profile_enabled'],
            'device_key_templates' => ['device_key_template_uuid', 'domain_uuid', 'name', 'description', 'enabled'],
            'device_keys' => ['device_key_uuid', 'device_uuid', 'key_area', 'key_index', 'key_type', 'key_value', 'key_label'],
            'v_device_keys' => ['device_key_uuid', 'device_uuid', 'device_key_id', 'device_key_category', 'device_key_type', 'device_key_line', 'device_key_value', 'device_key_extension', 'device_key_label'],
            'v_device_settings' => ['device_setting_uuid', 'device_uuid', 'domain_uuid', 'device_setting_category', 'device_setting_subcategory', 'device_setting_name', 'device_setting_value', 'device_setting_enabled', 'device_setting_description'],
            'device_cloud_provisioning' => ['uuid', 'device_uuid', 'domain_uuid', 'provider', 'last_action', 'status'],
            'provisioning_templates' => ['template_uuid', 'domain_uuid', 'vendor', 'name', 'type', 'version', 'revision', 'content', 'checksum', 'created_at', 'updated_at'],
            'v_device_vendors' => ['device_vendor_uuid', 'name', 'enabled'],
            'phonebooks' => ['phonebook_uuid', 'domain_uuid', 'name', 'enabled', 'is_default', 'include_extensions'],
            'device_phonebook' => ['device_uuid', 'phonebook_uuid', 'slot', 'created_at', 'updated_at'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid', 'group_name'],
            'v_groups' => ['group_uuid', 'group_name', 'group_level'],
        ];
        foreach ($schemas as $name => $columns) {
            $this->table($name, $columns);
        }
        foreach ([self::LOCAL => self::A, self::SHARED => self::B, self::FOREIGN => self::C] as $id => $domain) {
            $mac = $this->mac($id);
            DB::table('v_domains')->insert(['domain_uuid' => $domain, 'domain_name' => $domain.'.invalid', 'domain_description' => $domain, 'domain_enabled' => 'true']);
            DB::table('v_devices')->insert(['device_uuid' => $id, 'domain_uuid' => $domain, 'device_address' => $mac, 'device_vendor' => 'fixture', 'device_description' => 'original', 'device_enabled' => 'true']);
            DB::table('v_device_lines')->insert(['device_line_uuid' => $id, 'device_uuid' => $id, 'domain_uuid' => $domain, 'line_number' => '1', 'auth_id' => '100', 'user_id' => '100', 'server_address' => $domain.'.invalid', 'password' => 'fixture-secret', 'sip_port' => '5060']);
            DB::table('v_extensions')->insert(['extension_uuid' => $id, 'domain_uuid' => $domain, 'extension' => '100', 'effective_caller_id_name' => $domain, 'password' => 'fixture-secret']);
            DB::table('v_device_profiles')->insert(['device_profile_uuid' => $id, 'domain_uuid' => $domain, 'device_profile_name' => $domain, 'device_profile_enabled' => 'true']);
            DB::table('device_key_templates')->insert(['device_key_template_uuid' => $id, 'domain_uuid' => $domain, 'name' => $domain, 'enabled' => 'true']);
            DB::table('provisioning_templates')->insert(['template_uuid' => $id, 'domain_uuid' => $domain, 'vendor' => 'fixture', 'name' => $domain, 'type' => 'custom', 'revision' => '1']);
            DB::table('phonebooks')->insert(['phonebook_uuid' => $id, 'domain_uuid' => $domain, 'name' => $domain, 'enabled' => '1']);
            DB::table('device_phonebook')->insert(['device_uuid' => $id, 'phonebook_uuid' => $id, 'slot' => '1']);
        }
        DB::table('provisioning_templates')->insert(['template_uuid' => self::GLOBAL, 'domain_uuid' => null, 'vendor' => 'fixture', 'name' => 'global', 'type' => 'default', 'revision' => '0']);
        DB::table('v_device_vendors')->insert(['device_vendor_uuid' => self::GLOBAL, 'name' => 'fixture', 'enabled' => 'true']);

        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession([
            'domain_uuid' => self::A, 'domain_name' => self::A.'.invalid', 'domain.language.code' => 'en-us',
            'permissions' => [], 'domains' => [(object) ['domain_uuid' => self::B, 'domain_name' => self::B.'.invalid']],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('device_authorization');
        parent::tearDown();
    }

    public static function permissionEndpoints(): array
    {
        return [
            ['GET', '/api/devices/data', 'device_view'],
            ['POST', '/api/devices/select-all', 'device_view'],
            ['POST', '/api/devices', 'device_add'],
            ['PUT', '/api/devices/'.self::LOCAL, 'device_edit'],
            ['POST', '/api/devices/bulk-update', 'device_edit'],
            ['POST', '/api/devices/bulk-delete', 'device_delete'],
            ['POST', '/api/devices/restart', 'device_edit'],
            ['POST', '/api/devices/sync', 'device_edit'],
            ['POST', '/api/devices/assign', 'extension_device_assign'],
            ['POST', '/api/devices/bulk-unassign', 'extension_device_unassign'],
            ['GET', '/api/devices/'.self::LOCAL.'/provisioning-preview', 'device_provisioning_preview'],
        ];
    }

    /** @dataProvider permissionEndpoints */
    public function test_action_permission_is_required_before_validation(string $method, string $url, string $permission): void
    {
        $this->permissions('device_all');
        $this->json($method, $url)->assertForbidden();
        $this->unchanged();
    }

    /** @dataProvider permissionEndpoints */
    public function test_missing_account_context_fails_closed(string $method, string $url, string $permission): void
    {
        $this->permissions($permission, 'device_all');
        $this->withSession(['domain_uuid' => null]);
        $this->json($method, $url)->assertForbidden();
        $this->unchanged();
    }

    public function test_global_list_and_selection_require_permission_and_cover_all_accounts(): void
    {
        $this->permissions('device_view');
        $this->postJson('/api/devices/select-all', ['showGlobal' => true])->assertOk()->assertJsonPath('items', [self::LOCAL]);
        $this->getJson('/api/devices/data?filter[showGlobal]=true')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/devices/select-all')->assertOk()->assertJsonPath('items', [self::LOCAL]);
        $this->getJson('/api/devices/data')->assertOk()->assertJsonCount(1, 'data');

        $this->permissions('device_view', 'device_all');
        foreach ([[(object) ['domain_uuid' => self::B]], [['domain_uuid' => self::B]], collect([['domain_uuid' => self::B]])] as $domains) {
            $this->withSession(['domains' => $domains]);
            $this->postJson('/api/devices/select-all', ['showGlobal' => true])->assertOk()->assertJsonPath('items', [self::LOCAL, self::SHARED, self::FOREIGN]);
            $this->getJson('/api/devices/data?filter[showGlobal]=true')->assertOk()->assertJsonCount(3, 'data');
        }
        $this->withSession(['domains' => null]);
        $this->postJson('/api/devices/select-all', ['showGlobal' => true])->assertOk()->assertJsonPath('items', [self::LOCAL, self::SHARED, self::FOREIGN]);
    }

    public function test_native_actions_keep_the_cookie_authentication_boundary(): void
    {
        $this->permissions('device_delete');
        $this->withToken('fixture-token')->postJson('/api/devices/bulk-delete', ['items' => [self::LOCAL]])->assertUnauthorized();
        $this->flushHeaders();
        auth()->forgetGuards();
        $this->app['session']->flush();
        $this->postJson('/api/devices/bulk-delete', ['items' => [self::LOCAL]])->assertRedirect(route('login'));
        $this->unchanged();
    }

    public function test_duplicate_checks_permission_and_source_account_before_copying(): void
    {
        // The test actor has no legacy PHP session; keep Laravel auth and action middleware.
        $this->withoutMiddleware([
            \App\Http\Middleware\CheckFusionPBXLogin::class,
            \App\Http\Middleware\NoStoreByDefault::class,
        ]);
        $payload = ['uuid' => self::FOREIGN, 'new_mac_address' => '00:00:00:00:00:09'];
        $this->postJson('/devices/duplicate', $payload)->assertForbidden();
        $this->permissions('device_add');
        $this->postJson('/devices/duplicate', $payload)->assertNotFound();
        $this->unchanged();
        $this->permissions('device_add', 'device_all');
        $payload['uuid'] = self::SHARED;
        $response = $this->postJson('/devices/duplicate', $payload)->assertCreated();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => $response->json('device_uuid'), 'domain_uuid' => self::B]);
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => $response->json('device_uuid'), 'domain_uuid' => self::B]);
    }

    public function test_token_api_request_validation_uses_route_account_without_a_session(): void
    {
        $this->withSession(['domain_uuid' => null, 'permissions' => []]);
        $class = \App\Http\Requests\Api\V1\StoreDeviceRequest::class;
        $request = $class::create('/api/v1/domains/'.self::B.'/devices', 'POST', [
            'device_address' => '00:00:00:00:00:09', 'device_key_template_uuid' => self::SHARED,
        ]);
        $route = (new Route('POST', 'api/v1/domains/{domain_uuid}/devices', fn () => null))->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();
        $this->assertSame(self::SHARED, $request->validated()['device_key_template_uuid']);
        $this->assertArrayNotHasKey('domain_uuid', $request->validated());
        $request->merge(['device_key_template_uuid' => self::FOREIGN]);
        $this->assertFalse(\Illuminate\Support\Facades\Validator::make($request->all(), $request->rules())->passes());

        $update = \App\Http\Requests\Api\V1\UpdateDeviceRequest::create('/devices', 'PUT', ['device_description' => 'changed']);
        $update->setContainer($this->app)->setRedirector($this->app['redirect']);
        $update->validateResolved();
        $this->assertSame(['device_description' => 'changed'], $update->validated());
    }

    public static function bulkActions(): array
    {
        return [
            ['bulk-delete', 'device_delete', 'items', []],
            ['bulk-update', 'device_edit', 'items', ['device_description' => 'changed']],
            ['bulk-update', 'device_edit', 'items', ['line_sip_port' => 5070, 'resync_devices' => true]],
            ['restart', 'device_edit', 'devices', []],
            ['sync', 'device_edit', 'devices', []],
        ];
    }

    /** @dataProvider bulkActions */
    public function test_mixed_bulk_requests_are_rejected_before_any_effect(string $action, string $permission, string $field, array $extra): void
    {
        $this->permissions($permission, 'device_line_edit');
        foreach ([self::FOREIGN, self::MISSING] as $id) {
            $this->permissions($permission, 'device_line_edit', ...($id === self::MISSING ? ['device_all'] : []));
            $this->postJson('/api/devices/'.$action, [$field => [self::LOCAL, $id]] + $extra)->assertNotFound();
            $this->unchanged();
        }
        foreach ([[], ['invalid'], [self::LOCAL, self::LOCAL], self::LOCAL] as $ids) {
            $this->postJson('/api/devices/'.$action, [$field => $ids] + $extra)->assertUnprocessable();
            $this->unchanged();
        }
    }

    public function test_delete_removes_only_authorized_devices_and_children(): void
    {
        $this->permissions('device_delete');
        $this->postJson('/api/devices/bulk-delete', ['items' => [self::SHARED]])->assertNotFound();
        $this->postJson('/api/devices/bulk-delete', ['items' => [self::LOCAL]])->assertOk();
        $this->assertDatabaseMissing('v_devices', ['device_uuid' => self::LOCAL]);
        $this->assertDatabaseMissing('v_device_lines', ['device_uuid' => self::LOCAL]);
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::SHARED]);

        $this->permissions('device_delete', 'device_all');
        $this->postJson('/api/devices/bulk-delete', ['items' => [self::SHARED]])->assertOk();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::FOREIGN]);
    }

    public function test_single_update_and_item_options_cannot_access_another_account(): void
    {
        $this->permissions('device_edit');
        $this->putJson('/api/devices/'.self::SHARED, $this->editPayload(self::SHARED))->assertNotFound();
        foreach (['itemUuid', 'item_uuid'] as $field) {
            $this->postJson('/api/devices/item-options', [$field => self::SHARED])->assertNotFound();
        }
        $this->postJson('/api/devices/item-options', ['itemUuid' => self::LOCAL, 'domain_uuid' => self::B])->assertForbidden();
        $this->postJson('/api/devices/item-options', ['itemUuid' => self::LOCAL, 'item_uuid' => self::SHARED])->assertUnprocessable();
        $this->permissions('device_edit', 'device_add');
        $this->postJson('/api/devices/item-options', ['mode' => 'create', 'itemUuid' => self::SHARED])->assertUnprocessable();
        $this->unchanged();
    }

    public function test_permitted_foreign_edit_preserves_owner_when_omitted_and_options_use_its_account(): void
    {
        $this->permissions('device_edit', 'device_all', 'device_template_view_all');
        $this->putJson('/api/devices/'.self::SHARED, $this->editPayload(self::SHARED))->assertOk();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::SHARED, 'domain_uuid' => self::B, 'device_description' => 'changed']);
        $this->postJson('/api/devices/item-options', ['item_uuid' => self::SHARED])->assertOk()
            ->assertJsonPath('item.domain_uuid', self::B)->assertJsonPath('lines.0.device_line_uuid', self::SHARED)
            ->assertJsonPath('default_line_options.domain_uuid', self::B)->assertJsonCount(3, 'domains');
    }

    public function test_transfers_require_both_permissions_and_an_existing_destination(): void
    {
        foreach ([['device_edit'], ['device_edit', 'device_all'], ['device_edit', 'device_domain']] as $permissions) {
            $this->permissions(...$permissions);
            $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::B])->assertForbidden();
            $this->putJson('/api/devices/'.self::LOCAL, $this->editPayload(self::LOCAL) + ['domain_uuid' => self::B])->assertForbidden();
            $this->unchanged();
        }
        $this->permissions('device_edit', 'device_all', 'device_domain');
        $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::MISSING])->assertUnprocessable();
        foreach ([null, '', 'invalid'] as $domain) {
            $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => $domain])->assertUnprocessable();
        }
        $this->permissions('device_edit');
        $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::A])->assertOk();
        $this->permissions('device_edit', 'device_all', 'device_domain');
        $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::C])->assertOk();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::LOCAL, 'domain_uuid' => self::C]);
    }

    public static function transferModes(): array
    {
        return [['single'], ['bulk']];
    }

    /** @dataProvider transferModes */
    public function test_transfer_clears_old_assignments_and_cascades_sip_lines(string $mode): void
    {
        $this->permissions('device_edit', 'device_all', 'device_domain', 'device_key_template_assign');
        DB::table('v_devices')->where('device_uuid', self::LOCAL)->update([
            'device_profile_uuid' => self::LOCAL, 'device_key_template_uuid' => self::LOCAL,
            'device_template_uuid' => self::LOCAL,
        ]);
        $data = ['domain_uuid' => self::B];
        if ($mode === 'single') {
            $data += ['phonebook_mode' => 'custom', 'phonebook_uuids' => [self::LOCAL]];
        }
        $response = $mode === 'single'
            ? $this->putJson('/api/devices/'.self::LOCAL, $this->editPayload(self::LOCAL) + $data)
            : $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL]] + $data);
        $response->assertOk()->assertSee('Removed assignments')->assertSee('Phonebooks')->assertSee('Select a provisioning template');
        $this->assertDatabaseHas('v_devices', [
            'device_uuid' => self::LOCAL, 'domain_uuid' => self::B,
            'device_profile_uuid' => null, 'device_key_template_uuid' => null, 'device_template_uuid' => null,
        ]);
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::LOCAL, 'domain_uuid' => self::B, 'server_address' => self::B.'.invalid']);
        $this->assertDatabaseMissing('device_phonebook', ['device_uuid' => self::LOCAL]);
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::FOREIGN, 'domain_uuid' => self::C]);
    }

    public function test_transfer_keeps_global_template_and_valid_destination_assignments(): void
    {
        $this->permissions('device_edit', 'device_all', 'device_domain', 'device_key_template_assign');
        DB::table('v_devices')->where('device_uuid', self::LOCAL)->update(['device_template_uuid' => self::GLOBAL]);
        $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => self::B, 'device_key_template_uuid' => self::SHARED])->assertOk();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::LOCAL, 'device_template_uuid' => self::GLOBAL, 'device_key_template_uuid' => self::SHARED]);
    }

    public function test_relationship_validation_rejects_foreign_references_and_lines(): void
    {
        $this->permissions('device_edit', 'device_key_template_assign');
        foreach (['device_profile_uuid', 'device_key_template_uuid', 'device_template_uuid'] as $field) {
            $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], $field => self::FOREIGN])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson('/api/devices/'.self::LOCAL, $this->editPayload(self::LOCAL) + ['phonebook_uuids' => [self::FOREIGN]])->assertUnprocessable();
        $this->putJson('/api/devices/'.self::LOCAL, $this->editPayload(self::LOCAL) + ['device_lines' => [[
            'line_type_id' => 'line', 'line_number' => 1, 'auth_id' => '100', 'device_line_uuid' => self::FOREIGN,
        ]]])->assertUnprocessable();
        $this->unchanged();
    }

    public function test_batch_reference_validation_finishes_before_any_save(): void
    {
        $this->permissions('device_edit', 'device_all', 'device_key_template_assign');
        $saved = 0;
        Event::listen('eloquent.updated: '.Devices::class, function () use (&$saved) { $saved++; });
        // Both devices are accessible, but only the first can use this account's template.
        $this->postJson('/api/devices/bulk-update', [
            'items' => [self::LOCAL, self::SHARED], 'device_key_template_uuid' => self::LOCAL,
        ])->assertUnprocessable()->assertJsonValidationErrors('device_key_template_uuid');
        $this->assertSame(0, $saved);
        $this->unchanged();
    }

    public function test_authorized_line_only_bulk_update_preserves_other_accounts(): void
    {
        $this->permissions('device_edit', 'device_line_edit');
        $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'line_sip_port' => 5070])->assertOk();
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::LOCAL, 'sip_port' => '5070']);
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::SHARED, 'sip_port' => '5060']);
        Bus::assertNothingDispatched();
    }

    public function test_new_device_cannot_be_created_in_an_unpermitted_account(): void
    {
        $this->permissions('device_add');
        $this->postJson('/api/devices', ['device_address' => '00:00:00:00:00:09', 'domain_uuid' => self::B])->assertForbidden();
        $this->permissions('device_add', 'device_all', 'device_domain');
        $this->postJson('/api/devices', ['device_address' => '00:00:00:00:00:09', 'domain_uuid' => self::MISSING])->assertUnprocessable();
        $this->postJson('/api/devices', ['device_address' => '00:00:00:00:00:09', 'domain_uuid' => self::B])->assertCreated();
        $this->assertDatabaseHas('v_devices', ['device_address' => '000000000009', 'domain_uuid' => self::B]);
    }

    public function test_assignment_and_unassignment_require_matching_device_and_extension_accounts(): void
    {
        $this->permissions('extension_device_assign', 'extension_device_unassign');
        $line = ['line_type_id' => 'line', 'line_number' => 1, 'auth_id' => '100', 'user_id' => '100'];
        $this->postJson('/api/devices/assign', ['device_address' => formatMacAddress($this->mac(self::FOREIGN)), 'device_lines' => [$line]])->assertNotFound();
        $this->postJson('/api/devices/bulk-unassign', ['items' => [self::LOCAL], 'extension_uuid' => self::SHARED])->assertNotFound();
        $this->unchanged();
        $this->permissions('extension_device_assign', 'extension_device_unassign', 'device_all');
        $this->postJson('/api/devices/assign', ['device_address' => formatMacAddress($this->mac(self::SHARED)), 'device_lines' => [$line]])->assertOk();
        $this->postJson('/api/devices/bulk-unassign', ['items' => [self::SHARED], 'extension_uuid' => self::SHARED])->assertOk();
        $this->assertDatabaseMissing('v_device_lines', ['device_uuid' => self::SHARED]);
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::LOCAL]);
    }

    public function test_v1_create_and_update_use_the_route_account_for_templates_and_profiles(): void
    {
        $this->withSession(['domain_uuid' => null]);
        $this->withToken('fixture');
        $auth = $this->mock(\App\Services\Auth\PermissionService::class);
        $auth->shouldReceive('userCanAccessDomain')->with(Mockery::any(), self::B)->andReturn(true);
        $auth->shouldReceive('userHasPermission')->with(Mockery::any(), Mockery::any(), self::B)->andReturn(true);
        DB::table('v_device_profiles')->insert(['device_profile_uuid' => self::GLOBAL, 'domain_uuid' => null]);
        $url = '/api/v1/domains/'.self::B.'/devices';
        $create = ['device_address' => '00:00:00:00:00:09', 'domain_uuid' => self::A];

        $invalid = ['device_template' => self::LOCAL, 'device_profile_uuid' => self::LOCAL];
        $this->postJson($url, $invalid + $create)->assertUnprocessable()->assertJsonValidationErrors(['device_template_uuid', 'device_profile_uuid']);
        $this->patchJson($url.'/'.self::SHARED, $invalid)->assertUnprocessable()->assertJsonValidationErrors(['device_template_uuid', 'device_profile_uuid']);
        $this->assertDatabaseCount('v_devices', 3);

        $this->postJson($url, ['device_template_uuid' => self::SHARED, 'device_profile_uuid' => self::SHARED] + $create)->assertCreated();
        $this->assertDatabaseHas('v_devices', ['domain_uuid' => self::B, 'device_template_uuid' => self::SHARED, 'device_profile_uuid' => self::SHARED]);
        $this->patchJson($url.'/'.self::SHARED, ['device_template' => self::GLOBAL, 'device_profile_uuid' => self::GLOBAL])->assertOk();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::SHARED, 'device_template_uuid' => self::GLOBAL, 'device_profile_uuid' => self::GLOBAL]);
    }

    public function test_preview_and_handset_resolve_only_account_or_shared_templates_and_profiles(): void
    {
        $this->permissions('device_provisioning_preview');
        $devices = DB::table('v_devices')->where('device_uuid', self::LOCAL);
        $devices->update(['device_vendor' => 'fixture', 'device_template_uuid' => self::FOREIGN]);
        $url = '/api/devices/'.self::LOCAL.'/provisioning-preview';
        $serve = function (string $method) {
            $request = Request::create('/prov/'.$this->mac(self::LOCAL).'.cfg', $method);
            $request->attributes->set('prov.device', Devices::findOrFail(self::LOCAL));
            return app(ProvisioningController::class)->serve($request);
        };
        $this->getJson($url)->assertNotFound();
        $this->assertSame(404, $serve('GET')->getStatusCode());
        $devices->update(['device_template' => 'fixture/global']);
        $this->assertSame(self::GLOBAL, $serve('HEAD')->headers->get('X-Prov-Template'));
        DB::table('provisioning_templates')->where('template_uuid', self::FOREIGN)->update(['type' => 'default', 'name' => 'private']);
        $devices->update(['device_template' => 'fixture/private']);
        $this->assertSame(404, $serve('HEAD')->getStatusCode());

        $devices->update(['device_template_uuid' => self::LOCAL, 'device_profile_uuid' => self::FOREIGN, 'device_key_template_uuid' => self::FOREIGN]);
        $device = Devices::findOrFail(self::LOCAL);
        (new \ReflectionMethod(ProvisioningController::class, 'buildTemplateVars'))->invoke(app(ProvisioningController::class), $device);
        $this->assertNull($device->profile);
        $this->assertNull($device->keyTemplate);
    }

    public function test_preview_rejects_foreign_and_ownerless_devices_before_rendering(): void
    {
        $this->permissions('device_provisioning_preview');
        $this->getJson('/api/devices/'.self::FOREIGN.'/provisioning-preview')->assertNotFound()->assertJsonValidationErrors('device');
        $this->permissions('device_provisioning_preview', 'device_all');
        DB::table('v_devices')->where('device_uuid', self::FOREIGN)->update(['domain_uuid' => null]);
        $this->getJson('/api/devices/'.self::FOREIGN.'/provisioning-preview')->assertNotFound()->assertJsonValidationErrors('device');
        // A permitted device gets past ownership checks to the missing-template response; no rendering.
        $this->getJson('/api/devices/'.self::SHARED.'/provisioning-preview')->assertNotFound()->assertJsonValidationErrors('template');
    }

    public function test_ht8x1_uses_tcp_for_an_unset_line_transport_and_honors_explicit_udp(): void
    {
        $device = Devices::findOrFail(self::LOCAL);
        $device->device_vendor = 'grandstream';
        $template = new \App\Models\ProvisioningTemplate(['vendor' => 'grandstream', 'name' => 'HT8x1']);
        $build = new \ReflectionMethod(ProvisioningController::class, 'buildTemplateVars');
        $controller = app(ProvisioningController::class);
        $this->assertSame('TCP', $build->invoke($controller, $device, $template)['lines'][1]['sip_transport']);
        $template->name = 'HT80x';
        $this->assertSame('UDP', $build->invoke($controller, $device, $template)['lines'][1]['sip_transport']);
        DB::table('v_device_lines')->where('device_uuid', self::LOCAL)->update(['sip_transport' => 'UDP']);
        $device->unsetRelation('lines');
        $template->name = 'HT8x1';
        $this->assertSame('UDP', $build->invoke($controller, $device, $template)['lines'][1]['sip_transport']);
    }

    public function test_preview_masks_configured_credentials_before_rendering_and_handset_receives_originals(): void
    {
        $this->permissions('device_provisioning_preview');
        $compiled = sys_get_temp_dir().'/fspbx-preview-'.Str::uuid();
        config(['view.compiled' => $compiled, 'app.url' => 'https://pbx.example.test']);
        DB::table('v_devices')->where('device_uuid', self::LOCAL)->update([
            'device_vendor' => 'sangoma', 'device_template_uuid' => self::LOCAL,
        ]);
        DB::table('provisioning_templates')->where('template_uuid', self::LOCAL)->update([
            'content' => <<<'BLADE'
                extension={{ $lines[1]['auth_id'] }}
                sip={{ $lines[1]['password'] }}
                admin={{ $settings['admin_password'] }}
                blank={{ $settings['user_password'] }}
                literal=factory-default
                fallback={{ $settings['missing_password'] ?? 'template-default' }}
                url={{ $sangoma_key_file_urls['contacts'] }}
                BLADE,
        ]);
        foreach (['http_auth_username' => 'user@example', 'http_auth_password' => 'pass:/@', 'admin_password' => '0', 'user_password' => ''] as $name => $value) {
            DB::table('v_domain_settings')->insert([
                'domain_uuid' => self::A, 'domain_setting_category' => 'provision',
                'domain_setting_subcategory' => $name, 'domain_setting_name' => 'text',
                'domain_setting_enabled' => 'true', 'domain_setting_value' => $value,
            ]);
        }

        try {
            $file = $this->getJson('/api/devices/'.self::LOCAL.'/provisioning-preview')->assertOk()->json('files.0');
            $this->assertSame(strlen($file['content']), $file['bytes']);
            $expected = "extension=100\nsip=[REDACTED]\nadmin=[REDACTED]\nblank=\nliteral=factory-default\nfallback=template-default\n";
            $path = 'pbx.example.test/prov/'.$this->mac(self::LOCAL).'-contacts.xml';
            $this->assertSame($expected.'url=https://[REDACTED]@'.$path."\n", $file['content']);
            $this->assertDatabaseHas('v_devices', ['device_uuid' => self::LOCAL, 'device_provisioned_date' => null]);

            $request = Request::create('/prov/'.$this->mac(self::LOCAL).'.cfg');
            $request->attributes->set('prov.device', Devices::findOrFail(self::LOCAL));
            $body = app(ProvisioningController::class)->serve($request)->getContent();
            $this->assertStringContainsString("sip=fixture-secret\nadmin=0\n", $body);
            $this->assertStringContainsString('url=https://user%40example:pass%3A%2F%40@'.$path, $body);
        } finally {
            File::deleteDirectory($compiled);
        }
    }

    public function test_restart_dispatches_only_authorized_devices_after_validation(): void
    {
        $this->permissions('device_edit');
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('getAllSipRegistrations')->once()->andReturn([
            ['user' => '100@'.self::A.'.invalid'], ['user' => '100@'.self::C.'.invalid'],
        ]);
        $action = Mockery::mock(DeviceActionService::class);
        $action->shouldReceive('scheduleDeviceActions')->once()
            ->with(Mockery::on(fn ($rows) => $rows->count() === 1 && $rows[0]['user'] === '100@'.self::A.'.invalid'), 'reboot')->andReturn(1);
        $this->instance(FreeswitchEslService::class, $esl);
        $this->instance(DeviceActionService::class, $action);
        $this->postJson('/api/devices/restart', ['devices' => [self::LOCAL]])->assertCreated();
    }

    public function test_device_commands_do_not_follow_a_foreign_sip_server_on_an_owned_device(): void
    {
        $this->permissions('device_edit');
        DB::table('v_device_lines')->where('device_uuid', self::LOCAL)->update(['server_address' => self::C.'.invalid']);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('getAllSipRegistrations')->once()->andReturn([['user' => '100@'.self::C.'.invalid']]);
        $action = Mockery::mock(DeviceActionService::class);
        $action->shouldReceive('scheduleDeviceActions')->once()
            ->with(Mockery::on(fn ($rows) => $rows->isEmpty()), 'provision')->andReturn(0);
        $this->instance(FreeswitchEslService::class, $esl);
        $this->instance(DeviceActionService::class, $action);
        $this->postJson('/api/devices/sync', ['devices' => [self::LOCAL]])->assertCreated();
    }

    public function test_update_checks_the_bound_devices_account_before_payload_validation(): void
    {
        $this->permissions('device_edit');
        $this->putJson('/api/devices/'.self::SHARED)->assertNotFound();
        $this->putJson('/api/devices/'.self::MISSING)->assertNotFound();
        $this->putJson('/api/devices/not-a-uuid')->assertNotFound();
        $this->putJson('/api/devices/'.self::LOCAL)->assertUnprocessable()->assertJsonValidationErrors('device_address');

        DB::table('v_devices')->insert(['device_uuid' => self::GLOBAL, 'device_address' => $this->mac(self::GLOBAL)]);
        $this->permissions('device_edit', 'device_all');
        $this->putJson('/api/devices/'.self::GLOBAL, $this->editPayload(self::GLOBAL))->assertNotFound();
        $this->assertDatabaseHas('v_devices', ['device_uuid' => self::GLOBAL, 'domain_uuid' => null]);
        $this->unchanged();
    }

    public function test_item_options_validate_modes_and_preserve_both_uuid_aliases(): void
    {
        $this->permissions('device_add', 'device_edit');
        foreach ([
            ['mode' => 'update'], ['mode' => 'invalid'], ['item_uuid' => ['invalid']],
            ['mode' => 'bulk_update', 'item_uuid' => self::LOCAL],
            ['itemUuid' => self::LOCAL, 'item_uuid' => self::SHARED],
        ] as $payload) {
            $this->postJson('/api/devices/item-options', $payload)->assertUnprocessable();
        }
        foreach (['itemUuid', 'item_uuid'] as $field) {
            $this->postJson('/api/devices/item-options', [$field => self::LOCAL])
                ->assertOk()->assertJsonPath('item.device_uuid', self::LOCAL);
        }
        foreach (['create', 'bulk_update'] as $mode) {
            $this->postJson('/api/devices/item-options', ['mode' => $mode])->assertOk();
        }
        $this->permissions('device_add');
        $this->postJson('/api/devices/item-options', ['mode' => 'bulk_update'])->assertForbidden();
        $this->permissions('device_edit');
        $this->postJson('/api/devices/item-options', ['mode' => 'create'])->assertForbidden();
        $this->unchanged();
    }

    public function test_bulk_requests_validate_device_lists_before_any_effect(): void
    {
        $this->permissions('device_edit', 'device_delete', 'extension_device_unassign');
        foreach ([
            ['bulk-update', 'items', ['device_description' => 'changed']],
            ['bulk-delete', 'items', []],
            ['bulk-unassign', 'items', ['extension_uuid' => self::LOCAL]],
            ['restart', 'devices', []], ['sync', 'devices', []],
        ] as [$action, $field, $extra]) {
            foreach ([null, [], self::LOCAL, ['invalid'], [self::LOCAL, self::LOCAL], [self::LOCAL, null]] as $ids) {
                $this->postJson('/api/devices/'.$action, [$field => $ids] + $extra)->assertUnprocessable();
                $this->unchanged();
            }
        }
        foreach ([null, 'invalid', ['invalid']] as $extension) {
            $this->postJson('/api/devices/bulk-unassign', ['items' => [self::LOCAL], 'extension_uuid' => $extension])
                ->assertUnprocessable()->assertJsonValidationErrors('extension_uuid');
        }
        $this->unchanged();
    }

    public function test_bulk_update_requires_device_fields_or_line_settings(): void
    {
        $this->permissions('device_edit', 'device_line_edit');
        foreach ([[], ['unknown_field' => 'value'], ['resync_devices' => true], ['line_scope' => 'all'], ['line_sip_port' => null]] as $changes) {
            $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL]] + $changes)
                ->assertUnprocessable()->assertJsonValidationErrors('input');
            $this->unchanged();
        }
        $this->postJson('/api/devices/bulk-update', [
            'items' => [self::LOCAL], 'line_scope' => 'list', 'line_numbers' => '1-2', 'line_sip_port' => 5070,
        ])->assertOk();
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::LOCAL, 'sip_port' => '5070']);
        $this->assertDatabaseHas('v_device_lines', ['device_uuid' => self::SHARED, 'sip_port' => '5060']);
        Bus::assertNothingDispatched();
    }

    public function test_domain_inputs_are_validated_on_each_device_form(): void
    {
        $this->permissions('device_add', 'device_edit', 'device_all', 'device_domain', 'extension_device_assign');
        foreach ([null, '', 'invalid', ['invalid'], self::MISSING] as $domain) {
            $this->postJson('/api/devices', ['device_address' => '00:00:00:00:00:09', 'domain_uuid' => $domain])
                ->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->putJson('/api/devices/'.self::LOCAL, $this->editPayload(self::LOCAL) + ['domain_uuid' => $domain])
                ->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->postJson('/api/devices/bulk-update', ['items' => [self::LOCAL], 'domain_uuid' => $domain])
                ->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->postJson('/api/devices/item-options', ['item_uuid' => self::LOCAL, 'domain_uuid' => $domain])
                ->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->postJson('/api/devices/assign', $this->editPayload(self::LOCAL) + ['domain_uuid' => $domain])
                ->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->unchanged();
        }
    }

    public function test_extension_assignment_keeps_its_own_permission_and_cannot_move_devices(): void
    {
        $payload = $this->editPayload(self::LOCAL);
        $this->permissions('device_edit');
        $this->postJson('/api/devices/assign', $payload)->assertForbidden();
        $this->permissions('extension_device_assign', 'device_all', 'device_domain');
        $this->putJson('/api/devices/'.self::LOCAL, $payload)->assertForbidden();
        $this->postJson('/api/devices/assign', $payload + ['domain_uuid' => self::B])->assertForbidden();
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

    private function mac(string $id): string
    {
        return '00000000000'.substr($id, 0, 1);
    }

    private function editPayload(string $id): array
    {
        return ['device_address' => formatMacAddress($this->mac($id)), 'device_description' => 'changed'];
    }

    private function unchanged(): void
    {
        foreach ([self::LOCAL => self::A, self::SHARED => self::B, self::FOREIGN => self::C] as $id => $domain) {
            $this->assertDatabaseHas('v_devices', ['device_uuid' => $id, 'domain_uuid' => $domain, 'device_description' => 'original']);
            $this->assertDatabaseHas('v_device_lines', ['device_uuid' => $id, 'domain_uuid' => $domain, 'sip_port' => '5060']);
        }
        Bus::assertNothingDispatched();
    }
}
