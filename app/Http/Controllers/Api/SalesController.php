<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesController extends Controller
{
    /**
     * Listar todas las ventas de un Workspace
     */
    public function index($uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // 🟢 CAMBIO AQUÍ: Agregamos el 'processor' a la carga de relaciones (Eager Loading)
        $sales = OrgSale::with(['seller:id,name,email', 'processor:id,name,email'])
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
        Log::info('Iniciando actualización de comisión', [
            'company_uid' => $uid,
            'sale_id' => $saleId,
            'payload' => $request->all(),
        ]);

        try {
            $request->validate([
                'commission_status' => 'required|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date',
                'commission_amount' => 'nullable|numeric|min:0',
                // Si en el futuro quieres actualizar desde el frontend también la comisión del procesador,
                // puedes agregar aquí las reglas de validación para 'processor_commission_status', etc.
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en updateCommission', [
                'errors' => $e->errors(),
            ]);

            return response()->json(['errors' => $e->errors()], 422);
        }

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $sale = OrgSale::where('org_company_id', $company->id)
            ->where('id', $saleId)
            ->firstOrFail();

        Log::info('Venta encontrada antes de update', ['current_sale' => $sale->toArray()]);

        $updated = $sale->update([
            'commission_status' => $request->commission_status,
            'seller_payout_date' => $request->seller_payout_date,
            'commission_amount' => $request->commission_amount ?? $sale->commission_amount,
        ]);

        Log::info('Resultado del update', ['success' => $updated]);

        // 🟢 CAMBIO AQUÍ: Recargamos también la relación del procesador para retornar el objeto completo
        $sale->load(['seller:id,name,email', 'processor:id,name,email']);

        return response()->json([
            'message' => 'Estatus y fecha de comisión actualizados.',
            'data' => $sale,
        ], 200);
    }

    /**
     * Exportar las ventas filtradas a PDF
     */
    public function exportPdf(Request $request, $uid)
    {
        $request->validate([
            'sale_ids' => 'required|array',
            'sale_ids.*' => 'integer',
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Buscamos exactamente los IDs que el frontend nos mandó
        $sales = OrgSale::with(['seller:id,name,email'])
            ->where('org_company_id', $company->id)
            ->whereIn('id', $request->sale_ids)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculamos los totales para el reporte
        $totalAmount = $sales->sum('total_amount');
        $totalCommissions = $sales->sum('commission_amount');

        // Generamos el PDF usando una vista Blade
        $pdf = Pdf::loadView('pdf.sales-report', [
            'sales' => $sales,
            'company' => $company,
            'totalAmount' => $totalAmount,
            'totalCommissions' => $totalCommissions,
            'fechaReporte' => now()->format('d/m/Y H:i'),
        ]);

        // Retornamos el PDF directamente (el frontend lo procesará como archivo)
        return $pdf->download('reporte_ventas.pdf');
    }
}
