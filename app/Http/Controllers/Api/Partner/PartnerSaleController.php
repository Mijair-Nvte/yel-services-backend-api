<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\OrgSale;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
class PartnerSaleController extends Controller
{
    /**
     * Devuelve la lista paginada de ventas del partner autenticado.
     */
    public function index(Request $request)
    {
        // Obtenemos el ID del usuario autenticado (el Partner)
        $partnerId = $request->user()->id;

        $sales = OrgSale::where('seller_id', $partnerId)
            ->orderBy('created_at', 'desc')
            // Seleccionamos solo los campos que el partner necesita ver
            ->select([
                'id',
                'uid',
                'customer_name',
                'product_name',
                'total_amount',
                'commission_amount',
                'commission_status',
                'seller_payout_date',
                'created_at',
            ])
            ->paginate(15); // Paginación de 15 en 15 para la tabla

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

            // Obtenemos SOLO las ventas que pertenecen a este Partner (auth->id)
            $sales = OrgSale::where('org_company_id', $company->id)
                ->where('seller_id', auth()->id()) // IMPORTANTE: Seguridad para que no vea ventas de otros
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
