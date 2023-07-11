<?php

namespace App\Listeners;

use App\Events\ExtensionUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateUser implements ShouldQueue
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
     * @param  \App\Events\ExtensionUpdated  $event
     * @return void
     */
    public function handle(ExtensionUpdated $event)
    {
        logger("Extension Updated Captured");
        // logger([$event->extension->voicemail]);
    }
}
