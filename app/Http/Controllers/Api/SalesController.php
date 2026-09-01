<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * Listar todas las ventas de un Workspace
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Doble Capa de Seguridad
            $this->authorizeWorkspace($company);
            $this->authorize('view_sales');

            $sales = OrgSale::with(['seller:id,name,email', 'seller.partnerProfile.sellerType', 'processor:id,name,email', 'customer'])
                ->where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $sales->transform(function ($sale) {
                // Si la venta tiene un vendedor asignado, extraemos el tipo
                if ($sale->seller && $sale->seller->partnerProfile && $sale->seller->partnerProfile->sellerType) {
                    // Le inyectamos una propiedad "tipo" directamente al objeto seller para fácil acceso en React
                    $sale->seller->type_name = $sale->seller->partnerProfile->sellerType->name;
                    $sale->seller->type_id = $sale->seller->partnerProfile->sellerType->id;
                }

                return $sale;
            });

            return response()->json(['data' => $sales], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar ventas.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar el estatus de la comisión de una venta específica
     */
    public function updateCommission(Request $request, string $uid, $saleId)
    {
        Log::info('Iniciando actualización de comisión', [
            'company_uid' => $uid,
            'sale_id' => $saleId,
            'payload' => $request->all(),
        ]);

        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_sales');

            $request->validate([
                'commission_status' => 'required|in:pending,paid,not_applicable',
                'seller_payout_date' => 'nullable|date',
                'commission_amount' => 'nullable|numeric|min:0',
            ]);

            $sale = OrgSale::where('org_company_id', $company->id)
                ->where('id', $saleId)
                ->firstOrFail();

            $sale->update([
                'commission_status' => $request->commission_status,
                'seller_payout_date' => $request->seller_payout_date,
                'commission_amount' => $request->commission_amount ?? $sale->commission_amount,
            ]);

            // Recargamos relaciones para el frontend
            $sale->load(['seller:id,name,email', 'processor:id,name,email', 'customer']);

            return response()->json([
                'message' => 'Estatus y fecha de comisión actualizados.',
                'data' => $sale,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error en updateCommission', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'No se pudo actualizar la comisión.'], 500);
        }
    }

    /**
     * Exportar las ventas filtradas a PDF
     */
    public function exportPdf(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_sales');

            $request->validate([
                'sale_ids' => 'required|array',
                'sale_ids.*' => 'integer',
            ]);

            $sales = OrgSale::with(['seller:id,name,email', 'customer'])
                ->where('org_company_id', $company->id)
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

            $pdf = Pdf::loadView('pdf.sales-report', [
                'sales' => $sales,
                'company' => $company,
                'totalAmount' => $totalAmount,
                'totalCommissions' => $totalCommissions,
                'fechaReporte' => now()->format('d/m/Y H:i'),
                'logoBase64' => $logoBase64,
            ]);

            return $pdf->download('reporte_ventas.pdf');

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar el PDF.'], 500);
        }
    }

    /**
     * Actualizar los detalles generales de una venta
     */
    public function update(Request $request, string $uid, $saleId)
    {
        Log::info('Iniciando actualización general de venta', ['sale_id' => $saleId]);

        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_sales');

            $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'product_name' => 'required|string|max:255',
                'total_amount' => 'required|numeric|min:0',
                'seller_id' => 'nullable|exists:users,id',
            ]);

            // Obtenemos la venta JUNTO con su cliente
            $sale = OrgSale::with('customer')
                ->where('org_company_id', $company->id)
                ->where('id', $saleId)
                ->firstOrFail();

            // 1. Actualizamos la Venta (producto, monto, vendedor)
            $sale->update([
                'product_name' => $request->product_name,
                'total_amount' => $request->total_amount,
                'seller_id' => $request->seller_id,
            ]);

            // 2. Actualizamos el Cliente (si existe)
            if ($sale->customer) {
                // Separar nombre y apellido por si el front envía "customer_name" unido
                $nameParts = explode(' ', trim($request->customer_name), 2);
                $firstName = $nameParts[0] ?? 'Cliente';
                $lastName = $nameParts[1] ?? null;

                $sale->customer->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $request->customer_email,
                    'phone' => $request->customer_phone,
                ]);
            }

            // Recargamos relaciones para enviar al frontend
            $sale->load(['seller:id,name,email', 'processor:id,name,email', 'customer']);

            return response()->json([
                'message' => 'Venta actualizada correctamente.',
                'data' => $sale,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar detalles de la venta.'], 500);
        }
    }

    /**
     * Eliminar el registro de una venta
     */
    public function destroy(string $uid, $saleId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_sales');

            $sale = OrgSale::where('org_company_id', $company->id)
                ->where('id', $saleId)
                ->firstOrFail();

            $sale->delete();

            Log::info('Venta eliminada correctamente', ['sale_id' => $saleId]);

            return response()->json(['message' => 'Registro eliminado con éxito.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo eliminar la venta.'], 500);
        }
    }
}
