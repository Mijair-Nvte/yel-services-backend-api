<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCustomer;
use App\Models\OrgSale;
use App\Traits\HandlesCustomers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 

class InvestorReferralController extends Controller
{
    use HandlesCustomers; // 2. Usamos el Trait en el controlador

    /**
     * Registra un nuevo referido desde el portal de Yel Investor.
     */
    public function store(Request $request, string $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $investor = $request->user();

            // 1. Validar los datos mínimos
            $request->validate([
                'referredFirstName' => 'required|string|max:255',
                'referredLastName' => 'required|string|max:255',
                'referredPhone' => 'required|string|max:20',
                'referredEmail' => 'nullable|email|max:255',
                'serviceId' => 'required|string',
                'financingType' => 'required|string',
                'relationship' => 'required|string',
                'contactMethod' => 'required|string',
            ]);

            // 2. Usar el Trait para buscar (SOLO POR EMAIL) o crear al cliente
            // Unimos el nombre y apellido porque tu trait pide "fullName"
            $fullName = trim($request->referredFirstName.' '.$request->referredLastName);

            $customerId = $this->findOrCreateCustomer(
                $company->id,
                $fullName,
                $request->referredEmail,
                $request->referredPhone
            );

            // 3. Actualizamos la metadata (ya que el trait base no la incluye por defecto)
            $customer = OrgCustomer::find($customerId);
            $currentMetadata = $customer->metadata ?? []; // Mantenemos la data anterior si existe
            $currentMetadata['relationship_with_investor'] = $request->relationship;
            $currentMetadata['preferred_contact_method'] = $request->contactMethod;

            $customer->update(['metadata' => $currentMetadata]);

            // 4. Registrar la "Venta" en estado pendiente
            $sale = OrgSale::create([
                'org_company_id' => $company->id,
                'org_customer_id' => $customerId,
                'seller_id' => $investor->id,

                // Etiquetas clave para Yel Investor
                'source_type' => 'yel_investor_portal',
                'customer_origin' => 'investor_referral',

                // Combinamos el servicio y financiamiento
                'product_name' => $request->serviceId.' - '.$request->financingType,

                // Datos financieros inicializados en 0 y pendientes
                'total_amount' => 0,
                'payment_status' => 'pending',
                'commission_amount' => 0,
                'commission_status' => 'pending',
            ]);

          // 5. Enviar a GHL (Envuelto en un try-catch para que no bloquee el flujo principal)
            try {
                $this->dispatchToGHL($sale, $request, $investor);
            } catch (\Exception $ghlException) {
                // Solo logueamos el error, pero dejamos que el sistema siga adelante
                Log::error('⚠️ Error no crítico: Falló el despacho a GoHighLevel (Referido Investor).', [
                    'error' => $ghlException->getMessage(),
                    'sale_id' => $sale->id
                ]);
            } 

            return response()->json([
                'success' => true,
                'message' => 'Referido registrado exitosamente.',
                'data' => $sale,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error guardando referido desde Yel Investor: '.$e->getMessage());

            return response()->json(['message' => 'Error al registrar el referido.'], 500);
        }
    }

    /**
     * Envía la información del referido al Dispatcher de GoHighLevel
     */
    private function dispatchToGHL(OrgSale $sale, Request $request, $investor): void
    {
        $payload = [
            'first_name' => $request->referredFirstName,
            'last_name' => $request->referredLastName,
            'email' => $request->referredEmail,
            'phone' => $request->referredPhone,
            'service_purchased' => $sale->product_name, // O el nombre del servicio de interés
            'total_amount' => $sale->total_amount,
            'source' => 'investor_referral_portal',
            'company_id' => $sale->org_company_id,
            // Datos adicionales útiles para tu pipeline de GHL:
            'referred_by_name' => $investor->name,
            'referred_by_email' => $investor->email,
            'relationship' => $request->relationship,
            'contact_method' => $request->contactMethod,
        ];
// 1. Obtenemos la URL específica para referidos de inversores
        $webhookUrl = config('services.ghl.inbound_webhook_referrals_investor_url');

        // 2. Despachamos el Job pasándole el payload Y la URL
        \App\Jobs\SendSaleToGHLDispatcherJob::dispatch($payload, $webhookUrl);
    }
}
