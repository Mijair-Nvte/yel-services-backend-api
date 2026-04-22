<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\OrgCompanyNotice;
use App\Models\OrgEvent;
use App\Observers\DocumentObserver;
use App\Observers\OrgCompanyNoticeObserver;
use App\Observers\OrgEventObserver;
use Illuminate\Support\Facades\Gate;
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
        Gate::before(function ($user, $ability) {
            // 1. Intentamos obtener el ID del team de Spatie
            $companyId = getPermissionsTeamId();

            // 2. Si está vacío (porque el middleware 'can' corrió antes), forzamos la lectura de la URL
            if (! $companyId && request()->route('uid')) {
                $uid = request()->route('uid');
                $company = \App\Models\OrgCompany::where('uid', $uid)->first();

                if ($company) {
                    $companyId = $company->id;
                    setPermissionsTeamId($companyId); // Le avisamos a Spatie de una vez
                }
            }

            // 3. Validar si es el Dueño (Owner)
            if ($companyId) {
                // NUEVA LÓGICA: Simplemente verificamos si el usuario es el dueño directo de la empresa
                $isOwner = \App\Models\OrgCompany::where('id', $companyId)
                    ->where('owner_id', $user->id)
                    ->exists();

                if ($isOwner) {
                    return true; // 🚀 PASE VIP ABSOLUTO
                }
            }
        });

        OrgEvent::observe(OrgEventObserver::class);
        Document::observe(DocumentObserver::class);
        OrgCompanyNotice::observe(OrgCompanyNoticeObserver::class);

    }
}
