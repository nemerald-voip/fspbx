<?php

namespace Tests\Unit;

use App\Http\Requests\StoreBusinessHoursRequest;
use App\Http\Requests\UpdateBusinessHoursRequest;
use App\Http\Requests\StoreHolidayHourRequest;
use App\Http\Requests\UpdateHolidayHourRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RoutingTargetValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('dynamic_routes', function (Blueprint $table) {
            $table->uuid('dynamic_route_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('extension');
        });
        DB::table('dynamic_routes')->insert([
            ['dynamic_route_uuid' => RoutingDestinations::TARGET, 'domain_uuid' => RoutingDestinations::DOMAIN, 'extension' => '9504'],
            ['dynamic_route_uuid' => '33333333-3333-4333-8333-333333333333', 'domain_uuid' => RoutingDestinations::TARGET, 'extension' => '9505'],
        ]);
        session(['domain_uuid' => RoutingDestinations::DOMAIN, 'domain_name' => 'example.test']);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    /** @dataProvider requestTypes */
    public function test_target_validation_handles_missing_foreign_and_target_free_routes(string $class, string $action, string $target, bool $scalar): void
    {
        $request = new $class;
        if ($request instanceof UpdateRingGroupRequest) {
            $ringGroup = new \App\Models\RingGroups;
            $ringGroup->setRawAttributes(['ring_group_uuid' => RoutingDestinations::TARGET]);
            $route = new \Illuminate\Routing\Route('PUT', 'ring-groups/{ring_group}', fn () => null);
            $route->bind(\Illuminate\Http\Request::create('ring-groups/'.RoutingDestinations::TARGET, 'PUT'));
            $route->setParameter('ring_group', $ringGroup);
            $request->setRouteResolver(fn () => $route);
        }
        $rules = array_intersect_key($request->rules(), array_flip([$action, $target]));
        $this->assertTrue(Validator::make([$action => 'dynamic_routes', $target => $scalar ? '9504' : ['value' => RoutingDestinations::TARGET]], $rules)->passes());
        $this->assertTrue(Validator::make([$action => 'hangup'], $rules)->passes());

        foreach ([
            [$action => 'dynamic_routes'],
            [$action => 'dynamic_routes', $target => $scalar ? '9505' : ['value' => '33333333-3333-4333-8333-333333333333']],
            [$action => 'dynamic_routes', $target => $scalar ? '' : ['value' => 'bad-id']],
        ] as $data) {
            $validator = Validator::make($data, $rules);
            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has($target));
        }
        $validator = Validator::make([$action => 'unknown'], $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($action));
    }

    public static function requestTypes(): array
    {
        return [
            [StoreBusinessHoursRequest::class, 'after_hours_action', 'after_hours_target', false],
            [UpdateBusinessHoursRequest::class, 'after_hours_action', 'after_hours_target', false],
            [StoreHolidayHourRequest::class, 'action', 'target', false],
            [UpdateHolidayHourRequest::class, 'action', 'target', false],
            [UpdateRingGroupRequest::class, 'timeout_action', 'timeout_target', true],
        ];
    }

    public function test_each_time_slot_validates_its_own_action_even_when_the_target_is_omitted(): void
    {
        foreach ([StoreBusinessHoursRequest::class, UpdateBusinessHoursRequest::class] as $class) {
            $rules = array_intersect_key((new $class)->rules(), array_flip(['time_slots.*.action', 'time_slots.*.target']));
            $validator = Validator::make(['time_slots' => [
                ['action' => 'hangup'],
                ['action' => 'dynamic_routes'],
                ['action' => 'dynamic_routes', 'target' => ['value' => RoutingDestinations::TARGET]],
            ]], $rules);
            $this->assertTrue($validator->fails());
            $this->assertSame(['time_slots.1.target'], $validator->errors()->keys());
        }
    }
}
