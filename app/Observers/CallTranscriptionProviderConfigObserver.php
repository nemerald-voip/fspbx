<?php 
namespace App\Observers;

use App\Models\CallTranscriptionProviderConfig;
use App\Services\CallTranscriptionConfigService;

class CallTranscriptionProviderConfigObserver
{
    public function __construct(protected CallTranscriptionConfigService $svc) {}
    public function saved(CallTranscriptionProviderConfig $m): void  { $this->svc->invalidate($m->tenant_uuid); }
    public function deleted(CallTranscriptionProviderConfig $m): void { $this->svc->invalidate($m->tenant_uuid); }
}
