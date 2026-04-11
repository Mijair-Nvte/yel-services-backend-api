<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // <--- Importante para los logs

class SalesController extends Controller
{
    /**
     * Listar todas las ventas de un Workspace
     */
    public function index($uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $sales = OrgSale::with('seller:id,name,email')
            ->where('org_company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $sales], 200);
    }

    /**
     * Actualizar el estatus de la comisión de una venta específica
     */
    public function updateCommission(Request $request, $uid, $saleId)
    {
        // LOG 1: Ver qué datos están llegando desde el Frontend
        Log::info("Iniciando actualización de comisión", [
            'company_uid' => $uid,
            'sale_id' => $saleId,
            'payload' => $request->all()
        ]);

        try {
            $request->validate([
                'commission_status' => 'required|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date', 
                'commission_amount' => 'nullable|numeric|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // LOG 2: Si la validación falla, ver qué campo dio error
            Log::error("Error de validación en updateCommission", [
                'errors' => $e->errors()
            ]);
            return response()->json(['errors' => $e->errors()], 422);
        }

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $sale = OrgSale::where('org_company_id', $company->id)
            ->where('id', $saleId)
            ->firstOrFail();

        // LOG 3: Ver el estado antes de la actualización
        Log::info("Venta encontrada antes de update", ['current_sale' => $sale->toArray()]);

        $updated = $sale->update([
            'commission_status' => $request->commission_status,
            'seller_payout_date' => $request->seller_payout_date,
            'commission_amount' => $request->commission_amount ?? $sale->commission_amount,
        ]);

        // LOG 4: Confirmar si Eloquent dice que se guardó
        Log::info("Resultado del update", ['success' => $updated]);

        $sale->load('seller:id,name,email');

        return response()->json([
            'message' => 'Estatus y fecha de comisión actualizados.',
            'data' => $sale,
        ], 200);
    }
}