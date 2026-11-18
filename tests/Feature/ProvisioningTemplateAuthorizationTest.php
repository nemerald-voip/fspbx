<?php

namespace Tests\Feature;

use App\Models\{ProvisioningTemplate, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Http, Process, Schema};
use ReflectionMethod;
use Tests\TestCase;

class ProvisioningTemplateAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const DEFAULT = '33333333-3333-4333-8333-333333333333';
    private const SHARED = '44444444-4444-4444-8444-444444444444';
    private const MISSING = '55555555-5555-4555-8555-555555555555';
    private const URL = '/api/provisioning-templates';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'template_authorization',
            'database.connections.template_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('template_authorization');
        Http::preventStrayRequests();
        Bus::fake();
        Process::fake();

        $model = new ProvisioningTemplate;
        $this->table('provisioning_templates', [$model->getKeyName(), ...$model->getFillable(), 'created_at', 'updated_at']);
        $this->table('v_device_vendors', ['device_vendor_uuid', 'name', 'enabled']);
        DB::table('v_device_vendors')->insert(['device_vendor_uuid' => self::LOCAL, 'name' => 'fixture', 'enabled' => 'true']);
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B, self::DEFAULT => null, self::SHARED => null] as $uuid => $domain) {
            DB::table('provisioning_templates')->insert([
                'template_uuid' => $uuid, 'domain_uuid' => $domain, 'vendor' => 'fixture', 'name' => $uuid,
                'type' => $uuid === self::DEFAULT ? 'default' : 'custom', 'content' => 'original',
                'revision' => $uuid === self::DEFAULT ? '0' : '1', 'version' => '1.0.0',
            ]);
        }
        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::A, 'domain_name' => 'a.invalid', 'domain.language.code' => 'en-us', 'permissions' => []]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('template_authorization');
        parent::tearDown();
    }

    public static function endpoints(): array
    {
        return [
            ['GET', '', 'view', []],
            ['POST', '/content', 'view', ['template_uuid' => self::LOCAL]],
            ['POST', '/item-options', 'view', ['item_uuid' => self::LOCAL]],
            ['POST', '/item-options', 'create', []],
            ['POST', '', 'create', []],
            ['PUT', '/'.self::LOCAL, 'update', []],
            ['POST', '/bulk-delete', 'delete', ['items' => [self::LOCAL]]],
        ];
    }

    /** @dataProvider endpoints */
    public function test_every_endpoint_requires_its_action_permission(string $method, string $path, string $action, array $data): void
    {
        $this->permissions(...array_diff(['view', 'create', 'update', 'delete', 'manage_global'], [$action]));
        $this->json($method, self::URL.$path, $data)->assertForbidden();
        $this->unchanged();
    }

    /** @dataProvider endpoints */
    public function test_missing_or_invalid_account_context_fails_closed(string $method, string $path, string $action, array $data): void
    {
        $this->permissions('view', 'create', 'update', 'delete', 'manage_global');
        foreach ([null, '', 'invalid'] as $domain) {
            $this->withSession(['domain_uuid' => $domain]);
            $this->json($method, self::URL.$path, $data)->assertForbidden();
        }
        $this->unchanged();
    }

    public function test_visibility_is_current_account_plus_shared_even_with_global_management(): void
    {
        foreach ([['view'], ['view', 'manage_global']] as $permissions) {
            $this->permissions(...$permissions);
            $this->getJson(self::URL)->assertOk()->assertJsonCount(3, 'data')
                ->assertJsonMissing(['template_uuid' => self::FOREIGN]);
            $this->getJson(self::URL.'?filter[domain_uuid]='.self::B)->assertOk()
                ->assertJsonMissing(['template_uuid' => self::FOREIGN]);
            $this->getJson(self::URL.'?domain_uuid='.self::B.'&showGlobal=true')->assertOk()
                ->assertJsonCount(3, 'data')->assertJsonMissing(['template_uuid' => self::FOREIGN]);
        }
    }

    public function test_foreign_template_cannot_be_read_modified_or_deleted_even_by_global_manager(): void
    {
        $this->permissions('view', 'create', 'update', 'delete', 'manage_global');
        $this->withSession(['user.groups' => [(object) ['group_name' => 'superadmin', 'group_level' => 80]]]);
        foreach ([self::FOREIGN, self::MISSING] as $uuid) {
            $this->postJson(self::URL.'/content', ['template_uuid' => $uuid])->assertNotFound();
            $this->postJson(self::URL.'/item-options', ['item_uuid' => $uuid, 'mode' => 'create'])->assertNotFound();
            $this->putJson(self::URL.'/'.$uuid, $this->payload())->assertNotFound();
            $this->postJson(self::URL.'/bulk-delete', ['items' => [$uuid]])->assertNotFound();
        }
        $this->unchanged();
    }

    public function test_read_only_access_and_shared_base_templates_remain_available(): void
    {
        $this->permissions('view');
        foreach ([self::LOCAL, self::DEFAULT, self::SHARED] as $uuid) {
            $this->postJson(self::URL.'/content', ['template_uuid' => $uuid])->assertOk()->assertJsonPath('item.content', 'original');
            $this->postJson(self::URL.'/item-options', ['item_uuid' => $uuid])->assertOk()
                ->assertJsonPath('read_only', true)->assertJsonMissingPath('routes.update_route')
                ->assertJsonPath('default_templates.0.items.0.value', self::DEFAULT);
        }
        $this->permissions('view', 'create');
        $this->postJson(self::URL.'/item-options')->assertOk()->assertJsonPath('read_only', false)
            ->assertJsonPath('permissions.provisioning_templates_manage_global', false);
    }

    public function test_shipped_defaults_are_immutable_even_with_all_permissions(): void
    {
        $this->permissions('view', 'create', 'update', 'delete', 'manage_global');
        $this->putJson(self::URL.'/'.self::DEFAULT, $this->payload())->assertNotFound();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::DEFAULT]])->assertNotFound();
        $this->postJson(self::URL, $this->payload(['type' => 'default']))->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload(['type' => 'default']))->assertUnprocessable();
        $this->unchanged();
    }

    public function test_current_account_template_can_be_created_updated_and_deleted(): void
    {
        $this->permissions('view', 'create', 'update', 'delete');
        $response = $this->postJson(self::URL, $this->payload(['base_template' => self::DEFAULT, 'base_version' => '1.0.0']))->assertCreated();
        $uuid = $response->json('item.template_uuid');
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => $uuid, 'domain_uuid' => self::A, 'type' => 'custom', 'revision' => 1]);
        $this->putJson(self::URL.'/'.$uuid, $this->payload(['content' => 'updated']))->assertOk();
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => $uuid, 'domain_uuid' => self::A, 'content' => 'updated', 'revision' => 2, 'checksum' => hash('sha256', 'updated')]);
        $this->postJson(self::URL.'/bulk-delete', ['items' => [$uuid, self::LOCAL]])->assertOk();
        $this->assertDatabaseMissing('provisioning_templates', ['template_uuid' => $uuid]);
        $this->assertDatabaseMissing('provisioning_templates', ['template_uuid' => self::LOCAL]);
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => self::FOREIGN, 'content' => 'original']);
    }

    public function test_account_parameters_cannot_reassign_templates(): void
    {
        $this->permissions('view', 'create', 'update', 'manage_global');
        $data = $this->payload(['domain_uuid' => self::B]);
        $this->postJson(self::URL, $data)->assertForbidden();
        $this->putJson(self::URL.'/'.self::LOCAL, $data)->assertForbidden();
        $this->unchanged();
    }

    public function test_ordinary_action_permissions_do_not_allow_shared_mutations(): void
    {
        $this->permissions('view', 'create', 'update', 'delete');
        $this->postJson(self::URL, $this->payload(['global' => true]))->assertForbidden();
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload(['global' => true]))->assertForbidden();
        $this->putJson(self::URL.'/'.self::SHARED, $this->payload(['global' => false]))->assertNotFound();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::SHARED]])->assertNotFound();
        $this->unchanged();
    }

    public function test_authorized_shared_custom_management_and_omitted_scope_preservation(): void
    {
        $this->permissions('view', 'create', 'update', 'delete', 'manage_global');
        $response = $this->postJson(self::URL, $this->payload(['global' => true]))->assertCreated();
        $uuid = $response->json('item.template_uuid');
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => $uuid, 'domain_uuid' => null, 'type' => 'custom']);
        $this->putJson(self::URL.'/'.$uuid, $this->payload(['content' => 'still shared']))->assertOk();
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => $uuid, 'domain_uuid' => null, 'content' => 'still shared']);
        $this->putJson(self::URL.'/'.$uuid, $this->payload(['global' => false]))->assertOk();
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => $uuid, 'domain_uuid' => self::A]);
        $this->putJson(self::URL.'/'.$uuid, $this->payload(['global' => true]))->assertOk();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [$uuid, self::SHARED]])->assertOk();
        $this->assertDatabaseMissing('provisioning_templates', ['template_uuid' => $uuid]);
        $this->assertDatabaseMissing('provisioning_templates', ['template_uuid' => self::SHARED]);
    }

    public function test_mixed_bulk_deletion_has_no_partial_effects(): void
    {
        $this->permissions('view', 'delete');
        foreach ([self::FOREIGN, self::DEFAULT, self::SHARED, self::MISSING] as $uuid) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL, $uuid]])->assertNotFound();
            $this->unchanged();
        }
        foreach ([[], 'invalid', ['invalid'], [self::LOCAL, self::LOCAL]] as $items) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => $items])->assertUnprocessable();
            $this->unchanged();
        }
    }

    public function test_invalid_identifiers_and_payloads_are_validation_errors(): void
    {
        $this->permissions('view', 'create', 'update');
        $this->postJson(self::URL.'/content', ['template_uuid' => 'invalid'])->assertUnprocessable();
        $this->postJson(self::URL.'/item-options', ['item_uuid' => ['invalid']])->assertUnprocessable();
        $this->postJson(self::URL, $this->payload(['global' => 'false']))->assertUnprocessable()->assertJsonValidationErrors('global');
        $this->postJson(self::URL, $this->payload(['base_template' => self::FOREIGN]))->assertUnprocessable()->assertJsonValidationErrors('base_template');
        $this->postJson(self::URL, $this->payload(['name' => self::LOCAL]))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->unchanged();
    }

    public function test_row_actions_match_server_permissions(): void
    {
        $this->permissions('view', 'update', 'delete');
        $rows = collect($this->getJson(self::URL)->assertOk()->json('data'))->keyBy('template_uuid');
        $this->assertTrue($rows[self::LOCAL]['can_update']);
        $this->assertTrue($rows[self::LOCAL]['can_delete']);
        foreach ([self::DEFAULT, self::SHARED] as $uuid) {
            $this->assertFalse($rows[$uuid]['can_update']);
            $this->assertFalse($rows[$uuid]['can_delete']);
        }
        $this->permissions('view', 'update', 'delete', 'manage_global');
        $rows = collect($this->getJson(self::URL)->assertOk()->json('data'))->keyBy('template_uuid');
        $this->assertTrue($rows[self::SHARED]['can_update']);
        $this->assertTrue($rows[self::SHARED]['can_delete']);
        $this->assertFalse($rows[self::DEFAULT]['can_update']);
    }

    public function test_permission_defaults_are_superadmin_only_and_preserve_existing_decisions(): void
    {
        $this->table('v_groups', ['group_uuid', 'group_name']);
        $this->table('v_permissions', ['permission_uuid', 'application_name', 'permission_name', 'insert_date']);
        $this->table('v_group_permissions', ['group_permission_uuid', 'group_uuid', 'group_name', 'permission_name', 'permission_assigned', 'permission_protected', 'insert_date']);
        foreach (['superadmin', 'admin', 'user', 'multi-site admin'] as $group) {
            DB::table('v_groups')->insert(['group_uuid' => $group, 'group_name' => $group]);
        }
        $seeder = new DatabaseSeeder;
        foreach (['createPermissions', 'createGroupPermissions'] as $method) {
            (new ReflectionMethod($seeder, $method))->invoke($seeder);
        }
        foreach (['view', 'create', 'update', 'delete', 'manage_global'] as $action) {
            $permission = 'provisioning_templates_'.$action;
            $this->assertDatabaseHas('v_permissions', ['permission_name' => $permission]);
            $this->assertSame(['superadmin' => 'true'], DB::table('v_group_permissions')->where('permission_name', $permission)
                ->pluck('permission_assigned', 'group_name')->all());
        }
        DB::table('v_group_permissions')->where('permission_name', 'provisioning_templates_manage_global')->update(['permission_assigned' => 'false']);
        (new ReflectionMethod($seeder, 'createGroupPermissions'))->invoke($seeder);
        $this->assertSame(['superadmin' => 'false'], DB::table('v_group_permissions')
            ->where('permission_name', 'provisioning_templates_manage_global')->pluck('permission_assigned', 'group_name')->all());
    }

    private function table(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['vendor' => 'fixture', 'name' => 'new custom', 'type' => 'custom', 'content' => 'saved content'];
    }

    private function permissions(string ...$actions): void
    {
        $this->withSession(['permissions' => array_map(fn ($action) => (object) ['permission_name' => 'provisioning_templates_'.$action], $actions)]);
    }

    private function unchanged(): void
    {
        $this->assertSame(4, DB::table('provisioning_templates')->count());
        $this->assertSame(0, DB::table('provisioning_templates')->where('content', '!=', 'original')->count());
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => self::LOCAL, 'domain_uuid' => self::A]);
        $this->assertDatabaseHas('provisioning_templates', ['template_uuid' => self::FOREIGN, 'domain_uuid' => self::B]);
    }
}
