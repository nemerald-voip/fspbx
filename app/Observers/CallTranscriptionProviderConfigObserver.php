<?php

namespace App\Observers;

use App\Models\CallTranscriptionProviderConfig;
use App\Services\CallTranscriptionConfigService;

class CallTranscriptionProviderConfigObserver
{
    public function saved(CallTranscriptionProviderConfig $model): void
    {
        app(CallTranscriptionConfigService::class)->invalidate($model->domain_uuid);
    }

    public function deleted(CallTranscriptionProviderConfig $model): void
    {
        app(CallTranscriptionConfigService::class)->invalidate($model->domain_uuid);
    }
}
