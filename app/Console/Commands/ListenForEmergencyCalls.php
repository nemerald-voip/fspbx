<?php

namespace App\Console\Commands;

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

        $this->info('Listening for emergency calls to 911...');

        $eslService->listen(function ($event) {
            $destination = $event->getHeader('Caller-Destination-Number');
            $caller = $event->getHeader('Caller-Caller-ID-Number');
            $domain = $event->getHeader('variable_domain_uuid');

            logger()->alert("🚨 Emergency call detected from $caller to $destination");
            logger()->alert("Domain $domain");

            // Dispatch job to handle call notification
            // dispatch(new NotifyEmergencyCallJob($caller));
        });
    }
}

