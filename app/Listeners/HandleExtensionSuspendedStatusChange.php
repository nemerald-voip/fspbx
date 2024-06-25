<?php

namespace App\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\ExtensionSuspendedStatusChanged;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class HandleExtensionSuspendedStatusChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('default'))];
    }


    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ExtensionSuspendedStatusChanged $event): void
    {
        // Enable DND for this extension
        if ($event->model->extension) {
            if ($event->model->suspended) {
                $event->model->extension->do_not_disturb = 'true';
            } else {
                $event->model->extension->do_not_disturb = 'false';
            }
            $event->model->extension->save();
        } 

        // Disable Vocemail if exists
        if ($event->model->extension && $event->model->extension->voicemail) {
            if ($event->model->suspended) {
                $event->model->extension->voicemail->voicemail_enabled = 'false';
            } else {
                $event->model->extension->voicemail->voicemail_enabled = 'true';
            }
            $event->model->extension->voicemail->save();
        } 


    }
}
