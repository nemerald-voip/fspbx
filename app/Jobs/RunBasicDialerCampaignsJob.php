<?php

namespace App\Jobs;

use App\Services\BasicDialerService;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBasicDialerCampaignsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $uniqueFor = 86400;

    public function __construct(public string $campaignUuid)
    {
        $this->onQueue('dialer');
    }

    public function handle(BasicDialerService $dialer, FreeswitchEslService $esl): void
    {
        $nextDelay = $dialer->runCampaignCycle($this->campaignUuid, $esl);

        if ($nextDelay !== null) {
            self::dispatch($this->campaignUuid)->delay(now()->addSeconds($nextDelay));
        }
    }

    public function uniqueId(): string
    {
        return $this->campaignUuid;
    }
}
