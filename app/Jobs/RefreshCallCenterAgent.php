<?php

namespace App\Jobs;

use App\Models\CallCenterAgents;
use App\Models\FusionCache;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;

class RefreshCallCenterAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 55;
    public $failOnTimeout = true;
    public $backoff = 15;

    public function __construct(public string $agentUuid, public string $domainUuid) {}

    public function middleware(): array
    {
        return [new RateLimitedWithRedis('default')];
    }

    public function handle(): void
    {
        $agent = CallCenterAgents::where('domain_uuid', $this->domainUuid)->whereKey($this->agentUuid)->first();
        if (! $agent) {
            return;
        }
        if (! Str::isUuid($this->agentUuid)) {
            throw new RuntimeException('Invalid call center agent UUID.');
        }

        FusionCache::clearPattern('configuration:callcenter.conf*');
        $esl = app(FreeswitchEslService::class);
        try {
            if (! $esl->isConnected()) {
                throw new RuntimeException('Unable to connect to FreeSWITCH to refresh the agent.');
            }
            // Tiers reference the immutable agent UUID. Updating this agent does
            // not require reloading every queue assigned to it.
            $response = $esl->executeCommand('callcenter_config agent reload '.$this->agentUuid, false);
            if (! is_string($response) || ! preg_match('/^\+?OK\b/i', trim($response))) {
                throw new RuntimeException('FreeSWITCH did not confirm the agent reload.');
            }
            // Native reload can return +OK even when XML did not contain the agent.
            $rows = $esl->executeCommand('callcenter_config agent list '.$this->agentUuid, false);
            $live = is_array($rows) ? collect($rows)->firstWhere('name', $this->agentUuid) : null;
            $contact = $live['contact'] ?? '';
            // XML adds a channel-variable block, which can itself contain ${...}.
            $contactMatches = $contact === $agent->agent_contact
                || (str_starts_with($contact, '{') && str_ends_with($contact, '}'.$agent->agent_contact));
            if (! $live || ! $contactMatches || ($live['type'] ?? null) !== ($agent->agent_type ?: 'callback')) {
                throw new RuntimeException('FreeSWITCH agent contact does not match the saved configuration.');
            }
        } finally {
            $esl->disconnect();
        }
    }
}
