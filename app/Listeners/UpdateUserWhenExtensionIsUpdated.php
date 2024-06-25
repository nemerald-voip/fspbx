<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Session;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Voicemails;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class UpdateUserWhenExtensionIsUpdated implements ShouldQueue
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
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        // Allow only 2 tasks every 1 second
        Redis::throttle('system')->allow(2)->every(1)->then(function () use ($event) {
            // Find email for this extension
            $email = Voicemails::where('voicemail_id', $event->extension['extension'])
                ->where('domain_uuid', $event->extension['domain_uuid'])
                ->pluck('voicemail_mail_to')
                ->first();


            $origEmail = null; 
            if ($event->vmOriginalAttributes) {
                $origEmail = $event->vmOriginalAttributes['voicemail_mail_to'];
            }

            if (!$origEmail) {
                // No email was assigned to this extension before, return 
                $this->delete();
                return;
            }

            if ($origEmail === $email) {
                // Email wasn't updated 
                $this->delete();
                return;
            }

            $user = null; // Initialize $user variable

            // Find all voicemails with this original email address
            $voicemails = Voicemails::where('voicemail_mail_to', $origEmail)
                ->where('domain_uuid', $event->extension['domain_uuid'])
                ->get();

            // if there are more than one voicemails with this email abort and return
            if ($voicemails->count() >= 1) {
                // There are multiple extensions with the same email address
                $this->delete();
                return;
            } 
            
            // If there is no more extension with the original email address
            if ($voicemails->count() === 0) {
                // Find users with this email
                $user = User::where('user_email', $origEmail)->first();
            }

            if ($user) {
                // logger([$user->user_groups]);
                foreach ($user->user_groups as $group) {
                    // if the user has superadmin permissions abort
                    if ($group->group_name == "superadmin"){
                        $this->delete();
                        return;
                    }
                }

                $user->user_enabled = 'false';
                $user->update_date = date("Y-m-d H:i:s");
                $user->update_user = Session::get('user_uuid');
                $user->save();
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }
}
