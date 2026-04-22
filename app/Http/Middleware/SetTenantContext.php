<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\OrgCompany;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        // Si la ruta tiene el parámetro {uid} de la compañía
        $companyUid = $request->route('uid');

        if ($companyUid) {
            $company = OrgCompany::where('uid', $companyUid)->first();
            
            if ($company) {
                // AQUÍ OCURRE LA MAGIA: Le decimos a Spatie en qué compañía estamos
                setPermissionsTeamId($company->id);
            }
        }

        return $next($request);
    }
}