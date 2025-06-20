<?php

namespace App\Console\Commands;

use App\Models\EmergencyCall;
use Illuminate\Console\Command;
use App\Jobs\NotifyEmergencyCallJob;
use App\Jobs\NotifyEmergencyEmailJob;
use Illuminate\Support\Facades\Cache;
use App\Services\FreeswitchEslService;


class ListenForEmergencyCalls extends Command
{
    protected $signature = 'esl:listen-emergency';
    protected $description = 'Listen for emergency calls via ESL';

    public function handle(FreeswitchEslService $eslService)
    {
        $this->info('Starting self-healing ESL listener');

        $subscribed = false;

        while (true) {
            try {
                if (!$eslService->isConnected()) {
                    $this->warn('🔁 ESL disconnected. Reconnecting...');
                    $eslService->reconnect();
                    $subscribed = false; // reset on reconnect
                }

                // 🔁 Subscribe once even on first run
                if (!$subscribed) {
                    if (!$eslService->subscribeToEvents('plain', 'CHANNEL_CREATE')) {
                        $this->error('❌ Failed to subscribe to events.');
                        sleep(5);
                        continue;
                    }
                    $subscribed = true;
                    $this->info('✅ Subscribed to CHANNEL_CREATE events.');
                }

                $eslService->listen(function ($event) {
                    $direction = $event->getHeader('variable_direction');
                    if ($direction !== 'inbound') {
                        return; // only act on inbound calls
                    }
                    $destination = $event->getHeader('Caller-Destination-Number');
                    $caller = $event->getHeader('Caller-Caller-ID-Number');
                    $domain = $event->getHeader('variable_domain_uuid');

                    if (!$destination || !$domain) return;

                    $match = Cache::remember('emergency_calls', now()->addDays(30), function () {
                        return EmergencyCall::with('members', 'emails')->get();
                    })->first(fn($call) => $call->domain_uuid === $domain && $call->emergency_number === $destination);

                    if (!$match) return;

                    logger()->alert("🚨 Emergency call detected from $caller to $destination on domain $domain");
                    logger()->info("Will notify {$match->members->count()} members");

                    // Notify extensions (make calls)
                    foreach ($match->members as $member) {
                        logger()->info("Notify extension_uuid: {$member->extension_uuid}");
                        dispatch(new NotifyEmergencyCallJob($member, $caller));
                    }

                    // Notify emails (send emails)
                    foreach ($match->emails as $email) {
                        logger()->info("Notify email: {$email->email}");
                        dispatch(new NotifyEmergencyEmailJob($email, $caller));
                    }
                });
            } catch (\Throwable $e) {
                logger('💥 Listener crashed: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                sleep(10);
            }
        }
    }
}
