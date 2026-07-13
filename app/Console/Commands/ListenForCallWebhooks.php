<?php

namespace App\Console\Commands;

use App\Services\CallWebhooks\CallWebhookEventService;
use App\Services\FreeswitchEslService;
use Illuminate\Console\Command;
use Throwable;

class ListenForCallWebhooks extends Command
{
    protected $signature = 'esl:listen-call-webhooks';
    protected $description = 'Listen for extension and queue-agent call events and dispatch CRM webhooks';

    public function handle(
        FreeswitchEslService $eslService,
        CallWebhookEventService $eventService
    ): int {
        $this->info('Starting the FS PBX call webhook event listener.');

        while (true) {
            try {
                if (! $eslService->isConnected()) {
                    $eslService->reconnect();
                }

                $subscribed = $eslService->subscribeToEvents(
                    'plain',
                    'CHANNEL_CREATE CHANNEL_ANSWER CHANNEL_HANGUP_COMPLETE CUSTOM callcenter::info'
                );

                if (! $subscribed) {
                    $this->error('Unable to subscribe to FreeSWITCH call events. Retrying.');
                    sleep(5);
                    continue;
                }

                $this->info('Subscribed to extension and call-center events.');

                $eslService->listen(function ($event) use ($eventService) {
                    try {
                        $eventService->handle($event);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            } catch (Throwable $exception) {
                logger()->error('Call webhook ESL listener disconnected.', [
                    'error' => $exception->getMessage(),
                ]);
                sleep(5);
            }
        }
    }
}
