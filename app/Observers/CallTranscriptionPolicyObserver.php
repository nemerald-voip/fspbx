<?php 
namespace App\Observers;

use App\Models\CallTranscriptionPolicy;
use App\Services\CallTranscriptionConfigService;

class CallTranscriptionPolicyObserver
{
    public function __construct(protected CallTranscriptionConfigService $svc) {}
    public function saved(CallTranscriptionPolicy $m): void  { $this->svc->invalidate($m->tenant_uuid); }
    public function deleted(CallTranscriptionPolicy $m): void { $this->svc->invalidate($m->tenant_uuid); }
}
