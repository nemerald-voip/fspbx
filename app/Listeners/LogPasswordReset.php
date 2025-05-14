<?php

namespace App\Listeners;

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
            'timestamp'      => now(),
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
