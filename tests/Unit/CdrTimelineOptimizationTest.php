<?php

namespace Tests\Unit;

use App\Models\CDR;
use App\Services\CdrDataService;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\{DB, Facade, Schema};
use Illuminate\Translation\{ArrayLoader, Translator};
use PHPUnit\Framework\TestCase;

class CdrTimelineOptimizationTest extends TestCase
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
            $table->string('extension_uuid')->nullable();
            $table->string('description')->nullable();
            $table->string('domain_uuid');
            $table->string('extension');
            $table->string('effective_caller_id_name');
        });
        Schema::create('archive_recording', function (Blueprint $table) {
            $table->string('xml_cdr_uuid');
            $table->string('object_key');
        });
        $app->instance('session', new Store('testing', new ArraySessionHandler(120)));
        Schema::create('v_dialplans', function (Blueprint $table) {
            foreach (['dialplan_uuid', 'domain_uuid', 'dialplan_context', 'dialplan_number', 'dialplan_name',
                'dialplan_xml', 'dialplan_description', 'dialplan_enabled', 'dialplan_order'] as $key) {
                $table->string($key)->nullable();
            }
        });
        // Do not provide advanced settings: name lookup must not load them.
        foreach (['basic_dialer_campaign_attempts', 'outbound_faxes'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->string('call_uuid'));
        }
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

    public function test_name_lookup_query_count_is_constant_for_distinct_and_repeated_steps(): void
    {
        foreach ([1, 10, 50] as $count) {
            $profiles = [];
            for ($i = 0; $i < $count; $i++) {
                $profiles[] = $this->profile($i % 2 ? (string) (200 + $i) : '100', 1000 + $i, 1005 + $i);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $steps = (new CdrDataService())->buildCallFlowSummary($this->cdr($profiles));
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertCount($count, $steps);
            $this->assertCount(3, $queries);
            $this->assertSame('David Duck', $steps[0]['dialplan_name']);
            $this->assertStringNotContainsString('select *', implode(' ', array_column($queries, 'query')));
        }
    }

    public function test_related_query_deduplicates_channels_and_preserves_transfers_and_account_time_bounds(): void
    {
        $root = $this->cdr([$this->profile('9400')], ['call_center_queue_uuid' => self::ATTEMPT]);
        $child = $this->cdr([$this->profile('100', 1005, 1010), $this->profile('101', 1010, 1015)], [
            'xml_cdr_uuid' => self::ATTEMPT, 'start_epoch' => 1005,
            'cc_member_session_uuid' => $root->getKey(), 'originating_leg_uuid' => $root->getKey(),
        ]);
        DB::table('v_xml_cdr')->insert($child->getAttributes());
        DB::table('v_xml_cdr')->insert(array_replace($child->getAttributes(), ['xml_cdr_uuid' => self::OTHER_DOMAIN, 'domain_uuid' => self::OTHER_DOMAIN]));
        DB::table('v_xml_cdr')->insert(array_replace($child->getAttributes(), ['xml_cdr_uuid' => self::DOMAIN, 'start_epoch' => 10000]));
        $steps = (new CdrDataService())->buildCallFlowSummary($root);
        $this->assertSame(['9400', '100', '101'], $steps->pluck('destination_number')->all());
        $this->assertSame([10, 5, 5], $steps->pluck('duration_seconds')->all());
    }

    public function test_external_outbound_identity_survives_missing_configuration_without_type_queries(): void
    {
        DB::enableQueryLog();
        $steps = (new CdrDataService())->buildCallFlowSummary($this->cdr([$this->profile('12125550100')], ['direction' => 'outbound']));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(3, $queries);
        $this->assertSame('outbound_call', $steps[0]['dialplan_app_type']);
        $this->assertSame('Outbound Call', $steps[0]['dialplan_app']);
    }

    public function test_unrecognized_dialplan_still_produces_a_visible_extension_step(): void
    {
        $this->dialplan('100', '<action application="bridge" data="user/100"/>');
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$this->profile('100')]))->first();
        $this->assertSame('extension', $step['dialplan_app_type']);
        $this->assertSame('David Duck', $step['dialplan_name']);
    }

    public function test_dialplan_and_extension_names_cannot_come_from_another_account(): void
    {
        $this->dialplan('100', 'call_center_queue_uuid='.self::ATTEMPT, self::OTHER_DOMAIN);
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$this->profile('100')]))->first();
        $this->assertSame('extension', $step['dialplan_app_type']);
        $this->assertSame('David Duck', $step['dialplan_name']);
    }

    public function test_account_dialplan_takes_precedence_over_global_alias(): void
    {
        $this->dialplan('+12125550100', 'call_flow_uuid='.self::DOMAIN, null, 'Global');
        $this->dialplan('12125550100', 'call_flow_uuid='.self::ATTEMPT, self::DOMAIN, 'Account');
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$this->profile('12125550100')]))->first();
        $this->assertSame('Account', $step['dialplan_name']);
        $this->assertSame('call_flow', $step['dialplan_app_type']);
    }

    public function test_saved_route_identity_survives_reconfigured_or_deleted_dialplan(): void
    {
        $this->dialplan('9400', 'ring_group_uuid='.self::OTHER_DOMAIN, self::DOMAIN, 'Replacement');
        $profile = $this->profile('9400');
        $profile['extension']['application'] = $this->application('call_center_queue_uuid='.self::ATTEMPT);
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$profile]))->first();
        $this->assertSame('contact_center_queue', $step['dialplan_app_type']);
        $this->assertSame('9400', $step['dialplan_name']);
        DB::table('v_dialplans')->delete();
        $this->assertSame($step, (new CdrDataService())->buildCallFlowSummary($this->cdr([$profile]))->first());
    }

    public function test_only_the_uniquely_identified_final_queue_gets_the_cdr_outcome(): void
    {
        $first = $this->profile('9400');
        $first['extension']['application'] = $this->application('call_center_queue_uuid='.self::OTHER_DOMAIN);
        $last = $this->profile('9401', 1010, 1020);
        $last['extension']['application'] = $this->application('call_center_queue_uuid='.self::ATTEMPT);
        $cdr = $this->cdr([$first, $last], ['call_center_queue_uuid' => self::ATTEMPT, 'cc_cause' => 'answered']);
        $service = new CdrDataService();
        $this->assertSame(['Unknown', 'Answered'], $service->buildCallFlowSummary($cdr)->pluck('queue_result')->all());
        $cdr->call_flow = json_encode([$last, $last]);
        $this->assertSame(['Unknown', 'Unknown'], $service->buildCallFlowSummary($cdr)->pluck('queue_result')->all());
    }

    public function test_ring_group_is_split_once_even_with_repeated_assignments(): void
    {
        $profile = $this->profile('9400', 1000, 1020);
        $profile['caller_profile']['callee_id_number'] = '100';
        $profile['times']['bridged_time'] = 1005000000;
        $application = $this->application('ring_group_uuid='.self::ATTEMPT);
        $profile['extension']['application'] = [$application, $application];
        $steps = (new CdrDataService())->buildCallFlowSummary($this->cdr([$profile]));
        $this->assertSame(['ring_group', 'extension'], $steps->pluck('dialplan_app_type')->all());
        $this->assertSame(['9400', '100'], $steps->pluck('destination_number')->all());
        $this->assertSame([5, 15], $steps->pluck('duration_seconds')->all());
    }

    public function test_invalid_timestamps_and_malformed_profiles_do_not_invent_durations(): void
    {
        $steps = (new CdrDataService())->buildCallFlowSummary($this->cdr([
            $this->profile('100', 1000, 0), $this->profile('100', 1005, 1001),
            ['caller_profile' => ['destination_number' => '101']], 'invalid',
        ]));
        $this->assertCount(3, $steps);
        $this->assertSame([null, null, null], $steps->pluck('duration_seconds')->all());
        $this->assertSame(['Unknown', 'Unknown', 'Unknown'], $steps->pluck('duration_formatted')->all());
        $this->assertSame('--:--', $steps[2]['time_line']);
    }

    public function test_lookup_maps_do_not_survive_into_another_timeline_request(): void
    {
        $service = new CdrDataService();
        $cdr = $this->cdr([$this->profile('100')]);
        $this->assertSame('David Duck', $service->buildCallFlowSummary($cdr)[0]['dialplan_name']);
        DB::table('v_extensions')->where('domain_uuid', self::DOMAIN)->update(['effective_caller_id_name' => 'New name']);
        $this->assertSame('New name', $service->buildCallFlowSummary($cdr)[0]['dialplan_name']);
    }

    public function test_callback_projection_omits_raw_profiles_but_ordinary_projection_keeps_them(): void
    {
        $cdr = $this->cdr([$this->profile('100')]);
        DB::table('v_xml_cdr')->insert($cdr->getAttributes());
        $service = new CdrDataService();
        $this->assertSame($cdr->call_flow, CDR::query()->select($service->timelineSelectColumns())->first()->call_flow);
        DB::table('v_xml_cdr')->update(['cc_callback_attempt_uuid' => self::ATTEMPT, 'cc_callback_role' => 'agent']);
        $this->assertNull(CDR::query()->select($service->timelineSelectColumns())->first()->call_flow);
        Schema::table('v_xml_cdr', fn (Blueprint $table) => $table->dropColumn(['cc_callback_attempt_uuid', 'cc_callback_role']));
        $this->assertSame($cdr->call_flow, CDR::query()->select($service->timelineSelectColumns())->first()->call_flow);
    }

    public function test_short_extensions_never_match_a_dialplan_with_a_prefixed_one(): void
    {
        $this->dialplan('1100', 'call_center_queue_uuid='.self::ATTEMPT);
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$this->profile('100')]))->first();
        $this->assertSame('extension', $step['dialplan_app_type']);
        $this->assertSame('David Duck', $step['dialplan_name']);
    }

    public function test_park_voicemail_and_intercept_steps_keep_their_feature_identity(): void
    {
        $steps = (new CdrDataService())->buildCallFlowSummary($this->cdr([
            $this->profile('park+*5901'), $this->profile('*99100'), $this->profile('*97101^100'),
        ]));
        $this->assertSame(['park', 'voicemail', 'call_intercept'], $steps->pluck('dialplan_app_type')->all());
        $this->assertSame(['5901', '100', 'David Duck (100)'], $steps->pluck('dialplan_name')->all());
    }

    public function test_empty_xml_elements_are_normalized_before_building_steps(): void
    {
        $profile = $this->profile('100');
        $profile['caller_profile']['callee_id_number'] = [];
        $profile['caller_profile']['source'] = [];
        $profile['caller_profile']['originator']['originator_caller_profile']['destination_number'] = [];
        $profile['extension']['application'] = ['@attributes' => ['app_name' => 'set', 'app_data' => []]];
        $step = (new CdrDataService())->buildCallFlowSummary($this->cdr([$profile]))->first();
        $this->assertSame('100', $step['destination_number']);
        $this->assertSame('extension', $step['dialplan_app_type']);
    }

    public function test_callback_request_status_and_queue_result_preserve_the_original_agent_outcome(): void
    {
        $profile = $this->profile('9400', 1000, 1038);
        $profile['extension']['application'] = $this->application('call_center_queue_uuid='.self::ATTEMPT);
        $cdr = $this->cdr([$profile], [
            'status' => 'callback_requested', 'missed_call' => true, 'voicemail_message' => false,
            'hangup_cause' => 'NORMAL_CLEARING', 'call_center_queue_uuid' => self::ATTEMPT,
            'cc_cause' => 'cancel', 'cc_cancel_reason' => 'EXIT_WITH_KEY',
        ]);
        $agent = $this->cdr([$this->profile('100', 1005, 1007)], [
            'xml_cdr_uuid' => self::OTHER_DOMAIN, 'originating_leg_uuid' => $cdr->getKey(),
            'status' => 'cancelled', 'direction' => 'outbound', 'hangup_cause' => 'ORIGINATOR_CANCEL',
        ]);
        DB::table('v_xml_cdr')->insert($agent->getAttributes());
        $service = new CdrDataService();
        $steps = $service->buildCallFlowSummary($cdr);
        $this->assertSame('callback_requested', $cdr->status);
        $this->assertSame('Callback requested', $cdr->cc_result);
        $this->assertSame('Callback requested', $steps[0]['queue_result']);
        $this->assertSame('The call was canceled before it was answered.', $steps[1]['call_disposition']);
        $this->assertSame('Callback requested', $service->buildApiCallFlowData($cdr)[0]->queue_result);
        $cdr->status = 'missed';
        $this->assertSame('missed call', $cdr->status);
        $this->assertSame('The caller pressed the exit key', $cdr->cc_result);
    }

    public function test_callback_filter_and_legacy_status_filters_agree_with_the_displayed_status(): void
    {
        $callback = $this->cdr([], ['status' => 'callback_requested', 'missed_call' => true,
            'voicemail_message' => false, 'hangup_cause' => 'NORMAL_CLEARING']);
        DB::table('v_xml_cdr')->insert($callback->getAttributes());
        DB::table('v_xml_cdr')->insert(array_replace($callback->getAttributes(), [
            'xml_cdr_uuid' => self::OTHER_DOMAIN, 'status' => 'missed',
        ]));
        $method = new \ReflectionMethod(CdrDataService::class, 'applyStatusFilter');
        foreach (['callback_requested' => [$callback->getKey()], 'missed call' => [self::OTHER_DOMAIN],
            'answered' => [], 'abandoned' => []] as $filter => $expected) {
            $query = CDR::query();
            $method->invoke(new CdrDataService(), $query, $filter);
            $this->assertSame($expected, $query->pluck('xml_cdr_uuid')->all());
        }
    }

    private function cdr(array $profiles, array $attributes = []): CDR
    {
        return (new CDR())->forceFill(array_replace([
            'xml_cdr_uuid' => '88888888-1111-4111-8111-111111111111', 'domain_uuid' => self::DOMAIN,
            'direction' => 'inbound', 'start_epoch' => 1000, 'end_epoch' => 1100,
            'call_flow' => json_encode($profiles),
        ], $attributes));
    }

    private function profile(string $number, int $start = 1000, int $end = 1010): array
    {
        return ['caller_profile' => ['destination_number' => $number, 'callee_id_number' => $number, 'context' => 'audit.example'],
            'times' => ['profile_created_time' => $start * 1000000, 'profile_end_time' => $end * 1000000]];
    }

    private function application(string $data): array
    {
        return ['@attributes' => ['app_name' => 'set', 'app_data' => $data]];
    }

    private function dialplan(string $number, string $xml, ?string $domain = self::DOMAIN, string $name = 'Test'): void
    {
        DB::table('v_dialplans')->insert([
            'dialplan_uuid' => \Illuminate\Support\Str::uuid()->toString(), 'domain_uuid' => $domain,
            'dialplan_context' => 'audit.example', 'dialplan_number' => $number, 'dialplan_name' => $name,
            'dialplan_enabled' => 'true', 'dialplan_order' => '100', 'dialplan_xml' => $xml,
        ]);
    }
}
