<?php

namespace App\Providers;

use App\Models\OrgEvent;
use App\Models\Document;
use App\Models\OrgCompanyNotice;
use App\Observers\DocumentObserver;
use App\Observers\OrgEventObserver;
use App\Observers\OrgCompanyNoticeObserver;
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
        Document::observe(DocumentObserver::class);
        OrgCompanyNotice::observe(OrgCompanyNoticeObserver::class);
        
    }
}
