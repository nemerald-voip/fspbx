<?php 

namespace App\Observers;

use App\Models\CallTranscriptionPolicy;
use App\Services\CallTranscriptionConfigService;

class CallTranscriptionPolicyObserver
{
    public function saved(CallTranscriptionPolicy $model): void
    {
        app(CallTranscriptionConfigService::class)->invalidate($model->domain_uuid);
    }

    public function deleted(CallTranscriptionPolicy $model): void
    {
        app(CallTranscriptionConfigService::class)->invalidate($model->domain_uuid);
    }
}
