<?php

namespace Tests\Feature;

use App\Jobs\CheckStuckFaxesJob;
use App\Jobs\HandleFaxTxEventJob;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxNotificationJob;
use App\Models\OutboundFax;
use App\Services\FreeswitchEslService;
use App\Services\Ha\ActiveNodeResolver;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckStuckFaxesJobTest extends TestCase
{
    private const FAX = '00000000-0000-4000-8000-000000000001';
    private const DOMAIN = '00000000-0000-4000-8000-000000000002';
    private const ATTEMPT = '00000000-0000-4000-8000-000000000003';
    private const SETUP = '00000000-0000-4000-8000-000000000004';
    private const CARRIER = '00000000-0000-4000-8000-000000000005';
    private const OTHER = '00000000-0000-4000-8000-000000000006';

    private ActiveNodeResolver $nodes;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'fax_test',
            'database.connections.fax_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'cache.default' => 'array',
            'logging.default' => 'null',
        ]);
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));
        $this->nodes = $this->mock(ActiveNodeResolver::class);
        $this->nodes->allows('hostFingerprint')->andReturn('local');

        DB::statement('CREATE TABLE outbound_faxes (
            outbound_fax_uuid TEXT PRIMARY KEY, domain_uuid TEXT, fax_uuid TEXT, source TEXT, destination TEXT,
            file_path TEXT, status TEXT, retry_count INTEGER, retry_limit INTEGER, retry_at DATETIME, command TEXT,
            call_uuid TEXT, current_attempt_uuid TEXT, total_pages INTEGER, response TEXT, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE v_fax_logs (
            fax_log_uuid TEXT PRIMARY KEY, domain_uuid TEXT, fax_uuid TEXT, outbound_fax_uuid TEXT,
            outbound_fax_attempt_uuid TEXT, source TEXT, destination TEXT, fax_success TEXT, fax_result_code INTEGER,
            fax_result_text TEXT, fax_file TEXT, fax_retry_attempts INTEGER, fax_retry_limit INTEGER,
            fax_ecm_used TEXT, fax_local_station_id TEXT, fax_document_transferred_pages INTEGER,
            fax_document_total_pages INTEGER, fax_image_resolution TEXT, fax_image_size INTEGER,
            fax_bad_rows INTEGER, fax_transfer_rate INTEGER, fax_uri TEXT, fax_duration INTEGER,
            fax_date DATETIME, fax_epoch INTEGER
        )');
        DB::statement('CREATE TABLE v_fax_files (
            fax_file_uuid TEXT PRIMARY KEY, domain_uuid TEXT, fax_uuid TEXT, fax_mode TEXT, fax_destination TEXT,
            fax_file_type TEXT, fax_file_path TEXT, fax_caller_id_name TEXT, fax_caller_id_number TEXT,
            fax_date DATETIME, fax_epoch INTEGER
        )');
        $this->seedFax();
        (require database_path('migrations/2026_11_25_000001_add_watchdog_fields_to_outbound_faxes.php'))->up();
        $this->assertNull($this->fax()->sending_host);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->updateFax(['sending_host' => 'local']);
    }

    private function seedFax(array $overrides = []): void
    {
        DB::table('outbound_faxes')->insert(array_replace([
            'outbound_fax_uuid' => self::FAX, 'domain_uuid' => self::DOMAIN, 'fax_uuid' => self::OTHER,
            'current_attempt_uuid' => self::ATTEMPT, 'call_uuid' => self::SETUP,
            'source' => '1000', 'destination' => '5550100', 'file_path' => '/fax/sent/example.tif',
            'status' => 'sending', 'total_pages' => 33, 'retry_count' => 1, 'retry_limit' => 3,
            'retry_at' => now()->subHour(),
        ], $overrides));
    }

    private function updateFax(array $fields): void
    {
        OutboundFax::whereKey(self::FAX)->update($fields);
    }

    private function fax(): OutboundFax
    {
        return OutboundFax::findOrFail(self::FAX);
    }

    private function dump(array $fields = []): array
    {
        return array_replace([
            'Unique-ID' => self::CARRIER, 'variable_current_application' => 'txfax',
            'variable_outbound_fax_uuid' => self::FAX, 'variable_outbound_fax_attempt_uuid' => self::ATTEMPT,
            'variable_domain_uuid' => self::DOMAIN,
        ], $fields);
    }

    private function delivered(array $fields = []): array
    {
        return $this->dump(array_replace([
            'variable_fax_success' => '1', 'variable_fax_result_code' => '0',
            'variable_fax_result_text' => 'OK', 'variable_fax_document_total_pages' => '33',
            'variable_fax_document_transferred_pages' => '33', 'variable_fax_transfer_rate' => '14400',
        ], $fields));
    }

    private function listed(int $age = 3600): array
    {
        return ['row_count' => 1, 'rows' => [[
            'uuid' => self::CARRIER, 'application' => 'txfax', 'created_epoch' => (string) (now()->timestamp - $age),
        ]]];
    }

    private function webhook(array $fields = []): void
    {
        (new HandleFaxTxEventJob(array_replace([
            'domain_uuid' => self::DOMAIN, 'outbound_fax_uuid' => self::FAX,
            'outbound_fax_attempt_uuid' => self::ATTEMPT, 'fax_success' => '1', 'fax_result_code' => '0',
            'fax_result_text' => 'OK', 'fax_document_total_pages' => '33', 'fax_document_transferred_pages' => '33',
        ], $fields)))->handle();
    }

    /** Every allowed ESL command must consume exactly the supplied replies. */
    private function scan(mixed $list, array $commands = []): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->expects('isConnected')->andReturn(true);
        $esl->expects('disconnect');
        $commands['show channels as json'] = [$list];
        $errors = [];
        $esl->allows('executeCommand')->andReturnUsing(function ($command, $disconnect) use (&$commands, &$errors) {
            if ($disconnect !== false || empty($commands[$command])) {
                $errors[] = "Unexpected command: $command";
                return null;
            }
            $reply = array_shift($commands[$command]);
            try {
                return $reply instanceof Closure ? $reply() : $reply;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                return null;
            }
        });
        (new CheckStuckFaxesJob())->handle($esl, $this->nodes);
        $this->assertSame([], $errors);
        $this->assertSame([], array_keys(array_filter($commands)), 'Unconsumed ESL replies');
    }

    private function scanCarrier(array $dumps, array $hangups = [], int $age = 3600): void
    {
        $commands = ['uuid_dump '.self::CARRIER.' json' => $dumps];
        if ($hangups) {
            $commands['uuid_kill '.self::CARRIER] = $hangups;
        }
        $this->scan($this->listed($age), $commands);
    }

    private function scanAbsent(mixed $setupReply = false): void
    {
        $commands = $this->fax()->call_uuid === null ? [] : ['uuid_exists '.self::SETUP => [$setupReply]];
        $this->scan(['row_count' => 0], $commands);
    }

    private function assertSending(): void
    {
        $this->assertSame('sending', $this->fax()->status);
        $this->assertDatabaseCount('v_fax_logs', 0);
        $this->assertDatabaseCount('v_fax_files', 0);
        Queue::assertNothingPushed();
    }

    #[DataProvider('budgets')]
    public function test_live_carrier_gets_the_current_attempts_time_budget(?int $pages, int $seconds, bool $overdue): void
    {
        // The setup channel has bowed out. Its boolean false reply by itself cannot end this attempt.
        $this->updateFax(['total_pages' => $pages, 'retry_at' => now()->subSeconds($seconds)]);
        $this->scanAbsent(false);
        $this->assertNotNull($this->fax()->channel_missing_since);
        $dump = $this->dump();
        $this->scanCarrier($overdue ? [$dump, $dump] : [$dump], $overdue ? ['+OK'] : []);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->assertSending();
    }

    public static function budgets(): array
    {
        return [
            'one page just before floor' => [1, 899, false], 'floor reached' => [1, 900, true],
            '20 pages before timeout' => [20, 1799, false], '20 pages at timeout' => [20, 1800, true],
            'unknown pages before six hours' => [null, 21599, false], 'unknown pages at six hours' => [null, 21600, true],
        ];
    }

    public function test_watchdog_commits_delivery_before_hangup_and_late_webhook_is_idempotent(): void
    {
        $this->updateFax(['sending_host' => 'peer']);
        $delivered = $this->delivered(['variable_end_epoch' => '0', 'variable_fax_file' => '']);
        $this->scanCarrier([$delivered, $delivered], [function () {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('sent', $this->fax()->status);
            $this->assertDatabaseCount('v_fax_files', 1);
            Queue::assertPushed(SendFaxNotificationJob::class, 1);
            return '-ERR unable to release';
        }]);
        $this->webhook();
        $this->travel(5)->minutes();
        $this->scanCarrier([$delivered, $delivered], ['+OK']);
        $this->assertDatabaseCount('v_fax_files', 1);
        $this->assertDatabaseCount('v_fax_logs', 1);
        $this->assertDatabaseHas('v_fax_logs', [
            'outbound_fax_attempt_uuid' => self::ATTEMPT, 'fax_success' => '1', 'fax_result_code' => 0,
            'fax_result_text' => 'OK', 'fax_document_total_pages' => 33, 'fax_epoch' => now()->subMinutes(5)->timestamp,
            'fax_document_transferred_pages' => 33, 'fax_transfer_rate' => 14400,
        ]);
        Queue::assertPushed(SendFaxNotificationJob::class, 1);
        Queue::assertNotPushed(SendFaxJob::class);
    }

    public function test_sent_retry_only_releases_the_verified_original_carrier_after_its_own_timeout(): void
    {
        $this->updateFax(['current_attempt_uuid' => self::OTHER, 'retry_count' => 2, 'retry_at' => now()]);
        $this->webhook(['outbound_fax_attempt_uuid' => self::OTHER]);
        DB::table('v_fax_logs')->insert([
            'fax_log_uuid' => (string) Str::uuid(), 'domain_uuid' => self::DOMAIN, 'outbound_fax_uuid' => self::FAX,
            'outbound_fax_attempt_uuid' => self::ATTEMPT, 'fax_success' => '0', 'fax_result_text' => 'Old watchdog timeout',
        ]);
        $history = DB::table('v_fax_logs')->orderBy('fax_log_uuid')->get();
        $delivered = $this->delivered();
        $this->scanCarrier([$delivered], [], 2969);
        $this->scanCarrier([$delivered, $delivered], ['+OK'], 2970);
        $this->webhook(); // The old carrier's hangup.
        $this->assertEquals($history, DB::table('v_fax_logs')->orderBy('fax_log_uuid')->get());
        $this->assertSame('sent', $this->fax()->status);
        $this->assertSame(self::OTHER, $this->fax()->current_attempt_uuid);
        $this->assertDatabaseCount('v_fax_files', 1);
        Queue::assertPushed(SendFaxNotificationJob::class, 1);
        Queue::assertNotPushed(SendFaxJob::class);
    }

    #[DataProvider('foreignIdentities')]
    public function test_unrelated_channels_at_the_same_destination_are_left_alone(string $field): void
    {
        $this->seedFax(['outbound_fax_uuid' => self::OTHER, 'current_attempt_uuid' => (string) Str::uuid(),
            'sending_host' => 'peer']);
        $this->updateFax(['sending_host' => 'peer']);
        $this->scanCarrier([$this->delivered([$field => self::OTHER])]);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->assertSending();
    }

    public static function foreignIdentities(): array
    {
        return [['variable_domain_uuid'], ['variable_outbound_fax_uuid'], ['variable_outbound_fax_attempt_uuid']];
    }

    public function test_hangup_acknowledgement_never_retries_a_live_attempt(): void
    {
        $dump = $this->dump();
        foreach (['-ERR could not hang up', '+OK', null] as $reply) {
            $this->scanCarrier([$dump, $dump], [$reply]);
            $this->assertNull($this->fax()->channel_missing_since);
            $this->assertSending();
            $this->travel(5)->minutes();
        }
        $this->scanAbsent();
        $this->assertSending();
        $this->travel(5)->minutes();
        $this->webhook();
        $this->scan(['row_count' => 0]);
        Queue::assertNotPushed(SendFaxJob::class);
        $this->assertSame('sent', $this->fax()->status);
    }

    public function test_refresh_before_hangup_can_discover_delivery_or_defer_an_inconsistent_result(): void
    {
        $partial = $this->delivered(['variable_fax_document_transferred_pages' => '32']);
        $this->scanCarrier([$partial]);
        $this->scanCarrier([$this->dump(), $partial]);
        $this->scanCarrier([$this->dump(), $this->delivered(['variable_fax_result_code' => '2'])]);
        $this->scanCarrier([$this->dump(), $this->dump(['variable_domain_uuid' => self::OTHER])]);
        $this->assertSending();
        $this->scanCarrier([$this->dump(), $this->delivered()], ['+OK']);
        $this->assertSame('sent', $this->fax()->status);
        Queue::assertNotPushed(SendFaxJob::class);
    }

    #[DataProvider('badLists')]
    public function test_an_incomplete_list_cannot_confirm_a_second_absence(mixed $list): void
    {
        $this->scanAbsent();
        $this->travel(10)->minutes();
        $this->scan($list);
        $this->assertSending();
    }

    public static function badLists(): array
    {
        return [[null], ['-ERR channels unavailable'], [[]], [['row_count' => 1]], [['rows' => []]],
            [['row_count' => 0, 'rows' => null]], [['row_count' => 1, 'rows' => [['uuid' => self::CARRIER]]]]];
    }

    public function test_incomplete_dumps_and_ambiguous_carriers_cannot_authorize_action(): void
    {
        $this->scanAbsent();
        $this->travel(5)->minutes();
        foreach ([null, '-ERR No such channel', [], $this->dump(['Unique-ID' => self::OTHER]),
            $this->dump(['variable_current_application' => 'bridge']),
            $this->dump(['variable_outbound_fax_attempt_uuid' => ''])] as $bad) {
            $this->scanCarrier([$bad]);
        }
        $list = $this->listed();
        $list['rows'][] = array_replace($list['rows'][0], ['uuid' => self::OTHER]);
        $list['row_count']++;
        $this->scan($list, [
            'uuid_dump '.self::CARRIER.' json' => [$this->delivered()],
            'uuid_dump '.self::OTHER.' json' => [$this->delivered(['Unique-ID' => self::OTHER])],
        ]);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->assertSending();
    }

    public function test_a_live_setup_leg_must_still_belong_to_this_attempt_before_release(): void
    {
        $this->scanAbsent();
        $setup = $this->dump(['Unique-ID' => self::SETUP, 'variable_current_application' => 'bridge']);
        foreach (['variable_domain_uuid', 'variable_outbound_fax_uuid', 'variable_outbound_fax_attempt_uuid'] as $key) {
            $this->scan(['row_count' => 0], [
                'uuid_exists '.self::SETUP => ['true'],
                'uuid_dump '.self::SETUP.' json' => [array_replace($setup, [$key => self::OTHER])],
            ]);
        }
        $this->scan(['row_count' => 0], [
            'uuid_exists '.self::SETUP => [true], 'uuid_dump '.self::SETUP.' json' => [$setup],
            'uuid_kill '.self::SETUP => ['+OK'],
        ]);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->assertSending();
    }

    public function test_ambiguous_existence_replies_cannot_start_or_finish_the_grace_period(): void
    {
        foreach ([null, '-ERR false', 'not false', '', ['0'], 2] as $bad) {
            $this->scanAbsent($bad);
        }
        $this->assertNull($this->fax()->channel_missing_since);
        $this->scanAbsent(' FALSE ');
        $this->travel(5)->minutes();
        $this->scanAbsent('-ERR');
        $this->assertSending();
    }

    public function test_esl_client_preserves_false_and_zero_from_the_wire(): void
    {
        $client = Mockery::mock(FreeswitchEslService::class)->makePartial();
        $event = Mockery::mock();
        $event->expects('getBody')->times(2)->andReturn('false', '0');
        $this->assertFalse($client->convertEslResponse($event));
        $this->assertSame(0, $client->convertEslResponse($event));
    }

    public function test_setup_bowout_during_the_second_scan_restarts_the_grace_period(): void
    {
        $this->scanAbsent();
        $this->travel(5)->minutes();
        $listed = ['row_count' => 1, 'rows' => [['uuid' => self::SETUP, 'application' => 'bridge']]];
        $this->scan($listed, ['uuid_exists '.self::SETUP => [false]]);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->scanAbsent();
        $this->assertTrue($this->fax()->channel_missing_since->equalTo(now()));
        $this->travel(5)->minutes();
        $this->scan(array_replace($listed, ['row_count' => 2]));
        $this->assertNull($this->fax()->channel_missing_since);
        $this->assertSending();
    }

    public function test_absence_grace_starts_when_the_setup_probe_confirms_absence(): void
    {
        $this->scanAbsent(function () {
            $this->travel(30)->seconds();
            return false;
        });
        $this->assertTrue($this->fax()->channel_missing_since->equalTo(now()));
        $this->travel(270)->seconds();
        $this->scanAbsent();
        $this->assertSending();
    }

    #[DataProvider('missingAttempts')]
    public function test_two_absences_use_normal_retry_backoff_or_terminal_notification(?string $setup, int $count): void
    {
        $this->updateFax(['call_uuid' => $setup, 'retry_count' => $count]);
        $this->scanAbsent(0);
        $this->assertTrue($this->fax()->channel_missing_since->equalTo(now()));
        $this->travel(299)->seconds();
        $this->scanAbsent(false);
        $this->assertSending();
        $this->travel(1)->seconds();
        $this->scanAbsent('0');
        $this->webhook(['fax_success' => '0']); // duplicate failure after recovery
        $this->assertDatabaseCount('v_fax_logs', 1);
        if ($count === 3) {
            $this->assertSame('failed', $this->fax()->status);
            Queue::assertPushed(SendFaxNotificationJob::class, 1);
            Queue::assertNotPushed(SendFaxJob::class);
        } else {
            $this->assertSame('trying', $this->fax()->status);
            Queue::assertPushed(SendFaxJob::class, 1);
            Queue::assertPushed(SendFaxJob::class, fn ($job) => $job->delay->equalTo(now()->addSeconds(90)));
            Queue::assertNotPushed(SendFaxNotificationJob::class);
        }
    }

    public static function missingAttempts(): array
    {
        return [['setup' => self::SETUP, 'count' => 2], ['setup' => null, 'count' => 3]];
    }

    public function test_webhook_wins_during_the_second_absence_check(): void
    {
        $this->scanAbsent();
        $this->travel(5)->minutes();
        $this->scanAbsent(function () {
            $this->webhook();
            return false;
        });
        $this->assertSame('sent', $this->fax()->status);
        $this->assertDatabaseCount('v_fax_files', 1);
        $this->assertDatabaseCount('v_fax_logs', 1);
        Queue::assertPushed(SendFaxNotificationJob::class, 1);
        Queue::assertNotPushed(SendFaxJob::class);
    }

    public function test_recorded_success_is_recovered_under_lock_before_any_missing_result_retry(): void
    {
        DB::table('v_fax_logs')->insert([
            'fax_log_uuid' => (string) Str::uuid(), 'domain_uuid' => self::DOMAIN, 'fax_uuid' => self::OTHER,
            'outbound_fax_uuid' => self::FAX, 'outbound_fax_attempt_uuid' => self::ATTEMPT,
            'fax_success' => '1', 'fax_file' => '/fax/sent/example.tif',
        ]);
        $this->scanAbsent();
        $this->travel(5)->minutes();
        $this->scanAbsent();
        $this->assertSame('sent', $this->fax()->status);
        $this->assertDatabaseCount('v_fax_files', 1);
        $this->assertDatabaseCount('v_fax_logs', 1);
        Queue::assertNotPushed(SendFaxJob::class);
    }

    public function test_absence_is_local_to_sender_or_verified_standalone_for_unowned_rows(): void
    {
        $this->updateFax(['sending_host' => 'peer']);
        $this->scan(['row_count' => 0]);
        $this->assertNull($this->fax()->channel_missing_since);
        $this->updateFax(['sending_host' => null, 'call_uuid' => null]);
        $standalone = ['source' => 'standalone', 'subscription_discovery' => ['readable' => true, 'subscriptions' => []]];
        $withSubscription = $standalone;
        $withSubscription['subscription_discovery']['subscriptions'][] = ['hosts' => []];
        $this->nodes->expects('resolve')->times(5)->andReturn(['source' => 'setting'], ['source' => 'database'],
            $withSubscription, $standalone, $standalone);
        $this->scanAbsent();
        $this->scanAbsent();
        $this->scanAbsent();
        $this->assertNull($this->fax()->channel_missing_since);
        $this->scanAbsent();
        $this->assertNotNull($this->fax()->channel_missing_since);
        $this->travel(5)->minutes();
        $this->scanAbsent();
        Queue::assertPushed(SendFaxJob::class, 1);
    }

    public function test_stale_unidentified_and_duplicate_failure_webhooks(): void
    {
        $this->webhook(['outbound_fax_attempt_uuid' => self::OTHER]);
        $this->webhook(['outbound_fax_attempt_uuid' => null]);
        $this->webhook(['domain_uuid' => self::OTHER]);
        $this->assertSending();
        DB::transaction(function () {
            $this->webhook(['fax_success' => '0', 'hangup_cause_q850' => '17']);
            $this->webhook(['fax_success' => '0', 'hangup_cause_q850' => '17']);
            Queue::assertNothingPushed();
        });
        $this->assertSame('busy', $this->fax()->status);
        $this->assertDatabaseCount('v_fax_logs', 1);
        $this->assertDatabaseCount('v_fax_files', 0);
        Queue::assertPushed(SendFaxJob::class, 1);
    }

    public function test_phase_d_recovers_pending_dispatch_even_when_esl_is_unavailable(): void
    {
        $this->updateFax(['status' => 'waiting']);
        $this->scan(null);
        Queue::assertPushed(SendFaxJob::class, 1);
    }

    #[DataProvider('originateRaces')]
    public function test_originate_responses_only_update_the_claimed_sending_attempt(string $race, ?string $reply): void
    {
        DB::statement('CREATE TABLE v_fax (fax_uuid TEXT, domain_uuid TEXT)');
        DB::statement('CREATE TABLE v_domains (domain_uuid TEXT, domain_name TEXT)');
        DB::statement('CREATE TABLE v_extensions (domain_uuid TEXT, extension TEXT)');
        DB::table('v_fax')->insert(['fax_uuid' => self::OTHER, 'domain_uuid' => self::DOMAIN]);
        DB::table('v_domains')->insert(['domain_uuid' => self::DOMAIN, 'domain_name' => 'fax.example.test']);
        DB::table('v_extensions')->insert(['domain_uuid' => self::DOMAIN, 'extension' => '5550100']);
        cache()->put('fax_dialplan_variables', []);
        $limiter = Mockery::mock();
        Redis::expects('throttle')->with('fax-originate')->andReturn($limiter);
        foreach (['allow', 'every', 'block'] as $method) {
            $limiter->expects($method)->andReturnSelf();
        }
        $limiter->expects('then')->andReturnUsing(fn ($run) => $run());
        $this->updateFax(['status' => 'trying', 'sending_host' => 'peer', 'response' => 'previous',
            'channel_missing_since' => now()->subMinutes(10)]);

        $claimed = null;
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->expects('isConnected')->andReturn(true);
        $esl->expects('executeCommand')->andReturnUsing(function ($command) use ($race, $reply, &$claimed) {
            $claimed = $this->fax();
            if ($race === 'completed') {
                $this->webhook(['outbound_fax_attempt_uuid' => $claimed->current_attempt_uuid]);
            } elseif ($race === 'superseded') {
                $this->updateFax(['current_attempt_uuid' => self::OTHER, 'call_uuid' => self::SETUP, 'response' => 'new attempt']);
            }
            if ($reply === 'disconnect') {
                throw new \RuntimeException('Connection lost');
            }
            return $reply;
        });
        (new SendFaxJob(self::FAX))->handle($esl);

        $this->assertSame('sending', $claimed->status);
        $this->assertSame('local', $claimed->sending_host);
        $this->assertNull($claimed->channel_missing_since);
        $this->assertNotSame(self::ATTEMPT, $claimed->current_attempt_uuid);
        $this->assertSame(2, $claimed->retry_count);
        $this->assertStringContainsString('origination_uuid='.$claimed->call_uuid, $claimed->command);
        $this->assertStringContainsString('outbound_fax_attempt_uuid='.$claimed->current_attempt_uuid, $claimed->command);

        if ($race === 'completed') {
            $this->assertSame('sent', $this->fax()->status);
            $this->assertSame($claimed->response, $this->fax()->response);
            $this->assertDatabaseCount('v_fax_logs', 1);
            $this->assertDatabaseCount('v_fax_files', 1);
            Queue::assertPushed(SendFaxNotificationJob::class, 1);
        } elseif ($race === 'superseded') {
            $this->assertSame(self::OTHER, $this->fax()->current_attempt_uuid);
            $this->assertSame(self::SETUP, $this->fax()->call_uuid);
            $this->assertSame('new attempt', $this->fax()->response);
            $this->assertSending();
        } elseif ($reply === '-ERR rejected') {
            $this->assertSame('trying', $this->fax()->status);
            $this->assertDatabaseHas('v_fax_logs', ['outbound_fax_attempt_uuid' => $claimed->current_attempt_uuid,
                'fax_result_text' => 'Originate rejected: -ERR rejected']);
        } else {
            $this->assertSame($claimed->call_uuid, $this->fax()->call_uuid);
            $this->assertSending();
        }
        Queue::assertNotPushed(SendFaxJob::class);
    }

    public static function originateRaces(): array
    {
        return [
            ['completed', '+OK'], ['completed', '-ERR rejected'], ['superseded', '+OK'],
            ['superseded', '-ERR rejected'], ['superseded', 'disconnect'], ['none', '-ERR rejected'],
            ['none', null], ['none', 'disconnect'],
        ];
    }
}
