<?php

namespace Tests\Feature;

use App\Http\Controllers\BusinessHoursController;
use App\Models\{BusinessHour, BusinessHourPeriod, Dialplans, IvrMenuOptions, IvrMenus, User};
use App\Services\FlagDialplanRenderer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Tests\TestCase;

class RoutingAccountAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const MISMATCH = '33333333-3333-4333-8333-333333333333';
    private const WRONG_PARENT = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'routing_test', 'database.connections.routing_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'cache.default' => 'array', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        foreach ([new BusinessHour, new BusinessHourPeriod, new IvrMenus, new IvrMenuOptions, new Dialplans] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->uuid($model->getKeyName())->primary();
                foreach (array_diff($model->getFillable(), [$model->getKeyName()]) as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::create('v_default_settings', function (Blueprint $table) {
            foreach (['category', 'subcategory', 'enabled', 'value'] as $column) {
                $table->string('default_setting_'.$column)->nullable();
            }
        });
        Schema::create('dynamic_routes', function (Blueprint $table) {
            $table->uuid('dynamic_route_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('extension');
        });
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B] as $id => $domain) {
            DB::table('v_ivr_menus')->insert(['domain_uuid' => $domain, 'ivr_menu_uuid' => $id, 'dialplan_uuid' => self::FOREIGN]);
            DB::table('v_ivr_menu_options')->insert(['domain_uuid' => $domain, 'ivr_menu_uuid' => $id, 'ivr_menu_option_uuid' => $id]);
            DB::table('business_hours')->insert(['uuid' => $id, 'domain_uuid' => $domain, 'name' => 'Original', 'timezone' => 'UTC']);
            DB::table('dynamic_routes')->insert(['dynamic_route_uuid' => $id, 'domain_uuid' => $domain, 'extension' => '9500']);
        }
        DB::table('v_ivr_menu_options')->insert(['domain_uuid' => self::B, 'ivr_menu_uuid' => self::LOCAL, 'ivr_menu_option_uuid' => self::MISMATCH]);
        DB::table('v_ivr_menu_options')->insert(['domain_uuid' => self::A, 'ivr_menu_uuid' => self::FOREIGN, 'ivr_menu_option_uuid' => self::WRONG_PARENT]);
        DB::table('v_dialplans')->insert(['domain_uuid' => self::B, 'dialplan_uuid' => self::FOREIGN, 'dialplan_xml' => 'original']);
        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A]));
        $this->withSession(['domain_uuid' => self::A, 'domain_name' => 'a.invalid', 'permissions' => [],
            'user.groups' => [(object) ['group_name' => 'superadmin', 'group_level' => 80]]]);
    }

    private function grant(string ...$permissions): void
    {
        $this->withSession(['permissions' => array_map(fn ($p) => (object) ['permission_name' => $p], $permissions)]);
    }

    public function test_key_actions_require_edit_and_resolve_the_menu_and_key_in_the_same_account(): void
    {
        $path = '/api/virtual-receptionists/key/';
        $key = ['menu_uuid' => self::LOCAL, 'key' => '1', 'action' => 'hangup', 'status' => true];
        $this->postJson($path.'create', $key)->assertForbidden();
        $this->putJson($path.'update', [])->assertForbidden();
        $this->postJson($path.'delete', [])->assertForbidden();
        $this->grant('ivr_menu_edit');
        $this->postJson($path.'create', ['menu_uuid' => self::FOREIGN] + $key)->assertNotFound();
        foreach ([self::FOREIGN, self::MISMATCH, self::WRONG_PARENT] as $id) {
            $this->putJson($path.'update', ['option_uuid' => $id] + $key)->assertNotFound();
            $this->postJson($path.'delete', ['ivr_menu_option_uuid' => $id])->assertNotFound();
        }
        $id = $this->postJson($path.'create', ['domain_uuid' => self::B] + $key)->assertCreated()->json('data.ivr_menu_option_uuid');
        $routing = ['action' => 'dynamic_routes', 'target' => self::FOREIGN, 'extension' => '9500'];
        $this->putJson($path.'update', ['option_uuid' => $id] + $routing + $key)->assertJsonValidationErrors('target');
        $this->putJson($path.'update', ['option_uuid' => $id, 'target' => self::LOCAL, 'extension' => '9999'] + $routing + $key)->assertJsonValidationErrors('target');
        $this->putJson($path.'update', ['option_uuid' => $id, 'target' => self::LOCAL] + $routing + $key)->assertCreated();
        $this->assertDatabaseHas('v_ivr_menu_options', ['ivr_menu_option_uuid' => $id, 'domain_uuid' => self::A, 'ivr_menu_option_param' => 'transfer 9500 XML a.invalid']);
        $this->postJson($path.'delete', ['ivr_menu_option_uuid' => self::LOCAL])->assertOk();
        $this->assertDatabaseMissing('v_ivr_menu_options', ['ivr_menu_option_uuid' => self::LOCAL]);
    }

    public function test_menu_bulk_delete_validates_the_whole_selection_and_preserves_foreign_children(): void
    {
        $url = '/api/virtual-receptionists/bulk-delete';
        $this->postJson($url, [])->assertForbidden();
        $this->grant('ivr_menu_delete');
        $this->postJson($url, ['items' => ['invalid']])->assertUnprocessable();
        $this->postJson($url, ['items' => [self::LOCAL, self::FOREIGN]])->assertNotFound();
        $this->assertDatabaseHas('v_ivr_menu_options', ['ivr_menu_option_uuid' => self::LOCAL]);
        $this->postJson($url, ['items' => [self::LOCAL]])->assertOk();
        $this->assertDatabaseMissing('v_ivr_menus', ['ivr_menu_uuid' => self::LOCAL]);
        $this->assertDatabaseMissing('v_ivr_menu_options', ['ivr_menu_option_uuid' => self::LOCAL]);
        $this->assertDatabaseHas('v_ivr_menus', ['ivr_menu_uuid' => self::FOREIGN]);
        $this->assertDatabaseHas('v_ivr_menu_options', ['ivr_menu_option_uuid' => self::MISMATCH]);
        $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => self::FOREIGN, 'dialplan_xml' => 'original']);
    }

    public function test_menu_regeneration_creates_an_owned_plan_for_a_stale_reference(): void
    {
        $this->mock(FlagDialplanRenderer::class)->shouldReceive('ivr')->twice()
            ->andReturnUsing(fn (IvrMenus $menu) => '<extension uuid="'.$menu->dialplan_uuid.'"/>');
        $menu = IvrMenus::findOrFail(self::LOCAL);
        $controller = app(\App\Http\Controllers\VirtualReceptionistController::class);
        $generate = new \ReflectionMethod($controller, 'generateDialPlanXML');
        $generate->invoke($controller, $menu);
        $localId = $menu->fresh()->dialplan_uuid;
        $generate->invoke($controller, $menu);
        $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => $localId, 'domain_uuid' => self::A, 'dialplan_xml' => '<extension uuid="'.$localId.'"/>']);
        $this->grant('ivr_menu_delete');
        $this->postJson('/api/virtual-receptionists/bulk-delete', ['items' => [self::LOCAL]])->assertOk();
        $this->assertDatabaseMissing('v_dialplans', ['dialplan_uuid' => $localId]);
        $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => self::FOREIGN, 'domain_uuid' => self::B, 'dialplan_xml' => 'original']);
    }

    public function test_schedule_rebuild_preserves_a_dialplan_owned_by_another_account(): void
    {
        $schedule = BusinessHour::findOrFail(self::LOCAL);
        $schedule->dialplan_uuid = self::FOREIGN;
        $schedule->after_hours_action = 'hangup';
        $schedule->setRelation('periods', collect())->setRelation('holidays', collect())->setRelation('after_hours_target', null);

        try {
            app(BusinessHoursController::class)->generateDialPlanXML($schedule);
            $this->fail('Expected a mismatched dialplan to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('dialplan_uuid', $e->errors());
        }
        $this->assertDatabaseHas('v_dialplans', ['dialplan_uuid' => self::FOREIGN, 'domain_uuid' => self::B, 'dialplan_xml' => 'original']);
    }

    public function test_authorized_schedule_update_preserves_its_account_and_regenerates_its_routing(): void
    {
        // UniqueExtension checks the shared extension namespace in this account.
        foreach ([
            \App\Models\Extensions::class => 'extension', \App\Models\RingGroups::class => 'ring_group_extension',
            \App\Models\CallCenterQueues::class => 'queue_extension', \App\Models\Faxes::class => 'fax_extension',
            \App\Models\CallFlows::class => 'call_flow_extension', \App\Models\ConferenceCenters::class => 'conference_center_extension',
            \App\Models\Conferences::class => 'conference_extension', \App\Models\AiAgent::class => 'extension',
            \App\Models\PagingGroup::class => 'extension',
        ] as $class => $extensionField) {
            $model = new $class;
            Schema::create($model->getTable(), function (Blueprint $table) use ($model, $extensionField) {
                $table->uuid($model->getKeyName())->primary();
                $table->uuid('domain_uuid');
                $table->string($extensionField);
            });
        }
        $controller = \Mockery::mock(BusinessHoursController::class.'[generateDialPlanXML]', []);
        $controller->shouldReceive('generateDialPlanXML')->once()->withArgs(fn ($schedule) => $schedule->uuid === self::LOCAL && $schedule->domain_uuid === self::A);
        $this->instance(BusinessHoursController::class, $controller);
        $this->grant('business_hours_update');
        $this->putJson('/api/business-hours/'.self::LOCAL, [
            'name' => 'Office', 'extension' => '9200', 'timezone' => 'UTC', 'custom_hours' => false,
            'domain_uuid' => self::B, 'uuid' => self::FOREIGN,
        ])->assertOk();
        $this->assertDatabaseHas('business_hours', ['uuid' => self::LOCAL, 'domain_uuid' => self::A, 'name' => 'Office']);
        $this->assertDatabaseHas('business_hours', ['uuid' => self::FOREIGN, 'domain_uuid' => self::B, 'name' => 'Original']);
    }

    public function test_schedule_editor_checks_permission_and_bound_account_before_returning_or_changing_data(): void
    {
        $url = '/api/business-hours';
        $this->postJson($url.'/item-options', ['item_uuid' => self::LOCAL])->assertForbidden();
        $this->putJson($url.'/'.self::LOCAL)->assertForbidden();
        $this->grant('business_hours_update', 'business_hours_delete');
        $this->postJson($url.'/item-options', ['item_uuid' => self::FOREIGN])->assertNotFound();
        $this->putJson($url.'/'.self::FOREIGN, ['uuid' => self::LOCAL])->assertNotFound();
        $this->postJson($url.'/bulk-delete', ['items' => [self::LOCAL, self::FOREIGN]])->assertNotFound();
        $this->assertDatabaseCount('business_hours', 2);
        $this->postJson($url.'/item-options', ['item_uuid' => self::LOCAL])->assertOk()->assertJsonPath('item.uuid', self::LOCAL);
        $this->putJson($url.'/'.self::LOCAL)->assertJsonValidationErrors('name');
        $this->withSession(['domain_uuid' => null]);
        $this->postJson($url.'/item-options', ['item_uuid' => self::LOCAL])->assertForbidden();
    }
}
