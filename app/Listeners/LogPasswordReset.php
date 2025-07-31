<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\UserLog;

class LogPasswordReset
{
    public function handle(PasswordReset $event)
    {
        $user    = $event->user;
        $request = request();

        UserLog::create([
            'user_log_uuid'  => Str::uuid()->toString(),
            'domain_uuid'    => $user->domain_uuid,
            'timestamp'      => Carbon::now(get_local_time_zone($event->user->domain_uuid)),
            'user_uuid'      => $user->user_uuid,
            'username'       => $user->username,
            'type'           => 'password_reset',
            'result'         => 'success',
            'remote_address' => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'insert_user'    => $user->user_uuid,
        ]);
    }
}
