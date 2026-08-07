<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgInvestorTier;
use Illuminate\Http\Request;

class InvestorTierController extends Controller
{
    /**
     * Obtener el nivel actual del inversionista y su progreso
     */
    public function current(Request $request)
    {
        try {
            $user = $request->user();
            
            // Usamos el Accessor que creamos en el modelo User
            $currentTier = $user->current_investor_tier;
            
            // Contamos cuántas propiedades cerradas tiene para mostrar el progreso
            $closedPropertiesCount = $user->orgProperties()->where('status', 'closed')->count();

            return response()->json([
                'data' => [
                    'current_tier' => $currentTier,
                    'closed_properties_count' => $closedPropertiesCount,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar tu nivel de inversionista.',
                'debug_error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Listar todos los niveles disponibles (Para mostrar la matriz de beneficios)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // CORRECCIÓN DEFINITIVA BASADA EN TUS MODELOS:
            // Tu relación $user->companies() devuelve modelos OrgCompanyUser (la tabla pivote).
            $companyUserPivot = $user->companies()->first();
            
            // Si el usuario tiene una relación en org_company_users, sacamos el ID de ahí.
            // Si no tiene (es nuevo o un caso raro), usamos 1 por defecto para que no explote.
            $companyId = $companyUserPivot ? $companyUserPivot->org_company_id : 1;

            $tiers = OrgInvestorTier::where('org_company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('min_properties', 'asc')
                ->get();

            return response()->json(['data' => $tiers], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar los beneficios del programa.',
                'debug_error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}