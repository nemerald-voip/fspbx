<?php

namespace App\Observers;

use App\Models\Extensions;
use Illuminate\Support\Arr;
use App\Events\ExtensionCreated;
use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;
use App\Jobs\FireFollowMePresenceJob;

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
        ExtensionCreated::dispatch($extension);
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
