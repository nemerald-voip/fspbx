<?php

namespace Tests\Unit;

use App\Http\Requests\StoreBasicQueueRequest;
use App\Models\CallCenterQueueAgents;
use App\Models\CallCenterQueues;
use App\Services\BasicQueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class BasicQueueTierTest extends TestCase
{
    private const AGENT = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const QUEUE = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        session(['domain_uuid' => 'account-a', 'domain_name' => 'example.test', 'permissions' => [
            (object) ['permission_name' => 'contact_center_settings_edit'],
        ]]);
        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_call_center_queues' => ['call_center_queue_uuid', 'domain_uuid', 'dialplan_uuid', 'queue_extension', 'queue_name', 'update_date', 'update_user'],
            'v_call_center_agents' => ['call_center_agent_uuid', 'domain_uuid', 'agent_name', 'agent_id'],
            'v_call_center_tiers' => ['call_center_tier_uuid', 'domain_uuid', 'call_center_queue_uuid', 'call_center_agent_uuid', 'agent_name', 'queue_name', 'tier_level', 'tier_position', 'insert_date', 'insert_user', 'update_date', 'update_user'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                $table->primary($columns[0]);
            });
        }
        DB::table('v_domains')->insert(['domain_uuid' => 'account-a', 'domain_name' => 'example.test']);
        DB::table('v_call_center_queues')->insert(['call_center_queue_uuid' => self::QUEUE, 'domain_uuid' => 'account-a', 'queue_extension' => '8200']);
        DB::table('v_call_center_agents')->insert(['call_center_agent_uuid' => self::AGENT, 'domain_uuid' => 'account-a', 'agent_name' => 'Fixture Agent', 'agent_id' => '201']);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function queue(): CallCenterQueues
    {
        return CallCenterQueues::findOrFail(self::QUEUE);
    }

    private function saveBasic(array $values): array
    {
        return (new ReflectionMethod(BasicQueueService::class, 'syncTiers'))->invoke(
            new BasicQueueService(), $this->queue(), [['call_center_agent_uuid' => self::AGENT] + $values]
        );
    }

    private function basicValues(): array
    {
        // BasicQueueController reads these eagerly loaded pivot fields.
        $agent = $this->queue()->load('agents')->agents->first();
        return [(int) $agent->pivot->tier_level, (int) $agent->pivot->tier_position];
    }

    public function test_basic_queue_reads_and_saves_the_actual_persisted_tiers(): void
    {
        $this->saveBasic(['tier_level' => '3', 'tier_position' => '7']);
        $this->assertSame([3, 7], $this->basicValues());
        $result = $this->saveBasic(['tier_level' => '4', 'tier_position' => '8']);
        $this->assertTrue($result['updated']);
        $this->assertSame([4, 8], $this->basicValues());
        $this->assertFalse($this->saveBasic(['tier_level' => '4', 'tier_position' => '8'])['updated']);
    }

    public function test_missing_or_cleared_basic_tiers_default_to_one(): void
    {
        $this->saveBasic([]);
        $this->assertSame([1, 1], $this->basicValues());
        $this->assertFalse($this->saveBasic(['tier_level' => null, 'tier_position' => null])['updated']);
        $this->assertSame([1, 1], $this->basicValues());
    }

    public function test_legacy_zero_values_are_read_without_silently_rewriting_them(): void
    {
        $this->saveBasic(['tier_level' => 1, 'tier_position' => 1]);
        DB::table('v_call_center_tiers')->update(['tier_level' => '0', 'tier_position' => '0']);
        $this->assertSame([0, 0], $this->basicValues());
        $this->assertSame('0', CallCenterQueueAgents::first()->tier_level);
    }

    public function test_basic_tier_validation_accepts_only_whole_numbers_from_one_to_ten(): void
    {
        $rules = (new StoreBasicQueueRequest())->rules();
        foreach (['tier_level', 'tier_position'] as $field) {
            foreach ([1, '1', 7, '10', null] as $value) {
                $this->assertTrue(Validator::make(['value' => $value], ['value' => $rules['tiers.*.'.$field]])->passes());
            }
            foreach ([0, '0', -1, 1.5, '1.5', 11, 'text'] as $value) {
                $this->assertTrue(Validator::make(['value' => $value], ['value' => $rules['tiers.*.'.$field]])->fails());
            }
        }
    }

    private function contactCenter()
    {
        if (! class_exists(\Modules\ContactCenter\Http\Controllers\SettingsController::class)) {
            $this->markTestSkipped('Contact Center is optional; cross-editor checks require the module.');
        }
        $realtime = Mockery::mock(\Modules\ContactCenter\Services\ContactCenterRealtimeService::class);
        $realtime->shouldReceive('forgetQueue', 'forgetDomainQueues')->andReturnNull();

        return new class($realtime) extends \Modules\ContactCenter\Http\Controllers\SettingsController {
            public function assignTiers(CallCenterQueues $queue, array $tiers): void
            {
                $this->createQueueAgentAssignments($queue, $tiers);
            }

            public function readTiers(CallCenterQueues $queue): array
            {
                [$agents] = $this->getAgentAssignments($queue);
                $agent = $agents->first();
                return [(int) $agent->tier_level, (int) $agent->tier_position];
            }

            protected function fillQueueAttributes(CallCenterQueues $queue, array $attributes): void {}
            protected function reloadQueue($queueExtension): void {}
        };
    }

    public function test_contact_center_and_basic_queue_round_trip_the_same_values(): void
    {
        $controller = $this->contactCenter();
        $controller->assignTiers($this->queue(), [self::AGENT => ['level' => '3', 'position' => '7']]);
        $this->assertSame([3, 7], $this->basicValues());
        $this->saveBasic(['tier_level' => '4', 'tier_position' => '8']);
        $this->assertSame([4, 8], $controller->readTiers($this->queue()));
        $request = Mockery::mock(\Modules\ContactCenter\Http\Requests\UpdateSettingsRequest::class);
        $request->shouldReceive('validated')->andReturn(['tiers' => [self::AGENT => ['level' => '5', 'position' => '6']]]);
        // Dialplan generation is outside this isolated tier persistence check.
        $response = CallCenterQueues::withoutEvents(fn () => $controller->update($request, $this->queue()));
        $this->assertSame(200, $response->status());
        $this->assertSame([5, 6], $this->basicValues());
    }

    public function test_contact_center_cleared_or_missing_tiers_cannot_turn_into_zero(): void
    {
        $controller = $this->contactCenter();
        $controller->assignTiers($this->queue(), [self::AGENT => ['level' => '3', 'position' => '7']]);
        foreach ([['level' => null, 'position' => null], []] as $values) {
            $request = Mockery::mock(\Modules\ContactCenter\Http\Requests\UpdateSettingsRequest::class);
            $request->shouldReceive('validated')->andReturn(['tiers' => [self::AGENT => $values]]);
            CallCenterQueues::withoutEvents(fn () => $controller->update($request, $this->queue()));
            $this->assertSame([1, 1], $this->basicValues());
        }
    }
}
