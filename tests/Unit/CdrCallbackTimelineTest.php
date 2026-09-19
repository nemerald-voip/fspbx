<?php

namespace Tests\Unit;

use App\Http\Controllers\CdrsController;
use App\Models\CDR;
use App\Services\CdrDataService;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Facade, Schema};
use Illuminate\Translation\{ArrayLoader, Translator};
use PHPUnit\Framework\TestCase;

class CdrCallbackTimelineTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const OTHER_DOMAIN = '22222222-2222-4222-8222-222222222222';
    private const ATTEMPT = '33333333-3333-4333-8333-333333333333';
    private $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver = Model::getConnectionResolver();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository());
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $db = new Manager($app);
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $db->bootEloquent();
        $manager = $db->getDatabaseManager();
        $app->instance('db', $manager);
        $app->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
        Schema::create('v_xml_cdr', function (Blueprint $table) {
            $table->string('xml_cdr_uuid')->primary();
            foreach (['domain_uuid', 'cc_callback_attempt_uuid', 'cc_callback_role', 'destination_number',
                'caller_destination', 'status', 'direction', 'sip_hangup_disposition', 'hangup_cause',
                'cc_member_session_uuid', 'originating_leg_uuid', 'call_center_queue_uuid', 'extension_uuid',
                'sip_call_id', 'record_path', 'record_name', 'caller_id_name', 'caller_id_number',
                'missed_call', 'voicemail_message', 'hangup_cause_q850', 'cc_cancel_reason', 'cc_cause'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['start_epoch', 'answer_epoch', 'end_epoch', 'duration', 'billsec', 'waitsec'] as $field) {
                $table->integer($field)->nullable();
            }
            $table->text('call_flow')->nullable();
        });
        Schema::create('v_extensions', function (Blueprint $table) {
            $table->string('domain_uuid');
            $table->string('extension');
            $table->string('effective_caller_id_name');
        });
        Schema::create('archive_recording', function (Blueprint $table) {
            $table->string('xml_cdr_uuid');
            $table->string('object_key');
        });
        DB::table('v_extensions')->insert([
            ['domain_uuid' => self::DOMAIN, 'extension' => '100', 'effective_caller_id_name' => 'David Duck'],
            ['domain_uuid' => self::OTHER_DOMAIN, 'extension' => '100', 'effective_caller_id_name' => 'Other account'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    public function test_callback_uses_one_scoped_query_and_native_phone_intervals_instead_of_profiles(): void
    {
        $agent = $this->call(1, 'agent', '100', 1000, 1002, 1030);
        $this->call(2, 'customer', '12125550100', 1010, 1013, 1025, [
            'hangup_cause' => 'CALL_REJECTED', // Answered, but confirmation may have failed.
        ]);
        $this->call(3, 'agent', '100', 900, 901, 950, ['domain_uuid' => self::OTHER_DOMAIN]);
        $this->call(4, 'customer', '999', 900, 901, 950, ['cc_callback_attempt_uuid' => self::OTHER_DOMAIN]);
        $this->call(5, 'internal', 'lua', 900, 901, 950);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $steps = (new CdrDataService())->buildCallFlowSummary($agent);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries, 'Callback details must not perform per-leg lookups');
        $this->assertStringContainsString('left join "v_extensions"', $queries[0]['query']);
        $this->assertSame(['agent', 'customer'], $steps->pluck('cc_callback_role')->all());
        $this->assertSame(['100', '12125550100'], $steps->pluck('destination_number')->all());
        $this->assertSame(['00:00', '00:10'], $steps->pluck('time_line')->all());
        $this->assertSame([30, 15], $steps->pluck('duration_seconds')->all());
        $this->assertSame([28, 12], $steps->pluck('billsec')->all());
        $this->assertSame([2, 3], $steps->pluck('waitsec')->all());
        $this->assertSame(['Answered', 'Answered'], $steps->pluck('status_label')->all());
        $this->assertSame('David Duck', $steps[0]['dialplan_name']);
        $this->assertNull($steps[1]['dialplan_name']);
        $this->assertArrayNotHasKey('bridged_time', $steps[1], 'SIP answer must not fabricate a confirmed bridge');
        $this->assertSame(30, $agent->duration, 'Related intervals must not replace or inflate the selected CDR duration');
    }

    public function test_opening_the_customer_record_uses_the_same_attempt_start_and_steps(): void
    {
        $agent = $this->call(1, 'agent', '100', 1000, 1001, 1030);
        $customer = $this->call(2, 'customer', '101', 1010, 1012, 1025);
        $service = new CdrDataService();

        $this->assertSame($service->buildCallFlowSummary($agent)->all(), $service->buildCallFlowSummary($customer)->all());
    }

    public function test_concurrent_and_repeated_offers_keep_separate_channel_outcomes(): void
    {
        $busy = $this->call(1, 'agent', '100', 1000, 0, 1008, ['status' => 'busy', 'hangup_cause' => 'USER_BUSY']);
        $this->call(2, 'agent', '102', 1000, 0, 1011, ['status' => 'no_answer']);
        $this->call(3, 'agent', '100', 1020, 1021, 1050);
        $this->call(4, 'customer', '12125550100', 1030, 0, 1045, ['status' => 'no_answer']);

        $steps = (new CdrDataService())->buildCallFlowSummary($busy);

        $this->assertSame(['busy', 'no_answer', 'answered', 'no_answer'], $steps->pluck('status')->all());
        $this->assertSame(['00:00', '00:00', '00:20', '00:30'], $steps->pluck('time_line')->all());
        $this->assertSame([0, 0, 29, 0], $steps->pluck('billsec')->all());
        $this->assertSame([8, 11, 1, 15], $steps->pluck('waitsec')->all());
        $this->assertCount(4, $steps->pluck('xml_cdr_uuid')->unique());
    }

    public function test_a_missing_customer_cdr_does_not_create_a_customer_or_completion_step(): void
    {
        $agent = $this->call(1, 'agent', '100', 1000, 0, 1008, ['status' => 'failed']);
        $steps = (new CdrDataService())->buildCallFlowSummary($agent);

        $this->assertCount(1, $steps);
        $this->assertSame('agent', $steps[0]['cc_callback_role']);
        $this->assertSame('Failed', $steps[0]['status_label']);
        $this->assertSame('00:00:00', $steps[0]['billsec_formatted']);
    }

    public function test_api_detail_loads_callback_identity_and_returns_both_native_legs(): void
    {
        $agent = $this->call(1, 'agent', '100', 1000, 1002, 1030);
        $customer = $this->call(2, 'customer', '101', 1010, 1013, 1025);

        $payload = (new CdrDataService())->buildApiShowPayload(self::DOMAIN, $customer->getKey());

        $this->assertCount(2, $payload->call_flow);
        $this->assertSame($agent->getKey(), $payload->call_flow[0]->xml_cdr_uuid);
        $this->assertSame(self::ATTEMPT, $payload->call_flow[0]->cc_callback_attempt_uuid);
        $this->assertSame('customer', $payload->call_flow[1]->cc_callback_role);
        $this->assertSame('answered', $payload->call_flow[1]->status);
        $this->assertSame(12, $payload->call_flow[1]->billsec);
        $this->assertSame(15, $payload->duration);
    }

    public function test_modal_detail_loads_callback_identity_and_groups_the_same_account_attempt(): void
    {
        $agent = $this->call(1, 'agent', '100', 1000, 1002, 1030);
        $customer = $this->call(2, 'customer', '101', 1010, 1013, 1025);
        $this->call(3, 'agent', '100', 900, 901, 950, ['domain_uuid' => self::OTHER_DOMAIN]);
        app()->instance('request', Request::create('/cdrs/item-options', 'GET', ['item_uuid' => $customer->getKey()]));
        $session = new Store('testing', new ArraySessionHandler(120));
        $session->put('domain_uuid', self::DOMAIN);
        app()->instance('session', $session);

        $result = (new CdrsController(new CdrDataService()))->getItemOptions();

        $this->assertTrue($result['item']->callback_timeline);
        $this->assertSame(self::ATTEMPT, $result['item']->cc_callback_attempt_uuid);
        $this->assertSame('101', $result['item']->destination_number);
        $this->assertSame([$agent->getKey(), $customer->getKey()], $result['item']->call_flow->pluck('xml_cdr_uuid')->all());
        $this->assertSame(15, $result['item']->duration);
    }

    public function test_historical_records_keep_the_original_profile_timeline(): void
    {
        $old = $this->call(1, 'agent', '100', 1000, 1002, 1030, [
            'cc_callback_attempt_uuid' => null, 'cc_callback_role' => null,
        ]);
        $service = new class extends CdrDataService {
            protected function isBasicDialerCdr(CDR $cdr): bool { return false; }
            protected function isOutboundFaxCdr(CDR $cdr): bool { return false; }
            protected function enrichCallFlowSummary(Collection $rows, string $domainUuid): Collection { return $rows; }
            protected function buildCallbackCallFlowSummary(CDR $cdr): Collection
            {
                throw new \RuntimeException('Historical CDR entered callback normalization');
            }
        };
        $steps = $service->buildCallFlowSummary($old);

        $this->assertFalse($service->isCallbackCdr($old));
        $this->assertCount(2, $steps);
        $this->assertSame(['9400', '9400'], $steps->pluck('destination_number')->all());
        $this->assertInstanceOf(\Spatie\LaravelData\Optional::class, $service->buildApiCallFlowData($old)[0]->cc_callback_role);
    }

    public function test_detail_queries_remain_compatible_before_the_callback_columns_are_installed(): void
    {
        Schema::table('v_xml_cdr', fn (Blueprint $table) => $table->dropColumn(['cc_callback_attempt_uuid', 'cc_callback_role']));
        $this->assertSame([], (new CdrDataService())->callbackIdentityColumns());
    }

    private function call(int $id, string $role, string $number, int $start, int $answer, int $end, array $overrides = []): CDR
    {
        $uuid = sprintf('%08d-1111-4111-8111-111111111111', $id);
        // Two connected-party profiles for the same channel deliberately carry
        // a different callee and misleading durations. Callback timelines must
        // use the saved endpoint facts instead.
        $profile = [
            'caller_profile' => ['uuid' => $uuid, 'destination_number' => 'lua', 'callee_id_number' => '9400'],
            'times' => array_fill_keys(['bridged_time', 'created_time', 'answered_time', 'progress_time',
                'transfer_time', 'progress_media_time', 'hangup_time'], 0)
                + ['profile_created_time' => ($start + 1) * 1000000, 'profile_end_time' => ($end + 90) * 1000000],
        ];
        $row = array_replace([
            'xml_cdr_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'cc_callback_attempt_uuid' => self::ATTEMPT,
            'cc_callback_role' => $role, 'destination_number' => $number, 'caller_destination' => $number,
            'start_epoch' => $start, 'answer_epoch' => $answer, 'end_epoch' => $end,
            'duration' => $end - $start, 'billsec' => $answer ? $end - $answer : 0,
            'status' => 'answered', 'direction' => 'outbound', 'hangup_cause' => 'NORMAL_CLEARING',
            'call_flow' => json_encode([$profile, $profile]),
        ], $overrides);
        DB::table('v_xml_cdr')->insert($row);

        return (new CDR())->forceFill($row);
    }
}
