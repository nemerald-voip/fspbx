<?php

namespace Tests\Unit;

use App\Jobs\FetchTranscriptionSummary;
use App\Models\CallTranscription;
use App\Services\CallTranscription\CallTranscriptionService;
use App\Services\OpenAIService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FetchTranscriptionSummaryTest extends TestCase
{
    private Manager $db;
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $openAi;
    private $limiter;
    private Carbon $startedAt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $this->startedAt = Carbon::parse('2026-11-23 10:00:00');
        Carbon::setTestNow($this->startedAt);
        $this->db = new Manager($app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->db->bootEloquent();
        $this->db->getConnection()->getSchemaBuilder()->create('call_transcriptions', function (Blueprint $table) {
            $table->string('uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('summary_external_id');
            $table->string('summary_status');
            $table->text('summary_error')->nullable();
            $table->text('summary_payload')->nullable();
            $table->dateTime('summary_requested_at');
            $table->dateTime('summary_completed_at')->nullable();
            $table->timestamps();
        });
        $this->db->getConnection()->table('call_transcriptions')->insert([
            'uuid' => 'transcription',
            'summary_external_id' => 'response',
            'summary_status' => 'queued',
            'summary_requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->openAi = Mockery::mock(OpenAIService::class);
        $app->instance(OpenAIService::class, $this->openAi);
        $this->limiter = new class {
            public bool $blocked = false;

            public function throttle($name): self { return $this; }
            public function allow($count): self { return $this; }
            public function every($seconds): self { return $this; }
            public function then($allowed, $blocked): void
            {
                $this->blocked ? $blocked() : $allowed();
            }
        };
        Redis::swap($this->limiter);
    }

    protected function tearDown(): void
    {
        try {
            $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
            Mockery::close();
        } finally {
            Carbon::setTestNow();
            $this->db->getConnection()->disconnect();
            if ($this->previousResolver) {
                Model::setConnectionResolver($this->previousResolver);
            } else {
                Model::unsetConnectionResolver();
            }
            Facade::clearResolvedInstance('redis');
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Container::setInstance($this->previousContainer);
            parent::tearDown();
        }
    }

    public function test_polls_for_ten_minutes_then_two_more_hours_without_resetting_after_serialization(): void
    {
        $payload = serialize(new FetchTranscriptionSummary('transcription', 'response'));
        $this->openAi->shouldReceive('retrieveResponseById')->with('response')->times(34)
            ->andReturn(['status' => 'queued']);

        foreach (array_merge(range(1, 10), range(15, 130, 5)) as $minute) {
            Carbon::setTestNow($this->startedAt->copy()->addMinutes($minute));
            $job = unserialize($payload);
            $this->assertSame(0, $job->tries);
            $this->assertSame(3, $job->maxExceptions);
            $this->runJob($job, $minute === 130 ? null : ($minute < 10 ? 60 : 300), $minute === 130);
        }

        $row = CallTranscription::find('transcription');
        $this->assertSame('failed', $row->summary_status);
        $this->assertStringContainsString('OpenAI summary did not finish within 2 hours and 10 minutes.', $row->summary_error);
    }

    public function test_queue_delay_does_not_extend_the_deadline(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(129));
        $this->openAi->shouldReceive('retrieveResponseById')->once()->andReturn(['status' => 'in_progress']);

        $this->runJob($job, 60);
    }

    public function test_expired_job_fails_without_calling_the_provider(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(131));
        $this->openAi->shouldNotReceive('retrieveResponseById');

        $this->runJob($job, null, true);
        $this->assertSame('failed', CallTranscription::find('transcription')->summary_status);
    }

    public function test_throttled_poll_uses_the_slower_interval(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(11));
        $this->limiter->blocked = true;
        $this->openAi->shouldNotReceive('retrieveResponseById');

        $this->runJob($job, 300);
    }

    public function test_unknown_provider_state_uses_the_same_schedule(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(11));
        $this->openAi->shouldReceive('retrieveResponseById')->once()->andReturn(['status' => 'unknown']);

        $this->runJob($job, 300);
    }

    public function test_existing_horizon_payload_keeps_its_original_start_time(): void
    {
        $job = $this->legacyJob();
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(70));
        // The older row timestamp must not replace the actual queue submission time.
        CallTranscription::find('transcription')->update(['summary_requested_at' => now()->subHours(3)]);
        $this->openAi->shouldReceive('retrieveResponseById')->once()->andReturn(['status' => 'queued']);

        $this->runJob($job, 300, false, ['pushedAt' => (string) $this->startedAt->getTimestamp()]);
    }

    public function test_existing_payload_without_horizon_metadata_uses_the_record_start_time(): void
    {
        $job = $this->legacyJob();
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(131));
        $this->openAi->shouldNotReceive('retrieveResponseById');

        $this->runJob($job, null, true);
    }

    public function test_completed_response_is_saved_during_the_slower_polling_window(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(70));
        $this->openAi->shouldReceive('retrieveResponseById')->once()->andReturn([
            'status' => 'completed', 'text' => '{"summary":"Call summary"}',
        ]);
        $delivery = Mockery::mock(CallTranscriptionService::class);
        $delivery->shouldReceive('emailDeliveryConfig')->once()->with(null)->andReturn(['enabled' => false]);
        Container::getInstance()->instance(CallTranscriptionService::class, $delivery);

        $this->runJob($job, null);

        $row = CallTranscription::find('transcription');
        $this->assertSame('completed', $row->summary_status);
        $this->assertSame(['summary' => 'Call summary'], $row->summary_payload);
        $this->assertTrue($row->summary_completed_at->equalTo(now()));
    }

    public function test_expired_poll_cannot_fail_a_replacement_request(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(131));
        CallTranscription::find('transcription')->update(['summary_external_id' => 'replacement']);
        $this->openAi->shouldNotReceive('retrieveResponseById');

        $this->runJob($job, null);
        $job->failed(new RuntimeException('Old poll failed.'));

        $row = CallTranscription::find('transcription');
        $this->assertSame('queued', $row->summary_status);
        $this->assertNull($row->summary_error);
    }

    public function test_expired_poll_preserves_an_already_completed_summary(): void
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        Carbon::setTestNow($this->startedAt->copy()->addMinutes(131));
        CallTranscription::find('transcription')->update(['summary_status' => 'completed']);
        $this->openAi->shouldNotReceive('retrieveResponseById');

        $this->runJob($job, null);
        $job->failed(new RuntimeException('Old poll failed.'));

        $this->assertSame('completed', CallTranscription::find('transcription')->summary_status);
    }

    private function legacyJob(): FetchTranscriptionSummary
    {
        $job = new FetchTranscriptionSummary('transcription', 'response');
        $job->pollingStartedAt = null;

        // SerializesModels omits a default-null property, matching pre-change payloads.
        return unserialize(serialize($job));
    }

    private function runJob(FetchTranscriptionSummary $job, ?int $delay, bool $expires = false, array $payload = []): void
    {
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('payload')->andReturn($payload);
        if ($delay === null) {
            $queueJob->shouldNotReceive('release');
        } else {
            $queueJob->shouldReceive('release')->once()->with($delay);
        }
        if ($expires) {
            $queueJob->shouldReceive('fail')->once()
                ->with(Mockery::on(fn ($e) => $e instanceof RuntimeException
                    && $e->getMessage() === 'OpenAI summary did not finish within 2 hours and 10 minutes.'))
                ->andReturnUsing(fn ($e) => $job->failed($e));
        } else {
            $queueJob->shouldNotReceive('fail');
        }
        $job->setJob($queueJob)->handle();
    }
}
