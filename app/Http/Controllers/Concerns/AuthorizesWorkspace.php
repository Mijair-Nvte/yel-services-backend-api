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

        $hasAccess = $user->companies()
            ->where('org_company_id', $company->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasAccess || ! $company->is_active) {
            abort(403, 'Unauthorized workspace access');
        }
    }
}
