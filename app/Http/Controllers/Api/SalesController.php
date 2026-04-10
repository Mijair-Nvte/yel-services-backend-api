<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgSale;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    /**
     * Listar todas las ventas de un Workspace
     */
    public function index($uid)
    {
        // Validar que la compañía exista
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Obtener las ventas e incluir los datos del vendedor (relación 'seller')
        $sales = OrgSale::with('seller:id,name,email') // Traemos solo id, name y email del vendedor para no saturar
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
        $request->validate([
            'commission_status' => 'required|in:pending,paid,not_applicable',
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $sale = OrgSale::where('org_company_id', $company->id)
            ->where('id', $saleId)
            ->firstOrFail();

        $sale->update([
            'commission_status' => $request->commission_status,
        ]);

        // Retornamos la venta actualizada con su vendedor
        $sale->load('seller:id,name,email');

        return response()->json([
            'message' => 'Estatus de comisión actualizado correctamente.',
            'data' => $sale,
        ], 200);
    }
}
