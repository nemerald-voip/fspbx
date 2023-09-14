<?php

namespace App\Providers;

use App\Events\ExtensionCreated;
use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use App\Listeners\NotifySuperadminListener;
use App\Listeners\UpdateUserWhenExtensionIsUpdated;
use App\Listeners\SuspendUserWhenExtensionIsDeleted;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            'App\Listeners\SetUpUserSession',
        ],
        ExtensionCreated::class => [
            NotifySuperadminListener::class,
        ],
        ExtensionUpdated::class => [
            UpdateUserWhenExtensionIsUpdated::class
        ],
        ExtensionDeleted::class => [
            SuspendUserWhenExtensionIsDeleted::class
        ]


    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
