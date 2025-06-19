<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;
use App\Models\UserLog;

class LogFailedLogin
{
    public function handle(Failed $event)
    {
        $request    = request();
        $user       = $event->user;                    // may be null if email/username not found
        $credentials= $event->credentials;             // ['email' => '…', …]

        UserLog::create([
            'user_log_uuid'  => Str::uuid()->toString(),
            'domain_uuid'    => optional($user)->domain_uuid,
            'timestamp'      => Carbon::now(get_local_time_zone($event->user->domain_uuid ?? null)),
            'user_uuid'      => optional($user)->user_uuid,
            'username'       => $credentials['email'] ?? $credentials['username'] ?? null,
            'type'           => 'login_attempt',
            'result'         => 'failed',
            'remote_address' => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'insert_user'    => optional($user)->user_uuid,
        ]);
    }
}

