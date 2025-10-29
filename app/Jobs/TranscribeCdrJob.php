<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\CallTranscription\CallTranscriptionService;

class TranscribeCdrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $xmlCdrUuid;
    public ?string $domainUuid;
    public array $overrides;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */

    public $tries   = 10;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300, 1800, 3600];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;


    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    public function __construct(string $xmlCdrUuid, ?string $domainUuid = null, array $overrides = [])
    {
        $this->xmlCdrUuid = $xmlCdrUuid;
        $this->domainUuid = $domainUuid;
        $this->overrides  = $overrides;
        $this->onQueue('transcriptions');
    }

    public function handle(CallTranscriptionService $service): void
    {
        Redis::throttle('transcriptions')->allow(1)->every(1)->then(function () use ($service) {
    
            $providerKey = $service->currentProviderKey($this->domainUuid);

            $row = \App\Models\CallTranscription::updateOrCreate(
                ['xml_cdr_uuid' => $this->xmlCdrUuid],               // lookup by unique key
                [
                    'domain_uuid'     => $this->domainUuid,
                    'provider_key'    => $providerKey,
                    'status'          => 'pending',
                    'request_payload' => $this->overrides ?: null,
                    'requested_at'    => now(),
                    'provider_job_id' => null,
                    'started_at'      => null,
                    'completed_at'    => null,
                    'failed_at'       => null,
                    'error_message'   => null,
                ]
            );
    
            // 2) Kick off provider
            $result = $service->transcribeCdr($this->xmlCdrUuid, $this->domainUuid, $this->overrides);

            logger("submitted");
    
            // Expected: ['id' => '...', 'status' => 'queued'|'processing'|...]
            $row->update([
                'external_id'      => $result['id'] ?? null,
                'status'           => $result['status'] ?? null,
                'request_payload'  => $service->payload ?? null,
                'response_payload' => $result ?: null,
            ]);
    
        }, function () {
            $this->release(1);
        });
    }
}
