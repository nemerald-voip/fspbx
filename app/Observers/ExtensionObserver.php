<?php

namespace App\Observers;

use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;
use App\Jobs\FireFollowMePresenceJob;
use App\Jobs\SendSystemStatusNotificationToSlackJob;
use App\Models\Extensions;
use Illuminate\Support\Arr;

class ExtensionObserver
{
    /**
     * Handle the Extensions "created" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function created(Extensions $extension)
    {
        $user = auth()->user();

        // No authenticated user = likely system / CLI / job-created record
        if (!$user) {
            return;
        }

        if (isSuperadmin($user)) {
            return;
        }

        $extension->loadMissing('domain');
        $user->loadMissing('user_adv_fields', 'domain');

        $name =" ({$user->name_formatted})";

        $message = sprintf(
            '*New Extension*: extension %s was created by%s %s in domain %s',
            $extension->extension,
            $name,
            $user->user_email,
            $extension->domain->domain_name ?? $user->domain->domain_name ?? 'Unknown domain',
        );

        SendSystemStatusNotificationToSlackJob::dispatch($message)
            ->onQueue('slack')
            ->afterCommit();
    
    }

    /**
     * Handle the Extensions "updated" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function updated(Extensions $extension)
    {

        // Get the attributes from the model
        $extensionAttributes = $extension->only(['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name']);

        $originalAttributes = Arr::only(
            $extension->getOriginal(),
            ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name']
        );

        if ($extension->voicemail) {
            $vmOriginalAttributes = Arr::only(
                $extension->voicemail->getOriginal(),
                ['voicemail_uuid', 'domain_uuid', 'voicmeail_id', 'voicemail_mail_to']
            );
        } else {
            $vmOriginalAttributes = null;
        }

        ExtensionUpdated::dispatch($extensionAttributes, $originalAttributes, $vmOriginalAttributes);

        // Fire a job if follow_me_enabled changed
        if ($extension->wasChanged('follow_me_enabled')) {
            FireFollowMePresenceJob::dispatch($extension->extension_uuid);
        }
    }

    /**
     * Handle the Extensions "deleted" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function deleted(Extensions $extension)
    {
        // Get the attributes from the model
        $extensionAttributes = $extension->only(['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name']);

        $originalAttributes = Arr::only(
            $extension->getOriginal(),
            ['extension_uuid', 'domain_uuid', 'extension', 'effective_caller_id_name']
        );

        if ($extension->voicemail) {
            $vmOriginalAttributes = Arr::only(
                $extension->voicemail->getOriginal(),
                ['voicemail_uuid', 'domain_uuid', 'voicmeail_id', 'voicemail_mail_to']
            );
        } else {
            $vmOriginalAttributes = null;
        }
        ExtensionDeleted::dispatch($extensionAttributes, $originalAttributes, $vmOriginalAttributes);
    }

    /**
     * Handle the Extensions "restored" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function restored(Extensions $extension)
    {
        //
    }

    /**
     * Handle the Extensions "force deleted" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function forceDeleted(Extensions $extension)
    {
        //
    }
}
