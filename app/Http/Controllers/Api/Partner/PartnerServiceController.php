<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyPartner;
use App\Models\OrgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerServiceController extends Controller
{
    public function index(string $companyUid, Request $request): JsonResponse
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // Buscamos el registro de afiliado del usuario logueado en esta compañía
            $partner = OrgCompanyPartner::where('org_company_id', $company->id)
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->firstOrFail();

            // Obtenemos los servicios activos de la empresa
            $services = OrgService::where('org_company_id', $company->id)
                ->where('is_active', true)
                ->select('uid', 'name', 'description', 'price', 'cover_image')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'referral_code' => $partner->referral_code,
                'services' => $services,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el catálogo de servicios.',
            ], 500);
        }
    }
}
