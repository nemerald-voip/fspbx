<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class EmailSentLogger
{
    public function handle(MessageSent $event): void
    {
        try {
            $logId = optional($event->message->getHeaders()->get('X-Email-Log-Id'))
                ?->getBodyAsString()
                ?? data_get($event->data, 'attributes.logId');

            if (!$logId) {
                // nothing to update
                return;
            }

            EmailLog::where('uuid', $logId)->update([
                'status'     => 'sent',
            ]);
        } catch (\Throwable $e) {
            logger('EmailSentLogger@handle error ' . $e->getMessage());
        }
    }
}
