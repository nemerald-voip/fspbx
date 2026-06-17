<?php

namespace App\Jobs;

use App\Models\BasicDialerCampaign;
use App\Services\BasicDialerService;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileBasicDialerAttemptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    public function __construct(public string $campaignUuid)
    {
        $this->onQueue('dialer');
    }

    public function handle(BasicDialerService $dialer, FreeswitchEslService $esl): void
    {
        $campaign = BasicDialerCampaign::query()->whereKey($this->campaignUuid)->first();

        if ($campaign) {
            $dialer->reconcileCampaignAttempts($campaign, $esl);
        }
    }
}
