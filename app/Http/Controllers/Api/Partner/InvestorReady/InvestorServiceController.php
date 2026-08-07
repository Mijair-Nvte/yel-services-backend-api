<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestorServiceController extends Controller
{
    /**
     * Obtiene el catálogo de servicios para compras directas (Yel Investor)
     */
    public function index(string $companyUid, Request $request): JsonResponse
    {
        try {
            // 1. Buscamos la compañía
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

            // 2. Obtenemos los servicios activos de la empresa
            // A diferencia de Yel Pro, aquí NO validamos la tabla OrgCompanyPartner
            $services = OrgService::where('org_company_id', $company->id)
                ->where('is_active', true)
                ->select(
                    'uid', 
                    'name', 
                    'description', 
                    'price', 
                    'cover_image',
                    'stripe_price_id' 
                )
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                
                'services' => $services,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Compañía no encontrada.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el catálogo de servicios.',
            ], 500);
        }
    }
}