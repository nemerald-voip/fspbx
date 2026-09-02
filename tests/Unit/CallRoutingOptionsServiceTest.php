<?php

namespace Tests\Unit;

use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallRoutingOptionsServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/fspbx-call-routing-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_team_voicemail_label_is_preserved_when_reopening_a_ring_group(): void
    {
        $domainUuid = (string) Str::uuid();
        $voicemailUuid = $this->insertVoicemail($domainUuid, '9100', 'Sales');
        $service = new CallRoutingOptionsService($domainUuid);

        request()->merge(['category' => 'voicemails']);
        $options = $service->getOptions();
        $savedOption = $service->reverseEngineerRingGroupExitAction(
            'transfer *999100 XML account.example.test'
        );

        $this->assertSame('9100 - Team voicemail (Sales)', $options[0]['name']);
        $this->assertSame($options[0]['name'], $savedOption['name']);
        $this->assertSame($voicemailUuid, $savedOption['option']);
    }

    public function test_extension_voicemail_label_is_preserved_when_reopening_a_ring_group(): void
    {
        $domainUuid = (string) Str::uuid();
        $extensionUuid = (string) Str::uuid();

        DB::table('v_extensions')->insert([
            'extension_uuid' => $extensionUuid,
            'domain_uuid' => $domainUuid,
            'extension' => '100',
            'effective_caller_id_name' => 'David Duck',
        ]);

        $this->insertVoicemail($domainUuid, '100', 'David Duck');
        $service = new CallRoutingOptionsService($domainUuid);

        request()->merge(['category' => 'voicemails']);
        $options = $service->getOptions();
        $savedOption = $service->reverseEngineerRingGroupExitAction(
            'transfer *99100 XML account.example.test'
        );

        $this->assertSame('100 - David Duck', $options[0]['name']);
        $this->assertSame($options[0]['name'], $savedOption['name']);
    }

    public function test_exact_dialplan_number_is_preferred_over_one_prefixed_number(): void
    {
        $domainUuid = (string) Str::uuid();
        $laughlinQueueUuid = (string) Str::uuid();
        $lakeElsinoreQueueUuid = (string) Str::uuid();

        DB::table('v_dialplans')->insert([
            [
                'dialplan_uuid' => (string) Str::uuid(),
                'domain_uuid' => $domainUuid,
                'dialplan_name' => 'Lake Elsinore Clinic Queue',
                'dialplan_number' => '1600',
                'dialplan_xml' => '<action application="set" data="call_center_queue_uuid='.$lakeElsinoreQueueUuid.'"/>',
                'dialplan_order' => 230,
                'dialplan_enabled' => 'true',
            ],
            [
                'dialplan_uuid' => (string) Str::uuid(),
                'domain_uuid' => $domainUuid,
                'dialplan_name' => 'Laughlin Queue',
                'dialplan_number' => '600',
                'dialplan_xml' => '<action application="set" data="call_center_queue_uuid='.$laughlinQueueUuid.'"/>',
                'dialplan_order' => 230,
                'dialplan_enabled' => 'true',
            ],
        ]);

        $savedOption = (new CallRoutingOptionsService($domainUuid))
            ->reverseEngineerIVROption('transfer 600 XML account.example.test');

        $this->assertSame('contact_centers', $savedOption['type']);
        $this->assertSame($laughlinQueueUuid, $savedOption['option']);
        $this->assertSame('Laughlin Queue', $savedOption['name']);
    }

    public function test_ring_group_timeout_prefers_exact_dialplan_number(): void
    {
        $domainUuid = (string) Str::uuid();
        $laughlinQueueUuid = (string) Str::uuid();

        DB::table('v_dialplans')->insert([
            [
                'dialplan_uuid' => (string) Str::uuid(),
                'domain_uuid' => $domainUuid,
                'dialplan_name' => 'Lake Elsinore Clinic Queue',
                'dialplan_number' => '1600',
                'dialplan_xml' => '<action application="set" data="call_center_queue_uuid='.(string) Str::uuid().'"/>',
                'dialplan_order' => 230,
                'dialplan_enabled' => 'true',
            ],
            [
                'dialplan_uuid' => (string) Str::uuid(),
                'domain_uuid' => $domainUuid,
                'dialplan_name' => 'Laughlin Queue',
                'dialplan_number' => '600',
                'dialplan_xml' => '<action application="set" data="call_center_queue_uuid='.$laughlinQueueUuid.'"/>',
                'dialplan_order' => 230,
                'dialplan_enabled' => 'true',
            ],
        ]);

        $timeoutOption = (new CallRoutingOptionsService($domainUuid))
            ->reverseEngineerRingGroupExitAction('transfer 600 XML account.example.test');

        $this->assertSame('contact_centers', $timeoutOption['type']);
        $this->assertSame($laughlinQueueUuid, $timeoutOption['option']);
        $this->assertSame('Laughlin Queue', $timeoutOption['name']);
    }

    public function test_prefixed_dialplan_number_is_not_used_for_a_short_destination(): void
    {
        $domainUuid = (string) Str::uuid();

        DB::table('v_dialplans')->insert([
            'dialplan_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'dialplan_name' => 'Lake Elsinore Clinic Queue',
            'dialplan_number' => '1600',
            'dialplan_xml' => '<action application="set" data="call_center_queue_uuid='.(string) Str::uuid().'"/>',
            'dialplan_order' => 230,
            'dialplan_enabled' => 'true',
        ]);

        $savedOption = (new CallRoutingOptionsService($domainUuid))
            ->reverseEngineerRingGroupExitAction('transfer 600 XML account.example.test');
        $forwardOption = (new CallRoutingOptionsService($domainUuid))
            ->reverseEngineerForwardAction('600');

        $this->assertNull($savedOption['type']);
        $this->assertNull($savedOption['option']);
        $this->assertNull($savedOption['name']);
        $this->assertSame('external', $forwardOption['type']);
        $this->assertSame('600', $forwardOption['extension']);
    }

    private function insertVoicemail(string $domainUuid, string $voicemailId, string $description): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_voicemails')->insert([
            'voicemail_uuid' => $uuid,
            'domain_uuid' => $domainUuid,
            'voicemail_id' => $voicemailId,
            'voicemail_description' => $description,
        ]);

        return $uuid;
    }

    private function createSchema(): void
    {
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->string('dialplan_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('dialplan_name')->nullable();
            $table->string('dialplan_number')->nullable();
            $table->string('dialplan_context')->nullable();
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order')->nullable();
            $table->string('dialplan_enabled')->nullable();
        });

        Schema::create('v_extensions', function (Blueprint $table) {
            $table->string('extension_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('extension');
            $table->string('effective_caller_id_name')->nullable();
        });

        Schema::create('extension_advanced_settings', function (Blueprint $table) {
            $table->string('setting_uuid')->primary();
            $table->string('extension_uuid');
            $table->boolean('suspended')->default(false);
        });

        Schema::create('v_voicemails', function (Blueprint $table) {
            $table->string('voicemail_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('voicemail_id');
            $table->string('voicemail_description')->nullable();
        });
    }
}
