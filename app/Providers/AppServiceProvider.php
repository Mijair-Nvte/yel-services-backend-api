<?php

namespace App\Providers;
use App\Models\OrgEvent;
use App\Observers\OrgEventObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
          OrgEvent::observe(OrgEventObserver::class);
    }
}
