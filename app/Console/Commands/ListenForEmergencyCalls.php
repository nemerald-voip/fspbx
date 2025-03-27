<?php

namespace App\Console\Commands;

use App\Models\EmergencyCall;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\NotifyEmergencyCallJob;
use App\Services\FreeswitchEslService;


class ListenForEmergencyCalls extends Command
{
    protected $signature = 'esl:listen-emergency';
    protected $description = 'Listen for emergency calls via ESL';

    public function handle(FreeswitchEslService $eslService)
    {
        $this->info('Subscribing to ESL events...');

        if (!$eslService->subscribeToEvents('plain', 'CHANNEL_CREATE')) {
            $this->error('Failed to subscribe to events.');
            return;
        }

        $this->info('Listening for emergency calls...');

        // Load emergency call triggers
        $emergencyCalls = EmergencyCall::with('members')->get();

        $eslService->listen(function ($event) use ($emergencyCalls) {
            $destination = $event->getHeader('Caller-Destination-Number');
            $caller = $event->getHeader('Caller-Caller-ID-Number');
            $domain = $event->getHeader('variable_domain_uuid');

            if (!$destination || !$domain) return;

            $match = $emergencyCalls->first(function ($call) use ($destination, $domain) {
                return $call->domain_uuid === $domain && $call->emergency_number === $destination;
            });

            if (!$match) return;

            logger()->alert("🚨 Emergency call detected from $caller to $destination on domain $domain");
            logger()->info("Will notify {$match->members->count()} members");

            foreach ($match->members as $member) {
                logger()->info("Notify extension_uuid: {$member->extension_uuid}");
                // Here you can originate a call or dispatch a job
            }

            // dispatch(new NotifyEmergencyCallJob($match, $caller));
        });
    }
}
