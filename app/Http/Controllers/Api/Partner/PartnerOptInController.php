<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Services\Partner\PartnerService;
use Illuminate\Http\Request;

class PartnerOptInController extends Controller
{
    protected $partnerService;

    // Inyección de dependencias del servicio
    public function __construct(PartnerService $partnerService)
    {
        $this->partnerService = $partnerService;
    }

    /**
     * Revisa si el usuario actual ya es partner en este workspace
     * y devuelve su información (código de referido, etc.)
     */
    public function status(string $uid, Request $request)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $partner = OrgCompanyPartner::where('org_company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->first();

        // Si no es partner, respondemos con 200 pero indicando false
        if (!$partner) {
            return response()->json([
                'is_partner' => false,
                'data' => null
            ], 200);
        }

        // Si ya es partner, enviamos sus datos
        return response()->json([
            'is_partner' => true,
            'data' => $partner
        ], 200);
    }

    /**
     * Endpoint para que el usuario acepte unirse al programa.
     */
    public function join(string $uid, Request $request)
    {
        // 1. Obtener la compañía usando el UID de la URL
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        try {
            // 2. Llamar al servicio
            $partner = $this->partnerService->joinProgram($request->user(), $company);

            return response()->json([
                'success' => true,
                'message' => '¡Felicidades! Te has unido al programa de Partners.',
                'data' => $partner,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
