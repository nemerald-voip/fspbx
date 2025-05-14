<?php

namespace App\Providers;

use App\Events\GreetingDeleted;
use App\Events\ExtensionCreated;
use App\Events\ExtensionDeleted;
use App\Events\ExtensionUpdated;
use Illuminate\Auth\Events\Login;
use App\Listeners\NotifySuperadminListener;
use App\Events\ExtensionSuspendedStatusChanged;
use App\Listeners\NotifyModelsOnGreetingDeleted;
use App\Listeners\UpdateUserWhenExtensionIsUpdated;
use App\Listeners\SuspendUserWhenExtensionIsDeleted;
use App\Listeners\HandleExtensionSuspendedStatusChange;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            \App\Listeners\SetUpUserSession::class,
            \App\Listeners\LogSuccessfulLogin::class,
        ],
        ExtensionCreated::class => [
            NotifySuperadminListener::class,
        ],
        ExtensionUpdated::class => [
            UpdateUserWhenExtensionIsUpdated::class
        ],
        ExtensionDeleted::class => [
            SuspendUserWhenExtensionIsDeleted::class
        ],
        ExtensionSuspendedStatusChanged::class => [
            HandleExtensionSuspendedStatusChange::class,
        ],
        GreetingDeleted::class => [
            NotifyModelsOnGreetingDeleted::class,
        ],
        \Illuminate\Auth\Events\Failed::class        => [
            \App\Listeners\LogFailedLogin::class,
        ],
        \Illuminate\Auth\Events\PasswordReset::class => [
            \App\Listeners\LogPasswordReset::class,
        ],

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
