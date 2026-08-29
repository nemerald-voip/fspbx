<?php

namespace Tests\Unit;

use App\Models\SipProfiles;
use App\Services\DialplanService;
use App\Services\SofiaProfileRuntimeService;
use App\Services\SipCaptureService;
use App\Services\SwitchVariableService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SipCaptureServiceTest extends TestCase
{
    public function test_it_builds_the_homer_hep_three_capture_server_value(): void
    {
        $service = $this->service();

        $this->assertSame(
            'udp:212.56.38.50:9060;hep=3;capture_id=$${hep_capture_id}',
            $service->captureServerValue('UDP', '212.56.38.50', 9060)
        );
    }

    public function test_it_wraps_ipv6_collector_addresses(): void
    {
        $service = $this->service();

        $this->assertSame(
            'tcp:[2001:db8::10]:9060;hep=3;capture_id=$${hep_capture_id}',
            $service->captureServerValue('tcp', '2001:db8::10', 9060)
        );
    }

    public function test_it_parses_complete_and_legacy_capture_server_values(): void
    {
        $service = $this->service();

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => 'homer.example.com',
            'collector_port' => 9060,
            'capture_id' => 305,
        ], $service->parseCaptureServerValue('udp:homer.example.com:9060;hep=3;capture_id=305'));

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => '127.0.0.1',
            'collector_port' => 9060,
            'capture_id' => null,
        ], $service->parseCaptureServerValue('udp:127.0.0.1:9060'));

        $this->assertSame([
            'transport' => 'udp',
            'collector_host' => 'homer.example.com',
            'collector_port' => 9060,
            'capture_id' => null,
        ], $service->parseCaptureServerValue(
            'udp:homer.example.com:9060;hep=3;capture_id=$${hep_capture_id}'
        ));
    }

    public function test_it_retries_random_capture_ids_that_are_already_used(): void
    {
        $service = new TestableSipCaptureService([101, 3000000001]);

        $this->assertSame(3000000001, $service->availableCaptureId([101, 102]));
    }

    public function test_it_only_replaces_the_old_managed_default_capture_id(): void
    {
        $service = new TestableSipCaptureService([]);

        $this->assertTrue($service->replacesLegacyCaptureId(
            100,
            SipCaptureService::LEGACY_MANAGED_DESCRIPTION,
        ));
        $this->assertFalse($service->replacesLegacyCaptureId(
            101,
            SipCaptureService::LEGACY_MANAGED_DESCRIPTION,
        ));
        $this->assertFalse($service->replacesLegacyCaptureId(100, 'Configured manually.'));
    }

    public function test_enabling_capture_ensures_the_correlation_dialplan(): void
    {
        $this->withSqliteDatabase(function () {
            $service = new TestableSipCaptureSaveFlowService();

            $service->save(['enabled' => false]);
            $this->assertSame(0, $service->correlationDialplanChecks);
            $this->assertSame([false], $service->rtcpDialplanStates);
            $this->assertSame(1, $service->profileLockCount);

            $service->save([
                'enabled' => true,
                'transport' => 'udp',
                'collector_host' => 'homer.example.com',
                'collector_port' => 9060,
                'profile_uuids' => [TestableSipCaptureSaveFlowService::PROFILE_UUID],
            ]);

            $this->assertSame(1, $service->correlationDialplanChecks);
            $this->assertSame([false, true], $service->rtcpDialplanStates);
            $this->assertSame(2, $service->profileLockCount);
        });
    }

    public function test_it_creates_repairs_and_removes_the_managed_rtcp_event_dialplan(): void
    {
        $this->withSqliteDatabase(function () {
            $this->createDialplanSchema();
            $service = new TestableCorrelationDialplanSipCaptureService();

            $service->synchronizeRtcpEventDialplanForTest(true);

            $dialplan = DB::table('v_dialplans')->first();
            $this->assertNotNull($dialplan);
            $this->assertNull($dialplan->domain_uuid);
            $this->assertSame(SipCaptureService::RTCP_EVENTS_DIALPLAN_NAME, $dialplan->dialplan_name);
            $this->assertSame('global', $dialplan->dialplan_context);
            $this->assertSame('true', $dialplan->dialplan_continue);
            $this->assertSame(SipCaptureService::RTCP_EVENTS_DIALPLAN_ORDER, $dialplan->dialplan_order);
            $this->assertSame('true', $dialplan->dialplan_enabled);
            $this->assertSame(
                SipCaptureService::RTCP_EVENTS_DIALPLAN_DESCRIPTION,
                $dialplan->dialplan_description,
            );
            $this->assertStringContainsString(
                '<action application="export" data="fire_rtcp_events=true" inline="true"/>',
                $dialplan->dialplan_xml,
            );

            $detail = DB::table('v_dialplan_details')->first();
            $this->assertNotNull($detail);
            $this->assertSame('action', $detail->dialplan_detail_tag);
            $this->assertSame('export', $detail->dialplan_detail_type);
            $this->assertSame('fire_rtcp_events=true', $detail->dialplan_detail_data);
            $this->assertSame('true', $detail->dialplan_detail_inline);

            DB::table('v_dialplans')->update([
                'dialplan_order' => 999,
                'dialplan_enabled' => 'false',
                'dialplan_description' => 'Changed in the dialplan editor.',
            ]);
            $service->synchronizeRtcpEventDialplanForTest(true);

            $this->assertSame(1, DB::table('v_dialplans')->count());
            $this->assertSame(1, DB::table('v_dialplan_details')->count());
            $this->assertSame(
                SipCaptureService::RTCP_EVENTS_DIALPLAN_ORDER,
                DB::table('v_dialplans')->value('dialplan_order'),
            );
            $this->assertSame('true', DB::table('v_dialplans')->value('dialplan_enabled'));

            $service->synchronizeRtcpEventDialplanForTest(false);

            $this->assertSame(0, DB::table('v_dialplans')->count());
            $this->assertSame(0, DB::table('v_dialplan_details')->count());
        });
    }

    public function test_rtcp_profile_settings_are_deduplicated_and_follow_the_selected_capture_profiles(): void
    {
        $this->withSqliteDatabase(function () {
            $this->createSipProfileSettingsSchema();
            $service = new TestableProfileSettingsSipCaptureService();
            $internal = $this->sipProfile('11111111-1111-4111-8111-111111111111', 'internal');
            $external = $this->sipProfile('22222222-2222-4222-8222-222222222222', 'external');

            DB::table('v_sip_profile_settings')->insert([
                'sip_profile_setting_uuid' => '33333333-3333-4333-8333-333333333333',
                'sip_profile_uuid' => $external->sip_profile_uuid,
                'sip_profile_setting_name' => SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING,
                'sip_profile_setting_value' => '10000',
                'sip_profile_setting_enabled' => 'true',
                'sip_profile_setting_description' => 'Existing setting.',
            ]);
            DB::table('v_sip_profile_settings')->insert([
                'sip_profile_setting_uuid' => '44444444-4444-4444-8444-444444444444',
                'sip_profile_uuid' => $external->sip_profile_uuid,
                'sip_profile_setting_name' => ' RTCP-AUDIO-INTERVAL-MSEC ',
                'sip_profile_setting_value' => '15000',
                'sip_profile_setting_enabled' => 'true',
                'sip_profile_setting_description' => 'Duplicate setting.',
            ]);

            $service->saveProfileSettingsForTest(
                collect([$internal, $external]),
                collect([$internal->sip_profile_uuid]),
            );

            $internalRtcp = DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $internal->sip_profile_uuid)
                ->where('sip_profile_setting_name', SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING)
                ->first();
            $this->assertNotNull($internalRtcp);
            $this->assertSame(
                SipCaptureService::RTCP_AUDIO_INTERVAL_MSEC,
                $internalRtcp->sip_profile_setting_value,
            );
            $this->assertSame('true', $internalRtcp->sip_profile_setting_enabled);

            $externalRtcp = DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $external->sip_profile_uuid)
                ->whereRaw(
                    'lower(trim(sip_profile_setting_name)) = ?',
                    [SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING],
                )
                ->first();
            $this->assertSame(
                1,
                DB::table('v_sip_profile_settings')
                    ->where('sip_profile_uuid', $external->sip_profile_uuid)
                    ->whereRaw(
                        'lower(trim(sip_profile_setting_name)) = ?',
                        [SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING],
                    )
                    ->count(),
            );
            $this->assertSame('10000', $externalRtcp->sip_profile_setting_value);
            $this->assertSame('false', $externalRtcp->sip_profile_setting_enabled);
            $this->assertSame('Existing setting.', $externalRtcp->sip_profile_setting_description);

            $service->saveProfileSettingsForTest(
                collect([$internal, $external]),
                collect([$external->sip_profile_uuid]),
            );

            $externalRtcp = DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $external->sip_profile_uuid)
                ->where('sip_profile_setting_name', SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING)
                ->first();
            $this->assertSame(
                SipCaptureService::RTCP_AUDIO_INTERVAL_MSEC,
                $externalRtcp->sip_profile_setting_value,
            );
            $this->assertSame('true', $externalRtcp->sip_profile_setting_enabled);

            $service->saveProfileSettingsForTest(collect([$internal, $external]), collect());

            $this->assertSame(
                0,
                DB::table('v_sip_profile_settings')
                    ->where('sip_profile_setting_name', SipCaptureService::RTCP_AUDIO_INTERVAL_SETTING)
                    ->where('sip_profile_setting_enabled', 'true')
                    ->count(),
            );
        });
    }

    public function test_disabling_capture_preserves_an_administrator_rtcp_event_dialplan(): void
    {
        $this->withSqliteDatabase(function () {
            $this->createDialplanSchema();
            $dialplanUuid = '44444444-4444-4444-8444-444444444444';
            $customXml = '<extension name="custom_rtcp"><condition>'
                . '<action application="export" data="fire_rtcp_events=true" inline="true"/>'
                . '</condition></extension>';

            DB::table('v_dialplans')->insert([
                'dialplan_uuid' => $dialplanUuid,
                'domain_uuid' => null,
                'app_uuid' => '55555555-5555-4555-8555-555555555555',
                'hostname' => null,
                'dialplan_name' => 'custom_rtcp',
                'dialplan_number' => null,
                'dialplan_destination' => 'false',
                'dialplan_context' => 'global',
                'dialplan_continue' => 'true',
                'dialplan_xml' => $customXml,
                'dialplan_order' => 16,
                'dialplan_enabled' => 'true',
                'dialplan_description' => 'Administrator managed.',
            ]);

            $service = new TestableCorrelationDialplanSipCaptureService();
            $service->synchronizeRtcpEventDialplanForTest(true);
            $this->assertSame(2, DB::table('v_dialplans')->count());

            $service->synchronizeRtcpEventDialplanForTest(false);

            $this->assertSame(1, DB::table('v_dialplans')->count());
            $this->assertSame($dialplanUuid, DB::table('v_dialplans')->value('dialplan_uuid'));
            $this->assertSame($customXml, DB::table('v_dialplans')->value('dialplan_xml'));
        });
    }

    public function test_it_creates_an_editable_global_x_cid_correlation_dialplan_once(): void
    {
        $this->withSqliteDatabase(function () {
            $this->createDialplanSchema();
            $service = new TestableCorrelationDialplanSipCaptureService();

            $service->ensureCorrelationDialplanForTest();

            $dialplan = DB::table('v_dialplans')->first();
            $this->assertNotNull($dialplan);
            $this->assertNull($dialplan->domain_uuid);
            $this->assertNull($dialplan->hostname);
            $this->assertSame(SipCaptureService::CORRELATION_DIALPLAN_NAME, $dialplan->dialplan_name);
            $this->assertNull($dialplan->dialplan_number);
            $this->assertSame('false', $dialplan->dialplan_destination);
            $this->assertSame('global', $dialplan->dialplan_context);
            $this->assertSame('true', $dialplan->dialplan_continue);
            $this->assertSame(SipCaptureService::CORRELATION_DIALPLAN_ORDER, $dialplan->dialplan_order);
            $this->assertSame('true', $dialplan->dialplan_enabled);
            $this->assertSame('Used to correlate A-leg and B-leg.', $dialplan->dialplan_description);
            $this->assertStringContainsString(
                '<condition field="${sip_h_X-CID}" expression="^$">',
                $dialplan->dialplan_xml,
            );
            $this->assertStringContainsString(
                '<action application="set" data="sip_h_X-CID=${sip_call_id}"/>',
                $dialplan->dialplan_xml,
            );

            $details = DB::table('v_dialplan_details')
                ->orderBy('dialplan_detail_order')
                ->get();
            $this->assertCount(2, $details);
            $this->assertSame('condition', $details[0]->dialplan_detail_tag);
            $this->assertSame('${sip_h_X-CID}', $details[0]->dialplan_detail_type);
            $this->assertSame('^$', $details[0]->dialplan_detail_data);
            $this->assertSame('action', $details[1]->dialplan_detail_tag);
            $this->assertSame('set', $details[1]->dialplan_detail_type);
            $this->assertSame('sip_h_X-CID=${sip_call_id}', $details[1]->dialplan_detail_data);

            $dialplanUuid = $dialplan->dialplan_uuid;
            $service->ensureCorrelationDialplanForTest();

            $this->assertSame(1, DB::table('v_dialplans')->count());
            $this->assertSame(2, DB::table('v_dialplan_details')->count());
            $this->assertSame($dialplanUuid, DB::table('v_dialplans')->value('dialplan_uuid'));
        });
    }

    public function test_it_preserves_an_existing_global_correlation_dialplan(): void
    {
        $this->withSqliteDatabase(function () {
            $this->createDialplanSchema();
            $dialplanUuid = '11a1ae70-362e-444c-b395-2e75639a652f';
            $customXml = '<extension name="add_x_cid_header" continue="true" uuid="' . $dialplanUuid . '"></extension>';

            DB::table('v_dialplans')->insert([
                'dialplan_uuid' => $dialplanUuid,
                'domain_uuid' => null,
                'app_uuid' => '7be8195c-28fb-491c-a4d3-42b58bac3bd3',
                'hostname' => null,
                'dialplan_name' => SipCaptureService::CORRELATION_DIALPLAN_NAME,
                'dialplan_number' => null,
                'dialplan_destination' => 'false',
                'dialplan_context' => 'global',
                'dialplan_continue' => 'true',
                'dialplan_xml' => $customXml,
                'dialplan_order' => 90,
                'dialplan_enabled' => 'false',
                'dialplan_description' => 'Administrator managed.',
            ]);

            (new TestableCorrelationDialplanSipCaptureService())
                ->ensureCorrelationDialplanForTest();

            $this->assertSame(1, DB::table('v_dialplans')->count());
            $this->assertSame($customXml, DB::table('v_dialplans')->value('dialplan_xml'));
            $this->assertSame(90, DB::table('v_dialplans')->value('dialplan_order'));
            $this->assertSame('false', DB::table('v_dialplans')->value('dialplan_enabled'));
            $this->assertSame(0, DB::table('v_dialplan_details')->count());
        });
    }

    private function service(): SipCaptureService
    {
        return new SipCaptureService();
    }

    private function withSqliteDatabase(callable $callback): void
    {
        $databasePath = sys_get_temp_dir() . '/fspbx-sip-capture-' . bin2hex(random_bytes(8)) . '.sqlite';
        $originalConnection = config('database.default');
        touch($databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        try {
            $callback();
        } finally {
            DB::purge('sqlite');
            config(['database.default' => $originalConnection]);
            @unlink($databasePath);
        }
    }

    private function createDialplanSchema(): void
    {
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->uuid('dialplan_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->uuid('app_uuid')->nullable();
            $table->string('hostname')->nullable();
            $table->string('dialplan_name');
            $table->string('dialplan_number')->nullable();
            $table->string('dialplan_destination')->nullable();
            $table->string('dialplan_context');
            $table->string('dialplan_continue');
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order');
            $table->string('dialplan_enabled');
            $table->string('dialplan_description')->nullable();
            $table->dateTime('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
            $table->dateTime('update_date')->nullable();
            $table->uuid('update_user')->nullable();
        });

        Schema::create('v_dialplan_details', function (Blueprint $table) {
            $table->uuid('dialplan_detail_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->uuid('dialplan_uuid');
            $table->string('dialplan_detail_tag');
            $table->string('dialplan_detail_type')->nullable();
            $table->text('dialplan_detail_data')->nullable();
            $table->string('dialplan_detail_break')->nullable();
            $table->string('dialplan_detail_inline')->nullable();
            $table->integer('dialplan_detail_group');
            $table->integer('dialplan_detail_order');
            $table->string('dialplan_detail_enabled');
            $table->dateTime('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
        });
    }

    private function createSipProfileSettingsSchema(): void
    {
        Schema::create('v_sip_profile_settings', function (Blueprint $table) {
            $table->uuid('sip_profile_setting_uuid')->primary();
            $table->uuid('sip_profile_uuid');
            $table->string('sip_profile_setting_name');
            $table->string('sip_profile_setting_value')->nullable();
            $table->string('sip_profile_setting_enabled');
            $table->string('sip_profile_setting_description')->nullable();
            $table->dateTime('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
            $table->dateTime('update_date')->nullable();
            $table->uuid('update_user')->nullable();
        });
    }

    private function sipProfile(string $uuid, string $name): SipProfiles
    {
        $profile = new SipProfiles();
        $profile->forceFill([
            'sip_profile_uuid' => $uuid,
            'sip_profile_name' => $name,
            'sip_profile_hostname' => null,
            'sip_profile_enabled' => 'true',
        ]);

        return $profile;
    }
}

class TestableSipCaptureService extends SipCaptureService
{
    public function __construct(private array $candidates)
    {
    }

    public function availableCaptureId(array $usedCaptureIds): int
    {
        return $this->randomCaptureId(collect($usedCaptureIds));
    }

    public function replacesLegacyCaptureId(int $captureId, string $description): bool
    {
        $variable = new \App\Models\SwitchVariable();
        $variable->forceFill(['var_description' => $description]);

        return $this->shouldReplaceLegacyCaptureId($variable, $captureId);
    }

    protected function generateCaptureIdCandidate(): int
    {
        return array_shift($this->candidates);
    }
}

class TestableCorrelationDialplanSipCaptureService extends SipCaptureService
{
    public function ensureCorrelationDialplanForTest(): void
    {
        $this->ensureCorrelationDialplan();
    }

    public function synchronizeRtcpEventDialplanForTest(bool $enabled): void
    {
        $this->synchronizeRtcpEventDialplan($enabled);
    }

    protected function dialplanService(): DialplanService
    {
        return new class extends DialplanService
        {
            public function clearDialplanCache(?string $context): void
            {
                // Dialplan persistence is under test; filesystem cache access is not needed.
            }
        };
    }
}

class TestableProfileSettingsSipCaptureService extends SipCaptureService
{
    public function saveProfileSettingsForTest(Collection $profiles, Collection $selected): void
    {
        $this->saveProfileSettings($profiles, $selected);
    }
}

class TestableSipCaptureSaveFlowService extends SipCaptureService
{
    public const PROFILE_UUID = '94cbb60b-7cef-425d-b1e7-a28e2b582b37';

    public int $correlationDialplanChecks = 0;

    public array $rtcpDialplanStates = [];

    public int $profileLockCount = 0;

    protected function profiles(bool $includeDisabled = false): Collection
    {
        $profile = new SipProfiles();
        $profile->forceFill([
            'sip_profile_uuid' => self::PROFILE_UUID,
            'sip_profile_name' => 'internal',
            'sip_profile_hostname' => null,
            'sip_profile_enabled' => 'true',
        ]);

        return collect([$profile]);
    }

    protected function ensureCorrelationDialplan(): void
    {
        $this->correlationDialplanChecks++;
    }

    protected function lockProfilesForCaptureSave(Collection $profiles): void
    {
        $this->profileLockCount++;
    }

    protected function synchronizeRtcpEventDialplan(bool $enabled): void
    {
        $this->rtcpDialplanStates[] = $enabled;
    }

    protected function assignCaptureId(string $hostname): int
    {
        return 101;
    }

    protected function saveGlobalSetting(bool $enabled, ?string $captureServer): void
    {
    }

    protected function saveProfileSettings(Collection $profiles, Collection $selected): void
    {
    }

    protected function switchVariableService(): SwitchVariableService
    {
        return new class extends SwitchVariableService
        {
            public function currentHostname(): string
            {
                return 'node-a';
            }

            public function syncVarsXml(bool $clearSessionCache = true): bool
            {
                return true;
            }
        };
    }

    protected function runtimeService(): SofiaProfileRuntimeService
    {
        return new class extends SofiaProfileRuntimeService
        {
            public function __construct()
            {
            }

            public function synchronize(
                Collection $transitions,
                Collection $hostnames,
                ?Collection $captureStates = null,
                ?Collection $globalVariables = null,
            ): bool {
                return true;
            }
        };
    }
}
