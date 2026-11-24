<?php

namespace Tests\Feature;

use App\Jobs\CheckStuckFaxesJob;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxNotificationJob;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckStuckFaxesJobTest extends TestCase
{
    #[DataProvider('sendingAttempts')]
    public function test_recovery_uses_the_elapsed_time_of_the_current_attempt(
        ?int $pages,
        int $secondsSending,
        int $retryCount,
        string $expectedStatus,
    ): void {
        config([
            'database.default' => 'fax_test',
            'database.connections.fax_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        DB::statement('CREATE TABLE outbound_faxes (
            outbound_fax_uuid TEXT PRIMARY KEY, domain_uuid TEXT, fax_uuid TEXT, source TEXT, destination TEXT,
            file_path TEXT, status TEXT, retry_count INTEGER, retry_limit INTEGER, retry_at DATETIME,
            call_uuid TEXT, current_attempt_uuid TEXT, total_pages INTEGER, response TEXT, updated_at DATETIME
        )');
        DB::statement('CREATE TABLE v_fax_logs (
            fax_log_uuid TEXT PRIMARY KEY, domain_uuid TEXT, fax_uuid TEXT, outbound_fax_uuid TEXT,
            outbound_fax_attempt_uuid TEXT, source TEXT, destination TEXT, fax_success TEXT,
            fax_result_text TEXT, fax_file TEXT, fax_retry_attempts INTEGER, fax_retry_limit INTEGER,
            fax_duration INTEGER, fax_date DATETIME, fax_epoch INTEGER
        )');
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));
        DB::table('outbound_faxes')->insert([
            'outbound_fax_uuid' => 'fax', 'current_attempt_uuid' => 'attempt', 'call_uuid' => 'call',
            'status' => 'sending', 'total_pages' => $pages, 'retry_count' => $retryCount, 'retry_limit' => 3,
            'retry_at' => now()->subSeconds($secondsSending),
        ]);

        $esl = $this->mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(true);
        $esl->shouldReceive('executeCommand')->once()->with('uuid_exists call', false)->andReturn('true');
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldReceive('executeCommand')->with('uuid_kill call', false)
            ->times($expectedStatus === 'sending' ? 0 : 1)->andReturn('+OK');

        (new CheckStuckFaxesJob())->handle($esl);

        $this->assertDatabaseHas('outbound_faxes', ['outbound_fax_uuid' => 'fax', 'status' => $expectedStatus]);
        if ($expectedStatus === 'sending') {
            $this->assertDatabaseCount('v_fax_logs', 0);
            Queue::assertNothingPushed();
        } else {
            $this->assertDatabaseHas('v_fax_logs', ['outbound_fax_attempt_uuid' => 'attempt', 'fax_success' => '0']);
            $job = $expectedStatus === 'trying' ? SendFaxJob::class : SendFaxNotificationJob::class;
            Queue::assertPushed($job, fn ($queued) => $queued->outboundFaxUuid === 'fax');
        }
    }

    public static function sendingAttempts(): array
    {
        return [
            '20 pages, one second left' => [20, 1799, 1, 'sending'],
            '20 pages, time to retry' => [20, 1800, 1, 'trying'],
            'unknown pages, one second left' => [null, 21599, 3, 'sending'],
            'unknown pages, final attempt timed out' => [null, 21600, 3, 'failed'],
        ];
    }
}
