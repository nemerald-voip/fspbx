<?php

namespace Tests\Feature;

use App\Http\Controllers\BusinessHoursController;
use App\Models\{BusinessHour, BusinessHourHoliday, DynamicRoute, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Http, Process, Schema};
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class HolidayAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const SECOND = '33333333-3333-4333-8333-333333333333';
    private const UNOWNED = '44444444-4444-4444-8444-444444444444';
    private const ORPHAN = '55555555-5555-4555-8555-555555555555';
    private const MISSING = '66666666-6666-4666-8666-666666666666';
    private const EXTRA = '77777777-7777-4777-8777-777777777777';
    private const URL = '/api/holiday-hours';
    private array $regenerated = [];
    private ?string $failFor = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'holiday_authorization',
            'database.connections.holiday_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('holiday_authorization');
        Http::preventStrayRequests();
        Bus::fake();
        Process::fake();
        foreach ([new BusinessHour, new BusinessHourHoliday] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->uuid($model->getKeyName())->primary();
                foreach ($model->getFillable() as $column) {
                    $table->string($column)->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::create('dynamic_routes', function (Blueprint $table) {
            $table->uuid('dynamic_route_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('extension');
            $table->string('name');
        });
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B, self::SECOND => self::A, self::UNOWNED => null] as $uuid => $domain) {
            DB::table('business_hours')->insert(['uuid' => $uuid, 'domain_uuid' => $domain, 'name' => $uuid]);
        }
        foreach ([self::LOCAL, self::FOREIGN, self::SECOND, self::UNOWNED, self::ORPHAN, self::EXTRA] as $uuid) {
            $parent = match ($uuid) {
                self::ORPHAN => self::MISSING,
                self::EXTRA => self::LOCAL,
                default => $uuid,
            };
            DB::table('business_hour_holidays')->insert([
                'uuid' => $uuid, 'business_hour_uuid' => $parent, 'holiday_type' => 'recurring_pattern',
                'description' => 'original', 'mday' => '1', 'action' => 'hangup', 'created_at' => '2026-01-01 00:00:00',
            ]);
        }
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B] as $uuid => $domain) {
            DB::table('dynamic_routes')->insert(['dynamic_route_uuid' => $uuid, 'domain_uuid' => $domain, 'extension' => '9504', 'name' => $domain]);
        }

        // Exercise the real holiday observer but never render dialplans, contact FreeSWITCH, or clear live caches.
        $generator = Mockery::mock(BusinessHoursController::class)->makePartial();
        $generator->shouldReceive('generateDialPlanXML')->andReturnUsing(function (BusinessHour $parent) {
            $this->regenerated[] = [
                'uuid' => $parent->uuid, 'domain_uuid' => $parent->domain_uuid,
                'holidays' => $parent->holidays()->orderBy('uuid')->pluck('uuid')->all(),
            ];
            if ($this->failFor === $parent->uuid) {
                throw new \RuntimeException('Synthetic dialplan failure');
            }
        });
        $this->instance(BusinessHoursController::class, $generator);
        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::A, 'domain_name' => 'a.invalid', 'domain.language.code' => 'en-us', 'permissions' => []]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('holiday_authorization');
        parent::tearDown();
    }

    public static function endpoints(): array
    {
        return [
            ['GET', '?uuid='.self::LOCAL, 'list_view', []],
            ['POST', '/item-options', 'create', []],
            ['POST', '/item-options', 'update', ['item_uuid' => self::LOCAL]],
            ['POST', '', 'create', []],
            ['PUT', '/'.self::LOCAL, 'update', []],
            ['POST', '/bulk-delete', 'delete', ['items' => [self::LOCAL]]],
        ];
    }

    /** @dataProvider endpoints */
    public function test_every_endpoint_requires_its_holiday_permission(string $method, string $path, string $action, array $data): void
    {
        $this->permissions(...array_diff(['list_view', 'create', 'update', 'delete'], [$action]));
        $this->json($method, self::URL.$path, $data)->assertForbidden();
        $this->assertSame([], $this->regenerated);
        $this->unchanged();
    }

    /** @dataProvider endpoints */
    public function test_missing_or_invalid_account_context_fails_closed(string $method, string $path, string $action, array $data): void
    {
        $this->permissions($action);
        foreach ([null, '', 'invalid'] as $domain) {
            $this->withSession(['domain_uuid' => $domain]);
            $this->json($method, self::URL.$path, $data)->assertForbidden();
        }
        $this->assertSame([], $this->regenerated);
        $this->unchanged();
    }

    public function test_listing_and_item_options_are_scoped_through_the_parent(): void
    {
        $this->permissions('list_view', 'create', 'update');
        $this->getJson(self::URL.'?uuid='.self::LOCAL)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['uuid' => self::LOCAL])->assertJsonMissing(['uuid' => self::FOREIGN]);
        $this->postJson(self::URL.'/item-options')->assertOk()->assertJsonPath('routes.update_route', null);
        $this->postJson(self::URL.'/item-options', ['item_uuid' => self::LOCAL])->assertOk()
            ->assertJsonPath('item.uuid', self::LOCAL)->assertJsonPath('routes.store_route', null);
        foreach ([self::FOREIGN, self::UNOWNED, self::MISSING] as $uuid) {
            $this->getJson(self::URL.'?uuid='.$uuid.'&domain_uuid='.self::B)->assertNotFound();
        }
        foreach ([self::FOREIGN, self::UNOWNED, self::ORPHAN, self::MISSING] as $uuid) {
            $this->postJson(self::URL.'/item-options', ['item_uuid' => $uuid, 'mode' => 'create'])->assertNotFound();
        }
    }

    public function test_create_mode_cannot_bypass_item_options_edit_permission(): void
    {
        $this->permissions('create');
        $this->postJson(self::URL.'/item-options', ['item_uuid' => self::LOCAL, 'mode' => 'create'])->assertForbidden();
        $this->postJson(self::URL.'/item-options')->assertOk();
    }

    public function test_creation_requires_an_existing_parent_in_the_current_account(): void
    {
        $this->permissions('create');
        foreach ([self::FOREIGN, self::UNOWNED, self::MISSING, null, 'invalid'] as $parent) {
            $this->postJson(self::URL, $this->payload($parent) + ['domain_uuid' => self::B])
                ->assertUnprocessable()->assertJsonValidationErrors('business_hour_uuid');
            $this->unchanged();
        }
        $this->assertSame([], $this->regenerated);
    }

    public function test_creation_preserves_date_normalization_and_rebuilds_only_its_parent(): void
    {
        $this->permissions('create');
        $data = $this->payload(self::LOCAL, ['holiday_type' => 'single_date', 'start_date' => '2026-12-25', 'uuid' => self::FOREIGN]);
        $response = $this->postJson(self::URL, $data)->assertCreated();
        $uuid = $response->json('data.uuid');
        $this->assertNotSame(self::FOREIGN, $uuid);
        $holiday = BusinessHourHoliday::findOrFail($uuid);
        $this->assertSame(self::LOCAL, $holiday->business_hour_uuid);
        $this->assertSame('2026-12-25', $holiday->start_date->format('Y-m-d'));
        $this->assertTrue($holiday->start_date->equalTo($holiday->end_date));
        $this->assertSame([self::LOCAL], array_column($this->regenerated, 'uuid'));
        $this->assertContains($uuid, $this->regenerated[0]['holidays']);
        $this->assertDatabaseHas('business_hour_holidays', ['uuid' => self::FOREIGN, 'description' => 'original']);
    }

    public function test_update_checks_ownership_through_the_stored_parent(): void
    {
        $this->permissions('update');
        $this->withSession(['user.groups' => [(object) ['group_name' => 'superadmin', 'group_level' => 80]]]);
        foreach ([self::FOREIGN, self::UNOWNED, self::ORPHAN, self::MISSING] as $uuid) {
            $this->putJson(self::URL.'/'.$uuid, $this->payload())->assertNotFound();
            $this->putJson(self::URL.'/'.$uuid, [])->assertNotFound();
            $this->unchanged();
        }
        $this->assertSame([], $this->regenerated);
    }

    public function test_update_retains_the_shared_routing_target_validation(): void
    {
        $this->permissions('create', 'update');
        foreach ([self::FOREIGN, self::MISSING, 'invalid', null] as $uuid) {
            $data = $this->payload(self::LOCAL, ['action' => 'dynamic_routes', 'target' => ['value' => $uuid], 'domain_uuid' => self::B]);
            $this->postJson(self::URL, $data)->assertUnprocessable()->assertJsonValidationErrors('target');
            $this->putJson(self::URL.'/'.self::LOCAL, $data)->assertUnprocessable()->assertJsonValidationErrors('target');
            $this->unchanged();
        }
        $this->assertSame([], $this->regenerated);
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload(self::LOCAL, [
            'action' => 'dynamic_routes', 'target' => ['value' => self::LOCAL],
            'target_id' => self::FOREIGN, 'target_type' => User::class,
        ]))->assertOk();
        $this->assertDatabaseHas('business_hour_holidays', [
            'uuid' => self::LOCAL, 'business_hour_uuid' => self::LOCAL, 'description' => 'changed',
            'target_type' => DynamicRoute::class, 'target_id' => self::LOCAL,
        ]);
        $this->assertSame([self::LOCAL], array_column($this->regenerated, 'uuid'));
    }

    public function test_update_derives_parent_from_the_holiday_without_a_parent_parameter(): void
    {
        $this->permissions('update');
        $data = $this->payload();
        unset($data['business_hour_uuid']);

        $this->putJson(self::URL.'/'.self::LOCAL, $data)->assertOk();
        $this->assertDatabaseHas('business_hour_holidays', [
            'uuid' => self::LOCAL, 'business_hour_uuid' => self::LOCAL, 'description' => 'changed',
        ]);
        $this->assertSame([self::LOCAL], array_column($this->regenerated, 'uuid'));
    }

    public static function suppliedParents(): array
    {
        return [[self::SECOND], [self::FOREIGN], [self::UNOWNED], [self::MISSING], [null], ['invalid'], [[self::FOREIGN]]];
    }

    /** @dataProvider suppliedParents */
    public function test_client_supplied_parent_is_ignored_on_update(mixed $parent): void
    {
        $this->permissions('update');
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload(self::LOCAL, ['business_hour_uuid' => $parent]))->assertOk();
        $this->assertDatabaseHas('business_hour_holidays', [
            'uuid' => self::LOCAL, 'business_hour_uuid' => self::LOCAL, 'description' => 'changed',
        ]);
        $this->assertDatabaseHas('business_hour_holidays', ['uuid' => self::FOREIGN, 'business_hour_uuid' => self::FOREIGN, 'description' => 'original']);
        $this->assertSame([self::LOCAL], array_column($this->regenerated, 'uuid'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_mixed_deletion_preflights_every_record_before_any_dialplan_changes(): void
    {
        $this->permissions('delete');
        foreach ([self::FOREIGN, self::UNOWNED, self::ORPHAN, self::MISSING] as $uuid) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL, $uuid]])->assertNotFound();
            $this->unchanged();
            $this->assertSame([], $this->regenerated);
        }
    }

    public function test_bulk_delete_rebuilds_each_affected_owned_schedule_once(): void
    {
        $this->permissions('delete');
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL, self::EXTRA, self::SECOND]])->assertOk();
        foreach ([self::LOCAL, self::EXTRA, self::SECOND] as $uuid) {
            $this->assertDatabaseMissing('business_hour_holidays', ['uuid' => $uuid]);
        }
        foreach ([self::FOREIGN, self::UNOWNED, self::ORPHAN] as $uuid) {
            $this->assertDatabaseHas('business_hour_holidays', ['uuid' => $uuid, 'description' => 'original']);
        }
        $this->assertSame([self::LOCAL, self::SECOND], array_column($this->regenerated, 'uuid'));
        foreach ($this->regenerated as $schedule) {
            $this->assertSame(self::A, $schedule['domain_uuid']);
            $this->assertSame([], $schedule['holidays']);
        }
    }

    public function test_invalid_identifiers_and_selection_arrays_are_rejected(): void
    {
        $this->permissions('list_view', 'create', 'update', 'delete');
        $this->getJson(self::URL)->assertUnprocessable();
        $this->getJson(self::URL.'?uuid=invalid')->assertUnprocessable();
        $this->postJson(self::URL.'/item-options', ['item_uuid' => ['invalid']])->assertUnprocessable();
        $this->putJson(self::URL.'/invalid', $this->payload())->assertNotFound();
        foreach ([null, [], 'invalid', ['invalid'], [self::LOCAL, self::LOCAL]] as $items) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => $items])->assertUnprocessable();
        }
        $this->unchanged();
        $this->assertSame([], $this->regenerated);
    }

    public function test_account_switch_uses_current_session_and_not_login_account(): void
    {
        $this->permissions('list_view', 'update', 'delete');
        $this->withSession(['domain_uuid' => self::B, 'domain_name' => 'b.invalid']);
        $this->getJson(self::URL.'?uuid='.self::FOREIGN)->assertOk()->assertJsonCount(1, 'data');
        $this->putJson(self::URL.'/'.self::FOREIGN, $this->payload(self::FOREIGN))->assertOk();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertNotFound();
        $this->assertDatabaseHas('business_hour_holidays', ['uuid' => self::LOCAL, 'description' => 'original']);
        $this->assertSame([self::B], array_column($this->regenerated, 'domain_uuid'));
    }

    public function test_generator_failure_rolls_back_creates_updates_and_deletes(): void
    {
        $this->permissions('create', 'update', 'delete');
        $this->failFor = self::LOCAL;
        $this->postJson(self::URL, $this->payload())->assertStatus(500);
        $this->unchanged();
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload())->assertStatus(500);
        $this->unchanged();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertStatus(500);
        $this->unchanged();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_dialplan_validation_errors_are_preserved_and_roll_back_all_mutations(): void
    {
        $this->permissions('create', 'update', 'delete');
        $generator = Mockery::mock(BusinessHoursController::class);
        $generator->shouldReceive('generateDialPlanXML')->andThrow(
            ValidationException::withMessages(['target' => 'Select a valid routing target in this account.'])
        );
        $this->instance(BusinessHoursController::class, $generator);

        $this->postJson(self::URL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('target');
        $this->unchanged();
        $this->putJson(self::URL.'/'.self::LOCAL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('target');
        $this->unchanged();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])
            ->assertUnprocessable()->assertJsonValidationErrors('target');
        $this->unchanged();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_native_cookie_authentication_boundary_is_preserved(): void
    {
        $this->permissions('delete');
        $this->withHeader('Authorization', 'Bearer synthetic-token')
            ->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertUnauthorized();
        $this->unchanged();
        $this->assertSame([], $this->regenerated);
    }

    private function payload(?string $parent = self::LOCAL, array $overrides = []): array
    {
        return $overrides + ['business_hour_uuid' => $parent, 'holiday_type' => 'recurring_pattern', 'description' => 'changed', 'mday' => '2', 'action' => 'hangup'];
    }

    private function permissions(string ...$actions): void
    {
        $this->withSession(['permissions' => array_map(fn ($action) => (object) ['permission_name' => 'business_hours_holidays_'.$action], $actions)]);
    }

    private function unchanged(): void
    {
        $this->assertSame(6, DB::table('business_hour_holidays')->count());
        $this->assertSame(0, DB::table('business_hour_holidays')->where('description', '!=', 'original')->count());
        foreach ([self::LOCAL, self::FOREIGN, self::SECOND, self::UNOWNED] as $uuid) {
            $this->assertDatabaseHas('business_hour_holidays', ['uuid' => $uuid, 'business_hour_uuid' => $uuid]);
        }
    }
}
