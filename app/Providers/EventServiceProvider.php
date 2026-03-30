<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [
        \App\Events\EventCreated::class => [
            \App\Listeners\SendEventNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}