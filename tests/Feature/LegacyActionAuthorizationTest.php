<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyActionAuthorizationTest extends TestCase
{
    private const ACCOUNT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const OTHER_ACCOUNT = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const GATEWAY = '33333333-3333-4333-8333-333333333333';
    private const MISSING = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'legacy_action_test',
            'database.connections.legacy_action_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'logging.default' => 'null',
        ]);
        DB::purge('legacy_action_test');
        Http::preventStrayRequests();
        $this->withSession([
            'domain_uuid' => self::ACCOUNT,
            'domain.language.code' => 'en-us',
            'permissions' => [],
            'domains' => null,
        ]);

        $this->table('v_ivr_menus', ['ivr_menu_uuid', 'domain_uuid', 'ivr_menu_greet_long']);
        $this->table('v_fax_queue', ['fax_queue_uuid', 'domain_uuid']);
        $this->table('v_fax_files', [
            'fax_file_uuid', 'domain_uuid', 'fax_uuid', 'fax_caller_id_number', 'fax_destination', 'fax_mode',
        ]);
        $this->table('v_fax_logs', [
            'fax_log_uuid', 'domain_uuid', 'fax_uuid', 'fax_success', 'fax_result_code', 'fax_result_text',
            'fax_file', 'source', 'destination', 'fax_local_station_id', 'fax_ecm_used', 'fax_bad_rows',
            'fax_transfer_rate', 'fax_duration', 'fax_uri', 'fax_date', 'fax_epoch',
            'fax_document_transferred_pages', 'fax_document_total_pages', 'outbound_fax_uuid',
            'outbound_fax_attempt_uuid',
        ]);
        $this->table('v_domains', ['domain_uuid', 'domain_name', 'domain_description']);
        $this->table('v_domain_settings', ['domain_uuid', 'domain_setting_subcategory', 'domain_setting_enabled', 'domain_setting_value']);
        $this->table('v_default_settings', ['default_setting_subcategory', 'default_setting_enabled', 'default_setting_value']);
        $this->table('v_fax', ['fax_uuid', 'fax_caller_id_number']);
        $this->table('payment_gateways', ['uuid', 'slug', 'name', 'is_enabled', 'created_at', 'updated_at']);
        $this->table('gateway_settings', ['uuid', 'gateway_uuid', 'setting_key', 'setting_value', 'created_at', 'updated_at']);

        foreach ([self::LOCAL => self::ACCOUNT, self::FOREIGN => self::OTHER_ACCOUNT] as $id => $account) {
            DB::table('v_ivr_menus')->insert([
                'ivr_menu_uuid' => $id, 'domain_uuid' => $account, 'ivr_menu_greet_long' => 'original.wav',
            ]);
            foreach (['v_fax_queue' => 'fax_queue_uuid', 'v_fax_files' => 'fax_file_uuid', 'v_fax_logs' => 'fax_log_uuid'] as $table => $key) {
                DB::table($table)->insert([$key => $id, 'domain_uuid' => $account]);
            }
        }

        DB::table('payment_gateways')->insert([
            'uuid' => self::GATEWAY, 'slug' => 'stripe', 'name' => 'Stripe', 'is_enabled' => '1',
        ]);
        foreach (['sandbox' => 'true', 'sandbox_secret_key' => 'sk_test_FixtureOnly', 'webhook_secret' => 'whsec_FixtureOnly'] as $key => $value) {
            DB::table('gateway_settings')->insert([
                'uuid' => $key, 'gateway_uuid' => self::GATEWAY, 'setting_key' => $key, 'setting_value' => $value,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('legacy_action_test');
        parent::tearDown();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'fax logs' => ['GET', '/api/fax/log/data'],
            'fax log selection' => ['POST', '/api/fax/log/select-all'],
            'gateway settings' => ['GET', '/api/system-settings/payment_gateways'],
            'gateway update' => ['PUT', '/api/gateways'],
            'gateway deactivate' => ['POST', '/api/gateways/deactivate'],
            'gateway test' => ['POST', '/api/gateways/test'],
            'IVR greeting' => ['POST', '/api/virtual-receptionists/greeting/apply'],
        ];
    }

    /** @dataProvider protectedEndpoints */
    public function test_guest_requests_are_rejected(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertRedirect(route('login'));
    }

    /** @dataProvider protectedEndpoints */
    public function test_missing_permission_is_rejected_before_validation_or_database_access(string $method, string $uri): void
    {
        $this->signIn('account_settings_list_view', 'ivr_menu_view', 'fax_log_delete');
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->json($method, $uri, [
            'uuid' => self::GATEWAY, 'status' => 'false',
            'ivr' => self::FOREIGN, 'file_name' => 'changed.wav', 'mode' => 'test',
        ])->assertForbidden();

        $this->assertSame([], DB::getQueryLog());
        $response->assertDontSee('sk_test_FixtureOnly')->assertDontSee('whsec_FixtureOnly');
        $this->assertDatabaseHas('payment_gateways', ['uuid' => self::GATEWAY, 'is_enabled' => '1']);
        $this->assertDatabaseHas('v_ivr_menus', ['ivr_menu_uuid' => self::FOREIGN, 'ivr_menu_greet_long' => 'original.wav']);
    }

    public static function retiredFaxEndpoints(): array
    {
        return [['deleteSentFax'], ['deleteReceivedFax'], ['deleteFaxLog']];
    }

    /** @dataProvider retiredFaxEndpoints */
    public function test_retired_fax_routes_cannot_delete_local_or_foreign_records(string $action): void
    {
        $this->signIn('fax_inbox_delete', 'fax_sent_delete', 'fax_log_delete');

        foreach ([self::LOCAL, self::FOREIGN] as $id) {
            $this->deleteJson('/api/faxes/'.$action.'/'.$id)->assertNotFound();
        }

        foreach (['v_fax_queue', 'v_fax_files', 'v_fax_logs'] as $table) {
            $this->assertDatabaseCount($table, 2);
        }
    }

    public static function accountEndpoints(): array
    {
        return [
            ['GET', '/api/fax/log/data', 'fax_log_view'],
            ['POST', '/api/fax/log/select-all', 'fax_log_view'],
            ['POST', '/api/virtual-receptionists/greeting/apply', 'ivr_menu_edit'],
        ];
    }

    /** @dataProvider accountEndpoints */
    public function test_account_actions_require_a_current_account(string $method, string $uri, string $permission): void
    {
        $this->signIn($permission);
        $this->withSession(['domain_uuid' => null]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->json($method, $uri)->assertForbidden();
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_authorized_fax_log_reads_preserve_account_scoping(): void
    {
        $this->signIn('fax_log_view');

        foreach ([[], ['filter' => ['domain_uuid' => self::OTHER_ACCOUNT]], ['filter' => ['domain_uuid' => 'all']]] as $filters) {
            $this->json('GET', '/api/fax/log/data', $filters)
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.fax_log_uuid', self::LOCAL);
            $this->postJson('/api/fax/log/select-all', $filters)
                ->assertOk()->assertJsonPath('items', [self::LOCAL]);
        }

        $this->withSession(['domains' => [
            ['domain_uuid' => self::ACCOUNT], ['domain_uuid' => self::OTHER_ACCOUNT],
        ]]);
        $this->json('GET', '/api/fax/log/data', ['filter' => ['domain_uuid' => 'all']])
            ->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/fax/log/select-all', ['filter' => ['domain_uuid' => self::OTHER_ACCOUNT]])
            ->assertOk()->assertJsonPath('items', [self::FOREIGN]);
    }

    public function test_authorized_greeting_update_only_changes_the_current_accounts_ivr(): void
    {
        $this->signIn('ivr_menu_edit');

        $this->postJson('/api/virtual-receptionists/greeting/apply', [
            'ivr' => self::LOCAL, 'file_name' => 'ai_generated_greeting.wav',
        ])->assertOk()->assertJsonPath('success', true);

        foreach ([self::FOREIGN, self::MISSING] as $id) {
            $this->postJson('/api/virtual-receptionists/greeting/apply', [
                'ivr' => $id, 'file_name' => 'changed.wav', 'domain_uuid' => self::OTHER_ACCOUNT,
            ])->assertNotFound();
        }

        $this->assertDatabaseHas('v_ivr_menus', ['ivr_menu_uuid' => self::LOCAL, 'ivr_menu_greet_long' => 'ai_generated_greeting.wav']);
        $this->assertDatabaseHas('v_ivr_menus', ['ivr_menu_uuid' => self::FOREIGN, 'ivr_menu_greet_long' => 'original.wav']);
    }

    public function test_greeting_input_is_validated_before_saving(): void
    {
        $this->signIn('ivr_menu_edit');

        $this->postJson('/api/virtual-receptionists/greeting/apply', [
            'ivr' => 'not-a-uuid', 'file_name' => ['invalid'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ivr', 'file_name']);
        $this->postJson('/api/virtual-receptionists/greeting/apply', ['ivr' => self::LOCAL])
            ->assertUnprocessable()->assertJsonValidationErrors(['file_name']);

        $this->assertDatabaseHas('v_ivr_menus', ['ivr_menu_uuid' => self::LOCAL, 'ivr_menu_greet_long' => 'original.wav']);
    }

    public function test_authorized_gateway_settings_can_be_read_updated_and_deactivated(): void
    {
        $this->signIn('payment_gateways_view');

        $this->getJson('/api/system-settings/payment_gateways')
            ->assertOk()->assertJsonPath('0.uuid', self::GATEWAY)->assertJsonPath('0.settings.sandbox', 'true');

        $this->putJson('/api/gateways', [
            'uuid' => self::GATEWAY, 'status' => 'true', 'sandbox' => 'false', 'sandbox_secret_key' => '',
        ])->assertOk();
        $this->assertDatabaseHas('gateway_settings', ['setting_key' => 'sandbox', 'setting_value' => 'false']);
        $this->assertDatabaseHas('gateway_settings', ['setting_key' => 'sandbox_secret_key', 'setting_value' => 'sk_test_FixtureOnly']);
        $this->assertDatabaseHas('gateway_settings', ['setting_key' => 'webhook_secret', 'setting_value' => 'whsec_FixtureOnly']);

        $this->postJson('/api/gateways/deactivate', ['uuid' => self::GATEWAY])->assertOk();
        $this->assertDatabaseHas('payment_gateways', ['uuid' => self::GATEWAY, 'is_enabled' => '0']);
        $this->assertNull(Cache::tags(['gateways', 'stripe'])->get('gateways:stripe:sandbox_secret_key'));
    }

    public function test_authorized_gateway_connection_test_reaches_validation_without_network_access(): void
    {
        $this->signIn('payment_gateways_view');

        $this->postJson('/api/gateways/test', ['mode' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['mode']);
        $this->postJson('/api/gateways/test', ['mode' => 'test'])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('message', 'Enter a secret key to test.');
    }

    private function signIn(string ...$permissions): void
    {
        $this->actingAs((new User())->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::ACCOUNT]));
        $this->withSession(['permissions' => array_map(fn ($permission) => (object) ['permission_name' => $permission], $permissions)]);
    }

    private function table(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }
}
