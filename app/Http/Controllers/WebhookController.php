<?php

namespace App\Http\Controllers;

use App\Models\OrgCompany;
use App\Models\OrgCustomer; // Importamos el nuevo modelo de clientes
use App\Models\OrgPaymentLinkMapping;
use App\Models\OrgSale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $customerPhone = $orderData['customer']['phone'] ?? null;

        // OBTENER LA COMPAÑÍA (Workspace)
        $company = OrgCompany::first();

        if (! $company) {
            Log::error('No hay compañías registradas en el sistema.');

            return response()->json(['message' => 'System error'], 500);
        }

        // 🔍 PASO CLAVE: Buscar o registrar al cliente en el directorio central
        $customerId = $this->findOrCreateCustomer($company->id, $customerName, $customerEmail, $customerPhone);

        // Variables por defecto (asumimos que es una venta HUÉRFANA al principio)
        $sellerId = null;
        $commissionAmount = 0; 
        $commissionStatus = 'pending';

        // 🔍 REGLA 2: Buscar si el link está mapeado a un vendedor en la base de datos
        if ($paymentLinkId) {
            $mapping = OrgPaymentLinkMapping::where('ghl_payment_link_id', $paymentLinkId)
                ->where('is_active', true)
                ->first();

            if ($mapping) {
                $sellerId = $mapping->seller_id;
                Log::info("✅ Link mapeado encontrado. Vendedor ID: {$sellerId}.");
            } else {
                Log::warning("⚠️ VENTA HUÉRFANA: El link {$paymentLinkId} generó una venta pero no está asignado a ningún vendedor en la BD.");
            }
        }

        // 3. GUARDAR EN BASE DE DATOS USANDO EL ID DEL CLIENTE
        try {
            $sale = OrgSale::create([
                'org_company_id' => $company->id,
                'org_customer_id' => $customerId, // Nuevo campo asignado
                'source_type' => $sourceType,
                'source_id' => $paymentLinkId,
                'product_name' => $productName,
                'total_amount' => $totalAmount,
                'seller_id' => $sellerId,
                'commission_amount' => $commissionAmount,
                'commission_status' => $commissionStatus,
            ]);

            Log::info("💾 Venta guardada exitosamente con UID: {$sale->uid} asociada al Cliente ID: {$customerId}");

        } catch (\Exception $e) {
            Log::error('❌ Error guardando la venta: '.$e->getMessage());

            return response()->json(['message' => 'Database error'], 500);
        }

        Log::info('-----------------------------------');

        return response()->json(['message' => 'Orden procesada y guardada'], 200);
    }

    // =========================================================================
    // 2. NUEVO WEBHOOK PARA FORMULARIO DE SERVICIOS COMPLETADOS (WORKFLOW GHL)
    // =========================================================================
    public function handleServiceForm(Request $request)
    {
        Log::info('--- PROCESANDO FORMULARIO DE SERVICIO GHL ---');

        try {
            // 1. Datos del Contacto
            $customerName = $request->input('full_name', $request->input('first_name', 'Cliente Desconocido'));
            $customerEmail = $request->input('email');
            $customerPhone = $request->input('phone');
            $contactId = $request->input('contact_id');

            // 2. Datos del Formulario (GHL los esconde dentro de "customData")
            $customData = $request->input('customData', []);

            $productName = $customData['servicio'] ?? 'Servicio sin nombre';
            $montoRaw = $customData['monto'] ?? 0;
            $empleadoEmail = $customData['empleado_email'] ?? null;

            // Limpiamos el monto por si viene con símbolos
            $totalAmount = preg_replace('/[^0-9.]/', '', $montoRaw);
            $totalAmount = is_numeric($totalAmount) ? (float) $totalAmount : 0;

            // OBTENER LA COMPAÑÍA
            $company = OrgCompany::first();
            if (! $company) {
                Log::error('❌ Error: No hay compañías registradas en la tabla org_companies.');

                return response()->json(['message' => 'System error'], 500);
            }

            // 🔍 PASO CLAVE: Buscar o registrar al cliente en el directorio central
            $customerId = $this->findOrCreateCustomer($company->id, $customerName, $customerEmail, $customerPhone);

            // 3. BUSCAR AL EMPLEADO/PROCESADOR POR CORREO
            $processorId = null;
            if (! empty($empleadoEmail)) {
                $user = User::where('email', $empleadoEmail)->first();
                if ($user) {
                    $processorId = $user->id;
                    Log::info("✅ Empleado encontrado en BD: {$user->name} (ID: {$processorId})");
                } else {
                    Log::warning("⚠️ VENTA HUÉRFANA: No existe usuario en la tabla users con el email: {$empleadoEmail}");
                }
            }

            // 4. GUARDAR EN LA TABLA ORG_SALES
            $sale = OrgSale::create([
                'org_company_id' => $company->id,
                'org_customer_id' => $customerId, // Nuevo campo asignado
                'source_type' => 'service_form',
                'source_id' => $contactId,
                'customer_origin' => 'Website Form', // Mantenemos el origen de la venta aquí
                'product_name' => $productName,
                'total_amount' => $totalAmount,
                'seller_id' => $processorId,
                'processor_id' => $processorId,
                'commission_amount' => 0,
                'commission_status' => 'pending',
                'processor_commission_amount' => 0,
                'processor_commission_status' => 'pending',
            ]);

            Log::info("💾 Venta guardada correctamente. Sale UID: {$sale->uid} | Asociada al Cliente ID: {$customerId}");
            Log::info('--- FIN DEL PROCESO ---');

            return response()->json(['message' => 'Success'], 200);

        } catch (\Exception $e) {
            Log::error('❌ CRASH EN WEBHOOK: '.$e->getMessage());

            return response()->json(['message' => 'Database error'], 500);
        }
    }

    /**
     * Función interna de apoyo para buscar o crear un cliente de manera limpia.
     */
    private function findOrCreateCustomer(int $companyId, string $fullName, ?string $email, ?string $phone): int
    {
        $customer = null;

        // 1. Intentar buscar por Email (es nuestro índice principal)
        if (! empty($email)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                ->where('email', $email)
                ->first();
        }

        // 2. Si no hay email o no se encontró, e intentamos buscar por teléfono como plan B
        if (! $customer && ! empty($phone)) {
            $customer = OrgCustomer::where('org_company_id', $companyId)
                ->where('phone', $phone)
                ->first();
        }

        // 3. Si el cliente ya existe en el directorio, devolvemos su ID de inmediato
        if ($customer) {
            Log::info("👤 Cliente existente encontrado: {$customer->first_name} (ID: {$customer->id})");
            return $customer->id;
        }

        // 4. Si es completamente nuevo, separamos el nombre y lo registramos
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? null;

        $newCustomer = OrgCustomer::create([
            'org_company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ]);

        Log::info("👤 Nuevo cliente registrado en el directorio central (ID: {$newCustomer->id})");

        return $newCustomer->id;
    }
}