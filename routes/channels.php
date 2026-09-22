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


Broadcast::channel('room.{domainUuid}.{roomId}', function ($user, $domainUuid, $roomId) {
    if ($domainUuid !== session('domain_uuid') || !userCheckPermission('messages_view')) {
        return false;
    }
    $local = explode('_', $roomId)[0];
    $members = app(\App\Services\Messaging\MessageParticipantService::class)->members($domainUuid, '+'.$local);
    return $members->isNotEmpty() && (userCheckPermission('messages_view_as')
        || $members->contains('extension_uuid', $user->extension_uuid));
});

Broadcast::channel('extension.{extensionUuid}', function ($user, $extensionUuid) {
    return userCheckPermission('messages_view')
        && ($user->extension_uuid === $extensionUuid || userCheckPermission('messages_view_as'))
        && \App\Models\Extensions::where('domain_uuid', session('domain_uuid'))
            ->where('extension_uuid', $extensionUuid)->exists();
});
