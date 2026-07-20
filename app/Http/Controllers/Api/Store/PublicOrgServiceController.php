<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgService;

class PublicOrgServiceController extends Controller
{
    /**
     * Lista todos los servicios activos de una compañía para la Landing Page pública.
     */
    public function index(string $uid)
    {
        // 1. Validamos que la compañía exista
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 2. Traemos solo los servicios activos
        $services = OrgService::where('org_company_id', $company->id)
            ->where('is_active', true)
            // Por seguridad, seleccionamos solo lo necesario para pintar la web.
            // NO mandamos el stripe_price_id ni datos de comisiones al cliente final.
            ->select('uid', 'name', 'description','price', 'availability_type', 'available_states','cover_image')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'company_name' => $company->name,
            'data' => $services,
        ], 200);
    }

    /**
     * Valida si un código de referido existe y está activo para una compañía.
     */
    public function validateReferral(string $uid, string $code)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $partnerExists = \App\Models\OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('referral_code', $code)
           ->where('status', 'approved')
            ->exists();

        return response()->json([
            'is_valid' => $partnerExists,
            // Si no es válido, podríamos incluso sugerir no mostrar nada del partner
        ]);
    }
}
