<?php

namespace App\Observers;

use App\Events\ExtensionCreated;
use App\Models\Extensions;
use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;

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
        $originalAttributes = $extension->getOriginal();
        ExtensionUpdated::dispatch($extension, $originalAttributes);
    }

    /**
     * Handle the Extensions "deleted" event.
     *
     * @param  \App\Models\Extensions  $extensions
     * @return void
     */
    public function deleted(Extensions $extension)
    {
        $originalAttributes = $extension->getOriginal();
        ExtensionDeleted::dispatch($extension, $originalAttributes);
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
