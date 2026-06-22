<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PartnerSaleController extends Controller
{
    /**
     * Devuelve la lista paginada de ventas del partner autenticado.
     */
    public function index(Request $request)
    {
        // Obtenemos el ID del usuario autenticado (el Partner)
        $partnerId = $request->user()->id;

        $sales = OrgSale::with(['customer:id,first_name,last_name,email']) // Eager loading optimizado para el partner
            ->where('seller_id', $partnerId)
            ->orderBy('created_at', 'desc')
            ->select([
                'id',
                'uid',
                'org_customer_id', // 🌟 CRITICAL: Clave foránea necesaria para que funcione el 'with'
                'product_name',
                'total_amount',
                'payment_status',
                'commission_amount',
                'commission_status',
                'seller_payout_date',
                'created_at',
            ])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * Exportar las ventas (comisiones) filtradas del partner a PDF
     */
    public function exportPdf(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // Validamos que envíen el array de IDs
            $request->validate([
                'sale_ids' => 'required|array',
                'sale_ids.*' => 'integer',
            ]);

            // Obtenemos SOLO las ventas del Partner y cargamos su cliente
            $sales = OrgSale::with('customer') // 🌟 Añadido para el reporte PDF del partner
                ->where('org_company_id', $company->id)
                ->where('seller_id', auth()->id()) // Seguridad
                ->whereIn('id', $request->sale_ids)
                ->orderBy('created_at', 'desc')
                ->get();

            $totalAmount = $sales->sum('total_amount');
            $totalCommissions = $sales->sum('commission_amount');

            // Preparación del logo para PDF
            $logoPath = public_path('assets/img/logo-reportes.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $data = file_get_contents($logoPath);
                $logoBase64 = 'data:image/'.$type.';base64,'.base64_encode($data);
            }

            // Llamamos a una vista diferente para el partner
            $pdf = Pdf::loadView('pdf.partner-sales-report', [
                'sales' => $sales,
                'company' => $company,
                'partner' => auth()->user(), // Pasamos los datos del partner
                'totalAmount' => $totalAmount,
                  
                'totalCommissions' => $totalCommissions,
                'fechaReporte' => now()->format('d/m/Y H:i'),
                'logoBase64' => $logoBase64,
            ]);

            return $pdf->download('mis_comisiones.pdf');

        } catch (\Exception $e) {
            \Log::error('Error exportando PDF de Partner: '.$e->getMessage());

            return response()->json(['message' => 'Error al generar el PDF.'], 500);
        }
    }
}
