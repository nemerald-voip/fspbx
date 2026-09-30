<?php

namespace Tests\Unit;

use App\Models\CDR;
use App\Services\{CdrDataService, CdrStatus, QueueCallOutcome};
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class QueueCallOutcomeTest extends TestCase
{
    private Manager $db;
    private $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver = Model::getConnectionResolver();
        $this->db = new Manager();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->db->bootEloquent();
        $this->db->getConnection()->getSchemaBuilder()->create('v_xml_cdr', function (Blueprint $table) {
            $table->string('xml_cdr_uuid')->primary();
            foreach (['domain_uuid', 'status', 'cc_side', 'cc_cause', 'cc_cancel_reason', 'cc_agent_bridged', 'hangup_cause'] as $name) {
                $table->string($name)->nullable();
            }
            $table->boolean('voicemail_message')->nullable();
            $table->boolean('missed_call')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    private function call(array $attributes = []): array
    {
        return $attributes + [
            'xml_cdr_uuid' => 'call', 'domain_uuid' => 'account', 'status' => 'missed',
            'cc_side' => 'member', 'cc_cause' => 'cancel', 'cc_cancel_reason' => 'EXIT_WITH_KEY',
            'cc_agent_bridged' => 'false', 'hangup_cause' => 'NORMAL_CLEARING',
            'missed_call' => true, 'voicemail_message' => false,
        ];
    }

    /** @dataProvider outcomes */
    public function test_display_and_sql_filter_use_the_same_outcome(array $attributes, ?string $outcome, string $status): void
    {
        $attributes = $this->call($attributes);
        $this->db->getConnection()->table('v_xml_cdr')->insert($attributes);
        $this->assertSame($outcome, QueueCallOutcome::fromAttributes($attributes));
        $this->assertSame($status, (new CDR())->setRawAttributes($attributes)->status);
        $row = $this->db->getConnection()->table('v_xml_cdr')
            ->selectRaw('('.QueueCallOutcome::sql().') AS outcome, ('.CdrStatus::sql().') AS display_status')->first();
        $this->assertSame($outcome, $row->outcome);
        $this->assertSame($status, $row->display_status);
        $filter = new \ReflectionMethod(CdrDataService::class, 'applyStatusFilter');
        foreach (['abandoned', 'queue_exited', 'queue_timeout', 'queue_other', 'callback_requested', 'missed call', 'answered', 'cancelled'] as $value) {
            $query = CDR::query()->where('domain_uuid', 'account');
            $filter->invoke(new CdrDataService(), $query, $value);
            $this->assertSame($status === $value ? ['call'] : [], $query->pluck('xml_cdr_uuid')->all(), $value);
        }
        $query = CDR::query()->where('domain_uuid', 'another-account');
        $filter->invoke(new CdrDataService(), $query, $status);
        $this->assertSame(0, $query->count());
    }

    public static function outcomes(): array
    {
        return [
            'caller exits' => [[], 'queue_exited', 'queue_exited'],
            'exit then voicemail message' => [['status' => 'voicemail', 'voicemail_message' => true], 'queue_exited', 'queue_exited'],
            'exit then voicemail without message' => [['status' => 'voicemail'], 'queue_exited', 'queue_exited'],
            'exit then fallback answered' => [['status' => 'answered', 'missed_call' => false], 'queue_exited', 'queue_exited'],
            'abandoned' => [['cc_cancel_reason' => 'BREAK_OUT'], 'abandoned', 'abandoned'],
            'network failure is unknown' => [['cc_cancel_reason' => 'BREAK_OUT', 'hangup_cause' => 'NETWORK_OUT_OF_ORDER'], 'queue_other', 'queue_other'],
            'no agents' => [['cc_cancel_reason' => 'NO_AGENT_TIMEOUT'], 'queue_timeout', 'queue_timeout'],
            'maximum wait' => [['cc_cancel_reason' => 'TIMEOUT'], 'queue_timeout', 'queue_timeout'],
            'synthetic callback deadline' => [['cc_cause' => 'TIMEOUT'], 'queue_timeout', 'queue_timeout'],
            'confirmed callback precedes exit' => [['status' => 'callback_requested'], 'callback_requested', 'callback_requested'],
            'missing reason' => [['cc_cancel_reason' => null], 'queue_other', 'queue_other'],
            'unverified answer' => [['cc_cause' => 'answered', 'cc_agent_bridged' => null, 'status' => 'answered'], 'queue_other', 'queue_other'],
            'verified answer' => [['cc_cause' => 'answered', 'cc_agent_bridged' => 'true', 'status' => 'answered', 'missed_call' => false], 'answered', 'answered'],
            'verified answer then voicemail' => [['cc_cause' => 'answered', 'cc_agent_bridged' => 'true', 'status' => 'voicemail', 'voicemail_message' => true], 'answered', 'answered'],
            'verified bridge overrides generic missed flag' => [['cc_cause' => 'answered', 'cc_agent_bridged' => 'true'], 'answered', 'answered'],
            'agent offer is not a queue exit' => [['cc_side' => 'agent', 'status' => 'cancelled', 'hangup_cause' => 'ORIGINATOR_CANCEL'], null, 'cancelled'],
            'ordinary missed call' => [['cc_side' => null, 'cc_cause' => null, 'cc_cancel_reason' => null], null, 'missed call'],
            'ordinary answered call' => [['cc_side' => null, 'cc_cause' => null, 'status' => 'answered', 'missed_call' => false], null, 'answered'],
        ];
    }

    public function test_production_cancellation_fixture_has_21_abandons_55_exits_and_4_timeouts(): void
    {
        foreach (['BREAK_OUT' => 21, 'EXIT_WITH_KEY' => 55, 'NO_AGENT_TIMEOUT' => 4] as $reason => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->db->getConnection()->table('v_xml_cdr')->insert($this->call([
                    'xml_cdr_uuid' => $reason.$i, 'cc_cancel_reason' => $reason,
                    'status' => $reason === 'EXIT_WITH_KEY' && $i < 14 ? 'voicemail' : 'missed',
                    'voicemail_message' => $reason === 'EXIT_WITH_KEY' && $i < 7,
                ]));
            }
        }
        $filter = new \ReflectionMethod(CdrDataService::class, 'applyStatusFilter');
        foreach (['abandoned' => 21, 'queue_exited' => 55, 'queue_timeout' => 4, 'missed call' => 0, 'voicemail' => 7] as $status => $count) {
            $query = CDR::query();
            $filter->invoke(new CdrDataService(), $query, $status);
            $this->assertSame($count, $query->count(), $status);
            if ($status !== 'voicemail') {
                foreach ($query->get() as $cdr) {
                    $this->assertSame($status, $cdr->status);
                }
            }
        }
    }
}
