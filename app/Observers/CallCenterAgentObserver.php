<?php

namespace App\Observers;

use App\Models\CallCenterAgents;
use App\Services\AgentDirectoryCacheService;

class CallCenterAgentObserver
{
    public function created(CallCenterAgents $agent): void
    {
        app(AgentDirectoryCacheService::class)->agentsChanged([$agent->getAttributes()]);
    }

    public function updated(CallCenterAgents $agent): void
    {
        if ($agent->wasChanged(['agent_contact', 'domain_uuid', 'agent_type'])) {
            app(AgentDirectoryCacheService::class)->agentsChanged([$agent->getRawOriginal(), $agent->getAttributes()]);
        }
    }

    public function deleted(CallCenterAgents $agent): void
    {
        app(AgentDirectoryCacheService::class)->agentsChanged([$agent->getAttributes()]);
    }
}
