<?php

namespace App\Jobs;

use App\Services\Messaging\Outbound\ApiMessageService;
use App\Services\Messaging\Outbound\ApiMessageStatus;
use App\Services\Messaging\PhotoCompressionBusy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PrepareApiMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 10;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 30;

    public function __construct(public string $messageUuid) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('prepare-api-message:'.$this->messageUuid))->releaseAfter(30)->expireAfter(150)];
    }

    public function handle(ApiMessageService $service): void
    {
        try {
            $service->prepare($this->messageUuid);
        } catch (PhotoCompressionBusy $exception) {
            throw $exception;
        } catch (\App\Services\Messaging\Outbound\MediaPreparationException $exception) {
            app(ApiMessageStatus::class)->failPreparation($this->messageUuid);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(ApiMessageStatus::class)->failPreparation($this->messageUuid);
    }
}
