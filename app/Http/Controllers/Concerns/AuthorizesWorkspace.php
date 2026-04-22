<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OrgCompany;

trait AuthorizesWorkspace
{
    protected function authorizeWorkspace(OrgCompany $company): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // El dueño absoluto siempre tiene acceso
        $isOwner = $company->owner_id === $user->id;

        // O revisamos si es un miembro activo
        $isMember = $user->companies()
            ->where('org_company_id', $company->id)
            ->where('is_active', true)
            ->exists();

        if ((! $isOwner && ! $isMember) || ! $company->is_active) {
            abort(403, 'Unauthorized workspace access');
        }

        // Configuramos el ID de equipo para Spatie globalmente en esta petición
        setPermissionsTeamId($company->id);
    }
}
