<?php

namespace App\Http\Controllers;

use App\Models\OrgCompany;
use App\Models\OrgPaymentLinkMapping;
use App\Models\OrgSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleGHL(Request $request)
    {
        Log::info('--- PROCESANDO ORDEN GHL ---');

        if (! $request->has('order')) {
            Log::warning('El webhook no contiene datos de una orden.');
            return response()->json(['message' => 'No order data'], 200);
        }

        $orderData = $request->input('order');

        $sourceType = $orderData['source'] ?? null;
        $paymentLinkId = $orderData['source_id'] ?? null;

        // 🛑 REGLA 1: Ignorar absolutamente todo lo que NO sea un payment_link (Ej: Tienda web)
        if ($sourceType !== 'payment_link') {
            Log::info("⏭️ Venta ignorada. El origen es: {$sourceType} (No aplica comisión).");
            Log::info('-----------------------------------');
            return response()->json(['message' => 'Ignored, not a payment link'], 200);
        }

        // Si el código llega hasta aquí, ES un payment link. Extraemos el resto de datos.
        $totalAmount = $orderData['total_price'] ?? 0;

        $productName = 'Producto desconocido';
        if (! empty($orderData['line_items']) && isset($orderData['line_items'][0]['title'])) {
            $productName = $orderData['line_items'][0]['title'];
        }

        $customerName = $orderData['customer']['name'] ?? 'Cliente Desconocido';
        $customerEmail = $orderData['customer']['email'] ?? null;

        // OBTENER LA COMPAÑÍA (Workspace)
        $company = OrgCompany::first();

        if (! $company) {
            Log::error('No hay compañías registradas en el sistema.');
            return response()->json(['message' => 'System error'], 500);
        }

        // Variables por defecto (asumimos que es una venta HUÉRFANA al principio)
        $sellerId = null;
        $commissionAmount = $totalAmount * 0.08; // Calculamos el 8% por si se asigna después
        $commissionStatus = 'pending';

        // 🔍 REGLA 2: Buscar si el link está mapeado a un vendedor en la base de datos
        if ($paymentLinkId) {
            $mapping = OrgPaymentLinkMapping::where('ghl_payment_link_id', $paymentLinkId)
                ->where('is_active', true)
                ->first();

            if ($mapping) {
                // ¡Bingo! Encontramos al vendedor
                $sellerId = $mapping->seller_id;
                Log::info("✅ Link mapeado encontrado. Vendedor ID: {$sellerId}. Comisión: $".$commissionAmount);
            } else {
                // ALERTA: Link nuevo no registrado. Se guarda para no perder el rastro del dinero.
                Log::warning("⚠️ VENTA HUÉRFANA: El link {$paymentLinkId} generó una venta pero no está asignado a ningún vendedor en la BD.");
            }
        }

        // 3. GUARDAR EN BASE DE DATOS (Tenga vendedor asignado o sea huérfana)
        try {
            $sale = OrgSale::create([
                'org_company_id' => $company->id,
                'source_type' => $sourceType,
                'source_id' => $paymentLinkId,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'product_name' => $productName,
                'total_amount' => $totalAmount,
                'seller_id' => $sellerId,
                'commission_amount' => $commissionAmount,
                'commission_status' => $commissionStatus,
            ]);

            Log::info("💾 Venta guardada exitosamente con UID: {$sale->uid}");

        } catch (\Exception $e) {
            Log::error('❌ Error guardando la venta: '.$e->getMessage());
            return response()->json(['message' => 'Database error'], 500);
        }

        Log::info('-----------------------------------');

        return response()->json(['message' => 'Orden procesada y guardada'], 200);
    }
}