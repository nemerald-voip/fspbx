<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\SendSystemStatusNotificationToSlack;

class NotifySuperadminListener
{
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

        // Find the user who created this extension
        $user = User::find($event->extension->insert_user);
        if ($user) {
            //Send Notification to Slack if user is not Superadmin
            $message = sprintf(
                '*New Extension*: extension %s was created by %s (%s) in domain %s',
                $event->extension->extension,
                $user->user_adv_fields->first_name,
                $user->user_email,
                $user->domain->domain_name,
            );
            
            "*New Extension*: extension ". $event->extension->extension . " was created by " . $user->user_adv_fields->first_name . " in domain " . $user->domain->domain_name;

            SendSystemStatusNotificationToSlack::dispatchIf(!isSuperadmin($user), $message)->onQueue('slack');
        }
        // DeleteAgentJob::dispatch($event->agent);
    }
}
