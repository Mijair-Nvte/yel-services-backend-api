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

        // Si ya es partner (o está pendiente), enviamos sus datos
        return response()->json([
            'is_partner' => true,
            'data' => $partner
        ], 200);
    }

    /**
     * Endpoint para que el usuario envíe su solicitud al programa.
     */
    public function join(string $uid, Request $request)
    {
        // 1. Obtener la compañía usando el UID de la URL
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 2. Validar los datos del formulario fiscal
        $validatedData = $request->validate([
            'tax_form_type' => 'required|in:w9,w8ben',
            'legal_name'    => 'required|string|max:255',
            'tax_id'        => 'nullable|string|max:255',
            'address'       => 'required|string',
            'country'       => 'required|string',
        ]);

        try {
            // 3. Llamar al servicio pasando los datos validados
            $partner = $this->partnerService->joinProgram($request->user(), $company, $validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada correctamente. Nuestro equipo está revisando tus datos.',
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