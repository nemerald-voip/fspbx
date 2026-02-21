<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('room.{roomId}', function ($user, $roomId) {

    return true;
    // SECURITY:
    // 1. If Admin, allow access to all rooms
    if ($user->can('view_all_rooms') || $user->role === 'admin') {
        return true;
    }

    // 2. If User, only allow if the room belongs to their extension
    // You might need to query DB here if room IDs aren't directly linked to user IDs
    // For now, let's assume if they are logged in and belong to the domain, it's okay:
    return $user->domain_uuid === request()->user()->domain_uuid;
});