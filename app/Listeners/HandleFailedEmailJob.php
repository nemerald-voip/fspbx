<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Mail\SendQueuedMailable;

class HandleFailedEmailJob
{
    public function handle(JobFailed $event): void
    {
        logger('failed');
        $payload = $event->job->payload();
        $command = $payload['data']['command'];

        logger($payload);

        // Check if the job's command was a Mailable
        if ($command instanceof SendQueuedMailable) {
            logger('mailable');
            // The Mailable may not have its headers set yet. We need to build it.
            $command->build();
            $logId = $command->getSymfonyMessage()->getHeaders()->getHeaderBody('X-Email-Log-ID');
            logger($logId);

            if ($logId) {
                $log = EmailLog::find($logId);
                if ($log && $log->status !== 'sent') {
                    $log->update([
                        'status' => 'failed',
                        'error' => $event->exception->getMessage(),
                    ]);
                }
            }
        }
    }
}