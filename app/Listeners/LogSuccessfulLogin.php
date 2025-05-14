<?php

namespace App\Listeners;

use Carbon\Carbon;
use App\Models\UserLog;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $user    = $event->user;
        $request = request();

        UserLog::create([
            'user_log_uuid'  => Str::uuid()->toString(),
            'domain_uuid'    => $user->domain_uuid,
            'timestamp'      => Carbon::now(get_local_time_zone($event->user->domain_uuid)),
            'user_uuid'      => $user->user_uuid,
            'username'       => $user->username,
            'type'           => 'login_attempt',
            'result'         => 'success',
            'remote_address' => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'insert_user'    => $user->user_uuid,
        ]);
    }
}

