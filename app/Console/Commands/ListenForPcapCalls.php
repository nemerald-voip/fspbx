<?php

namespace App\Console\Commands;

use App\Services\FreeswitchEslService;
use App\Services\Pcap\PcapService;
use Illuminate\Console\Command;

class ListenForPcapCalls extends Command
{
    protected $signature = 'esl:listen-pcap';
    protected $description = 'Record call legs for accounts with an active PCAP capture';

    public function handle(FreeswitchEslService $eslService, PcapService $pcap)
    {
        $this->info('Starting PCAP call listener');

        $subscribed = false;
        $node = $pcap->node();

        while (true) {
            try {
                if (!$eslService->isConnected()) {
                    $eslService->reconnect();
                    $subscribed = false;
                }

                if (!$subscribed) {
                    if (!$eslService->subscribeToEvents('plain', 'CHANNEL_HANGUP_COMPLETE')) {
                        $this->error('Failed to subscribe to events.');
                        sleep(5);
                        continue;
                    }
                    $subscribed = true;
                    $this->info('Subscribed to CHANNEL_HANGUP_COMPLETE events.');
                }

                // Keep one connection open through quiet periods, so no hangup
                // is lost to a reconnect. recvEventTimed() never returns events
                // with this ESL extension, so poll recvEvent() instead.
                $connection = $eslService->connection();
                while ($eslService->isConnected()) {
                    $event = $connection->recvEvent();
                    if (!$event) {
                        usleep(100000);
                        continue;
                    }

                    if ($event->getHeader('Event-Name') !== 'CHANNEL_HANGUP_COMPLETE' || !$pcap->capturingDomains()) {
                        continue;
                    }

                    $leg = PcapService::legFromEvent(fn (string $name) => $event->getHeader($name), $node);
                    if ($leg) {
                        $pcap->recordLeg($leg);
                    }
                }
                $subscribed = false;
            } catch (\Throwable $e) {
                logger('PCAP listener error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                $subscribed = false;
                sleep(5);
            }
        }
    }
}
