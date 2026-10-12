<?php

namespace App\Console\Commands;

use App\Services\FreeswitchEslService;
use App\Services\Pcap\PcapService;
use Illuminate\Console\Command;

class ListenForPcapCalls extends Command
{
    protected $signature = 'esl:listen-pcap';
    protected $description = 'Record call legs for accounts with an active PCAP capture';

    // FreeSWITCH sends HEARTBEAT every 20 seconds. Silence for longer than this
    // means the connection died, for example when FreeSWITCH restarted.
    private const SILENCE_SECONDS = 60;

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
                    if (!$eslService->subscribeToEvents('plain', 'CHANNEL_HANGUP_COMPLETE HEARTBEAT')) {
                        $this->error('Failed to subscribe to events.');
                        sleep(5);
                        continue;
                    }
                    $subscribed = true;
                    $this->info('Subscribed to CHANNEL_HANGUP_COMPLETE and HEARTBEAT events.');
                }

                // Keep one connection open through quiet periods, so no hangup
                // is lost to a reconnect. recvEventTimed() never returns events
                // with this ESL extension, so poll recvEvent() instead.
                $connection = $eslService->connection();
                $lastEventAt = time();
                while ($eslService->isConnected()) {
                    $event = $connection->recvEvent();
                    if (!$event) {
                        // connected() stays true on a dead socket, so watch for missing heartbeats.
                        if (time() - $lastEventAt > self::SILENCE_SECONDS) {
                            $this->warn('No events from FreeSWITCH for ' . self::SILENCE_SECONDS . ' seconds. Reconnecting.');
                            $eslService->disconnect();
                            break;
                        }
                        usleep(100000);
                        continue;
                    }
                    $lastEventAt = time();

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
